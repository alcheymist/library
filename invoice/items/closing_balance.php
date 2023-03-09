<?php
namespace invoice;
//
//Closing balance is the sum of all non-aesthetic item amounts in the current
//period. Client and closing balances are the only aesthetic items known so far.
//
//Closing balance is a binary item because posting it involves 2 entiies:-
//a) client as the driver and 
//b) closing_balance as the storage table for the posted items
class item_closing_balance extends item_binary {
    //
    public function __construct($record) {
        //
        parent::__construct($record, "client", "closing_balance", "Amount to Pay");
        //
        //Closing balance is an aesthetic item; it is not used for calculating 
        //itself. In a report, it is simply aesthetic.
        $this->aesthetic = true;
    }
    
    //The cutoff period associated with the closing balance is for the next 
    //period
    function cutoff($n=0) {
        return $this->record->invoice->cutoff($n+1);
    }
    //
    //Returns the sql for computing the closing balance for the current period. 
    //It is calculated as the sum of all non-aesthetic item amounts. Invoice and 
    //closing balance items are not used, so, they are said to be known aesthect,
    //because their values in a report is purely aesthetic.
    function detailed_poster(bool $parametrized, bool $postage){
        //
        return $this->chk(
            "SELECT "
                //
                //The messages to communicate to the user
                //
                //The summary amount
                ."summary.amount, "
                //
                //Keys for supporting postage all based on the storrge entity
                //
                //The invoice keys, beyond period
                ."client.client "
                
            ."FROM "
                //The drivver for closing balance summation is the client
                ."client "
                //
                //Extend the client the summary for the closing balance. It is 
                //expected that every client will have a closing balance, and 
                //those that don't will be filtered out by the inner join 
                . "inner join ({$this->summation($parametrized)}) as summary on "
                    . "summary.client = client.client "
                //
                //Include support for testing unposted opening balances using 
                //a left join to posted data (in this items storage storage) in 
                //the current period
                ."left join ({$this->posted_items()}) as storage on "
                    //
                    //We need to match the driver and the storage using all 
                    //storage identifiers except the period,  because the storage
                    //and driver are in the same period zone
                    . "storage.client = client.client "
            . "where "
                //
                //Apply the client parametrized client constraint -- when displaying
                //generated data
                .($parametrized ? "client.client = :driver ": "true ")
                //
                //Add the postage constraint, if requested. 
                . "and " . ($postage ? "storage.client is null " : "true ")
                ,$parametrized
        );
    }
    
    
    function posted_items(){
        //
        return $this->chk(
            //    
            "select "
                //
                //Fields used to fully identify a storage, excluding invoice.period, 
                //e.g., for services, [invoice.client, service.service_type]
                ."invoice.client "
            ."from "
                //
                //The driver for tis item, e.g., service, rent, electricity,
                //water, closing_balane, etc
                ."closing_balance "
                //
                //The storrage invoice must be the same as the current one, so 
                //that this query returns the posted cases for the current period
                ."inner join ({$this->current_invoice()}) as invoice on "
                    . "closing_balance.invoice = invoice.invoice "
                
        );
    }
    
   
    //Returns the Sql for computing the closing balance for this period, as the
    //sum of all non-aesthetic item amounts. Let nbitem be an non-aesthetic item
    function summation(bool $parametrized){
        //
        return $this->chk(
            "SELECT "
                //
                //Sum all the amounts of the union of all non-aesthetic
                //items:- 
                ."sum(nbitem.amount) as amount, "
                //
                //The client field is needed the items detailed poster query.
                . "nbitem.client "
            ."FROM "
                //
                //Bring in all the non-aesthetic items
                . "({$this->union_of_na_items($parametrized)})as nbitem "
            . "Group by "
                  //
                . "nbitem.client"
            ,$parametrized
        );
    }
   
