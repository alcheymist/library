<?php
namespace invoice;

//The invoice item class management of clients across time. This class is 
//special in that :-
//a) it has to be the first one is posted 
//b) it is the last one to be unposted because 
//c) all posted items makes reference to it
//d) all its data are automatically generated
//e) its report sql is identical to the psoter sql
class item_invoice extends item_binary{
    //
    public function __construct($record) {
        //
        parent::__construct($record, "client", "invoice", "Current Invoice");
        //
        //Invoice is not used for calculating the closing alance; its use in
        //display report is purely aesthetic, 
        $this->aesthetic = true;
    }    

    //Returns an sql for reporting current invoice. An invoice is a posted 
    //client. 
    function detailed_poster(bool $parametrized, bool $postage) {
        //
        $sql = $this->chk(
            "select "
                //
                //Th client primary key is needed for constring the posted
                //invoice. It is placed before the messages fr the sake of 
                //reporting
                ."client.client, "
                
                //Key messages from an invoice:-
                //
                //The short name of the client, used as a unique identifier and
                //used for an account referenc in e-payments
                ."client.name as id, "
                //
                //Add the clients full name
                ."client.title as full_name, "
                //
                //Add the clients room number
                //. "room.uid as room_no, "
                //
                //Report on the current invoice period
                . "{$this->record->invoice->year} as year, "
                . "{$this->record->invoice->month} as month "
            //    
            ."from "
                //
                //The driver for this the client table
                ."client "
                //
                //inner join the agreement to assign room to the invoice
                //. "inner join agreement on agreement.client = agreement.agreement "
                //
                //
                //."inner join room on agreement.room = room.room "
                //
                //Consider only thoses cases that we have a valid agreement
            ."where "
                //
                //Add the parametrized client constraint, if necessary. 
                .($parametrized ? "client.client = :driver ": "true ")
                //
                //All invoices will be displayed, whether posted or not, so, they 
                //shuold behave as if they have not been posted
                . "and " . ($postage ? "true " : "true "),
                //
                $parametrized        
                
        );
        //
        return $sql;
    }
    //Posting a client to create the invoice item. This extends the posting a
    //parent item by inserting the current period record
    //
    //Ensure that this must be the first item to be posted since invoice is 
    //used by all other items
    function post(){
        //
        //Insert the current period, before the invoice
        $this->query(
            "insert 
                into period(year, month, cutoff)"
                ."value("
                    . "'{$this->record->invoice->year}', "
                    . "'{$this->record->invoice->month}', "
                    . "'{$this->cutoff()}' "
                . ")" 
           . "on duplicate key update year=values(year) "
        );
        //Then insert the current invoices for all the clients
        //
        //Posting the invoice binary items generally simply creates
        //a new record in the the invoice table
        $this->query(
        //
        //Create the records to be posted...
        "insert into "
             //     
             //..in the storage table for this item. The fields will be 
             //selected from the statement that follows
            . "invoice ("
                //
                //An voice, by design, does not have any messages, so we cannot 
                //track historical identification details of a client. This is not
                //important because we dont expect it to even over a long tie
                //.$this->fields->messages->names." "
                //
                //Specify all the storage identifiers, except those found in
                //the messages. There must be at least one, hence the leading comma
                ."client, period " 
            . ")"
             //
             // Select ...
             . "("
                . "select "
                    // 
                    //n invoice has no messages, so no data
                    //. $this->fields->messages->data. " "
                    //
                   //The identifiers for an invoice are:-is client
                    //.$this->fields->storage->identifiers->data
                    ."poster.client, "
                    ."period.period "
                   //     
                . "from "
                    //Data come from this items's poster sql
                    . "({$this->poster()}) as poster "
                    //
                    //This is a loose join to period
                    . "join ({$this->current_period()}) as period "
                . ") "
                 //
            . " on duplicate key update period=values(period)"
                          
        );        
    }
    
    //Returns the summary sql of an invoice item
    function summary(bool $parametrized) {
        //
        //Define postage
        $postage = $this->record->invoice->posted;
        //
        //Get the detailed sql
        $posted = $postage ? "report": "poster";
        //
        //get teh name of the functio: detaild_report or detailed_poster
        $fname = "detailed_$posted";
        //
        return $this->chk(
            //    
            "select "
                //
                //The summary field is called value; by default it is based on 
                //th amount column of the detailed sql
                . "detailed.id as value, "
                //
                //The month and year of the invoice
                . "detailed.month, "
                . "detailed.year "
                
            . "from "
                //
                . "({$this->$fname($parametrized, $postage)}) as detailed "
            . "group by detailed.id, detailed.month, detailed.year ",
            $parametrized            
        );
    }

    
    //Unposting an invoice removes the current invoice and period, in that order,
    //from the database. This process will fail if there are entities still
    //linked to the invoice at this point in the process, e.g., the clossing 
    //balances of the yester period, so, removel is consitional to there being
    //no closing balancesassociated wit the invoice. 
    function unpost(){
        //
        //When deleting all the invoices...
        if (!$this->record->invoice->current){
            //
            //Delete the invoices unconditionally
            $this->query(
              //     
              "delete "
                   . "invoice.* "
              . "from "
                  //
                  //Invoice is one source of records to delete
                  . "invoice "
            );
            //
            //Delete the periods unconditionally
            $this->query(
              //     
              "delete "
                   . "period.* "
              . "from "
                  . "period"
            );
            
        }
        //
        //When deleting only invoices of the current period....
        else {
            // 
            $this->query(
                //     
                "delete "
                    //
                    //Delete the invoices and the associated periods
                    . "invoice.* "
                . "from "
                    //
                    //Invoice is one source of records to delete
                    . "invoice "
                            //
                    // Add join to exclude records that were initiated by posting 
                    // of closing balances
                    ."left join closing_balance "
                        . "on closing_balance.invoice=invoice.invoice "
                    //
                    //Apply the current period constraint. 
                    ."inner join ({$this->current_invoice()}) as curr_invoice on "
                            . "invoice.invoice = curr_invoice.invoice " 
                ."where "
                    //
                    //Exclude recoreds initiated by postig of clossing balances            
                    . "closing_balance.closing_balance is null"            
            );
           
            //Delete the periods that have no invoice reference
            $this->query(
                //     
                "delete "
                   . "period.* "
                ."from "
                  . "period "
                  . "left join invoice on invoice.period=period.period "
                ."where "
                    . "invoice.invoice is null"
            );

                        
        }                
    }
        
    //Returns an sql for reporting selected client details. It is the same as 
    //the poster sql
    function detailed_report(bool $parametrized) {
        //
        $sql = $this->chk(
            "select "
                //Key messages from an invoice:-
                //
                //The short name of the client, used as a unique identifier and
                //used for an account referenc in e-payments
                ."client.name as id, "
                //
                //The full name of of the client
                ."client.title as client_name, "
                //
                //Report on the current invoice period
                . "period.year, "
                . "period.month, "
                . "concat(client.name, '-', period.year, '-', period.month)  as ref "
            //     
            ."from "
                //
                //The driver for this the client table
                ."client "
                . "inner join invoice on "
                    . "invoice.client = client.client "
                . "inner join period on invoice.period = period.period "
                //
            ."where "
                //
                //Add the parametrized client constraint, if necessary. 
                .($parametrized ? "invoice.invoice = :driver ": "true "),
                $parametrized
        );
        //
        return $sql;
    }
    
}