    //Posting a closing balance affects the next period by:-
    //a) creating the next period
    //b) creating the next invoice.
    //
    //In addition, it adds a record to the closing balance that becomes the opening
    //balance of the next period. This is made feasible by noting that the 
    //period shown on a closing balance item is that of the next period
    function post(){
        //
        //Let $d be the curent cutoff date of this closing balance; its one 
        //month ahead of the cuoff date of an invoice.
        $d = $this->cutoff();
        //
        //Create the next period
        $this->dbase->query(
            "insert into "
                . "period(year, month, cutoff) "
                . "value(year('$d'), month('$d'), '$d') "
                //
            . "on duplicate key update "
                . "year=values(year), "
                . "month=values(month), "
                . "cutoff=values(cutoff) "
        );
        //
        //Then insert the current invoices for all the clients
        //
        //Posting the invoice binary items simply create new records in the 
        //invoice table
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
             . "(select "
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
                 //Data come from this items's poster sql, with the following 
                 //conditions:-
                 //
                 //a) No parametrized client constraint is needed for posting the generated,
                 //because database CRUD operations are designed to work with multiple
                 //records.
                 //
                 //b) Pst all the data; we have taken care of duplicate keys
                 . "({$this->poster()}) as poster "
                 //
                 //This is a loose join to period
                 . "join ({$this->current_period()}) as period) "
                 //
            . " on duplicate key update "
                    . "period=values(period)"
                          
        );
        //    
        //Now create the closing balance record, like any other binary case
        $this->dbase->query(
            "insert into "
                //
                //Its the closing balance table we want, the amount message
                //and the closing balance identifiers
                . "closing_balance(amount, invoice) "
                //
                //The data comes from the poster sql for this item
                . "("
                    . "select "
                        //
                        //Let civ, cb be the current invoice and closing balance 
                        //aliases respectively
                        . "poster.amount, invoice.invoice "
                    . "from "
                        //
                        //Get All the current closing balances -- no parametrized 
                        //client constrain; and ignore postage constraint as we have
                        //taken care of duplicate keys
                        . "({$this->detailed_poster(false, false)}) as poster "
                        //
                        //Do a loose join to the current invoice; this is the one
                        //just inserted.
                        . "inner join ({$this->current_invoice()}) as invoice "
                            . "on invoice.client = poster.client "
                . ") "
                    //
                . "on duplicate key update "
                        . "amount = values(amount)" 
        );             
    }
    
    
    //To unpost the closing balances is to delete the closing balance records 
    //that match the current period followed by the invoices of th next
    //period
    function unpost() {
        //
        //Execute the generalized unposting for any binary item. This will 
        //remove the closing_balances of period cutoff(-1)
        parent::unpost();
        //
        //In addition, delete the invoices of that were generated; they may be
        //in use by a posting that was done after this, so you may need to 
        //unpost all postings that came after this.
        try{
            //To force trhowing of exception...
            $this->record->invoice->dbase->query(
                //
                //Let $sql support inspection of the query for debugging purposes...    
                $sql="delete "
                    . "invoice.* "
                . "from "
                    . "invoice "
                    . "inner join period on invoice.period=period.period "
                . "where "
                    . "period.year = year('{$this->cutoff()}') "
                    . "and period.month = month('{$this->cutoff()}')"    
            );
        }catch(\Exception $ex){
            //
            //Attempt to remove invoies failed; you may need to unpost data 
            //posted after this. In future, we will upost the offending cases,
            //rather than report error message
            die ("Please unpost {$this->cutoff()}");
        }            
        //
        //        
        //
        //Then the period
        $this->query(
            "delete "
                . "period.* "
            . "from "
                . "period "
            . "where "
                . "period.year = year('{$this->cutoff()}') "
                . "and period.month = month('{$this->cutoff()}')"       
        );
    }

    
    //Returns the sql that reports on all unionised items that are needed for 
    //computing closing balances, i.e., non-aesthetic items.
    function union_of_na_items(bool $parametrized){
        //
        //There is no initial union operator; after the first union, the 
        //operatop will be set to 'union all'.
        $union_operator = "";
        //
        //Start with an empty sql statement
        $sql="";
        //
        //Visit each non-aesthetic item and add it to the uniting sql.
        //There must at least one item to construct the closing balance
        //
        foreach($this->record->items as $item){
            //
            //Only non-aesthetic cases are considered
            if (!$item->aesthetic){
                //
                //Build the union, checking the subquery as usual
                $sql = $sql . $union_operator . $this->chk(
                    " select "
                        //
                        //The amount to be summed -- taking care whether the item
                        //debits or credits teh client's balance. 
                        ."poster.amount, "
                        //
                        //The client to use for grouping. This implies all poster
                        //queries must report the client.
                        ."poster.client "
                    ."from "
                        //
                        //This query should run for all clients, NOT just a 
                        //specific one, i.e., we don't need the parametrized client
                        //constraint?????. In addition, closing balance calculation needs 
                        //all postable records -- not just those that have not
                        //been posted. So, the postage constraint is not needed
                        . "({$item->detailed_poster($parametrized, false)}) as poster "
                ,$parametrized);
                //
                //Re-set the union operator so that duplicate records are not ignored.
               $union_operator = " union all ";         
            }
        } 
        //
        //We check and return the complete union of all items
        return $this->chk($sql,$parametrized);
    }
    //
    //
    function detailed_report(bool $parametrized) {
        //
        $sql = 
            //    
            "select "
                . "closing_balance.amount,"
                //
                //The closing balances date is that of the curent period's cutoff
                //date.
                . "concat('01-', period_next.month, '-', period_next.year) as due_on  "
            . "from "
                //
                ."closing_balance  "
                //
                //Get 2 invoices, the current one and the next one that is being
                //used for reporting th balances
                //
                . "inner join invoice as invoice_next on  "
                    . "closing_balance.invoice = invoice_next.invoice "
                . "inner join invoice as invoice_current on "
                    //The 2 invoices share a client
                    . "invoice_current.client = invoice_next.client "
                //
                //The periods of the current and next invoice...
                . "inner join period as period_next on "
                .   "invoice_next.period = period_next.period "
                . "inner join period as period_current on "
                .   "period_current.period = invoice_current.period "
            //...are one period apart, i.e., one day after the current period's
            //cutoff date    
            . "where "
                . "period_next.month = month(period_current.cutoff + interval 1 day) and "
                . "period_next.year = year(period_current.cutoff + interval 1 day)  and "
            //
            //Add the invoice constraint, if needed, is based on current invoice.
            . ($parametrized ? "invoice_current.invoice =:driver " : "true ")
        ;
        return $this->chk($sql, $parametrized);
        
    }
}

