<?php
namespace invoice;
//
//This class supports management of the opening balances. 
//
//There are 2 types of opening balances. Those:-
//(a) that are initially provided externally (as some special type of adjutment)
//(b) calculated and posted from a previous.
//The opening balance discrepancy is noted if (a) and (b) are both available and
//different from each other for any client.
//After the closing balance derived from (a) is posted, then all opening balances
//below the cutoff should be flagged as posted
//
//The following items support opening balances.
//- auto_balance(client*, period*, amount)
//- manual_balance(clent*, period*, amount, posted)
//
//Auto balances is an item driven by the closing balances. Ths process of posting
//autobalances does not create them. they are created by the closing balance item
//
//Manual balances are fed directly by the user; their dates are adjusted so that
//their dates are consisten with opeing balance. So an entry dated 31/1/2018 is
//re-dated as the opening balance of 1/2/2018
//
//eted with are Note the following:-
//The fields marked * are needed for identification
//
//The post field in teh closing balances table is used for marking when an auto
//balance is posted, because the link to invoice is not sufficient for the job
//
//Opening balance is neither a unary, nor a binary item; it s both! This is 
//because it is driven b
//manual or a auto-genertd item; it is both
class item_opening_balance extends item {
    //
    //The initial, manually captured opening balance
    public $initial;
    //
    //The autoumatically generated opening balances fromt the closing balance
    //of the yester period
    public $auto;
    //
    public function __construct($record) {
        //
        //Closing balance is a composite item made of 2 independent ones:- 
        //
        //Let $auto be items calculated from auto-generated closing balances 
        $this->auto = new item_auto_balance($record);
        //
        //and $manual to be an item drven by the manuuly input balanes
        $this->initial= new item_initial_balance($record);
        
        //
        //The driver of the opening balance is client
        parent::__construct($record, "client", "Opening Balance");
    }
        
    
    //
    //Returns the sql for reporting opening balances to post for the current 
    //period. There are two sources of opening balances. Those:-
    //a) raw inputs loaded by the user directly as opening balances and
    //b) calculated from the previous posting period
    //Assuming multiple balances posted by the user, the likelihood of 
    //discrepancy arises as calculate case may be different from an inputed ones. 
    //This discrepancy must be reported. If a balance is posted, then all unposted
    //cases dated below the posted one must be flagged as posted
    function detailed_poster(bool $parametrized, bool $postage){
        //
        //Define and report discrepancy (condition). A discrepancy occurs if...
        $discrepancy = 
            //...there is an manaully input opening balance amount    
            "initial.amount is not null " 
            //
            //and a  posted/calculated amount...
            ."and auto.amount is not null "
                
            //...that is different from the manual one
            . "and not(auto.amount = initial.amount) ";
        //
        //Let $a be balance amount to be reported, assuming no discrepancy as 
        //either the posted or the initial one -- whichever is not null
        $a = "if(auto.amount is null, initial.amount, auto.amount)";
        //
        //Apply the discrepancy condition; kwhen there is, then use the posted
        //amount in order to maintain historical consistency for opening and closing
        //balances
        $amount = "if($discrepancy, initial.amount, $a)";
        //
        //Compute the current month and year to support formualtion of the 
        //1st day of the current period
        $date =
            $this->record->invoice->year
            ."-"
            .$this->record->invoice->month
            ."-01";    
        //
        return $this->chk(
            "select "
                //
                //Client messages
                //The first day of current period is the date to be associated
                //with the opening balance
                ."'$date' as date, "
                //
                //Compute the actual opening balance ammount, give the intial 
                //and auto versons
                ."($amount) as amount, "
                 //
                //The client is needed to join all the item for calculating 
                //closing balance.
                ."client.client "
            ."from "
                //
                //Drive this process using the clients
                ."client "
                //
                //Extened the client with auto and manual balances
                //
                //Bring in opening balances that were input by the user directly. 
                //They may or may not be available. Hence the left join
                ."left join ({$this->initial->detailed_poster($parametrized, $postage)}) as initial "
                    . "on initial.client=client.client "
                //
                //Bring in the balances automatically posted from a previous 
                //period, which too, may or may not be available. 
                ."left join ({$this->auto->detailed_poster($parametrized, $postage)}) as auto "
                    . "on auto.client = client.client "
                //
                //Add support for testing unposted opening balance. 
                //
                //There is no single storage for opening balances, so the 
                //independent bits do their own tests. In general multi-storage 
                //items cannot do this test at this level.
            //          
            . "where "
                //        
                //Add the client consraint as required by poster items, if 
                //necessary
                . ($parametrized ? "client.client=:driver " : "true ")
                //
                //Filter the driver records to exclude cases where both the 
                //initial and posted balances are empty
                ."and not (auto.amount is null and initial.amount is null)"         
            ,$parametrized
        );
    }
    
    //The detailed report sql of an opening balance item is the union of the
    //the closning balance and the initial balances
    function detailed_report(bool $parametrized){
        //
        return $this->chk(
            "select "
                . "initial.amount "    
            ." from "
                ."({$this->initial->detailed_report($parametrized)}) as initial "
            ." union all "    
            ."select "
                . "auto.amount "    
            ." from "
                ."({$this->auto->detailed_report($parametrized)}) as auto "
            ,$parametrized
        );           
    }
    
    //Posting the closing balance independently posts the constituent items
    function post(){
        $this->auto->post();
        $this->initial->post();
    }
    
    //Unposting opening balaces independely unposts the independentlu componets
    function unpost(){
        $this->auto->unpost();
        $this->initial->unpost();
    }
    
    function summarised_report(bool $parametrized) {
        //
        //Collect, client by client, the amounts used for constructin the 
        //opening balances.
        $storage = $this->chk(
            $this->get_local_sql('balance_initial',$parametrized)
            .' union all '    
            .$this->get_local_sql('closing_balance',$parametrized)
            ,$parametrized
        );        
        //
        //Do the opening balance summary
        return $this->chk(
            //    
            "select "
                //Select all (*) the fields from the storage. We will have to figure
                //out how to throw out primary and foreign key fields from the
                //user reports
                . "storage.client, "
                . "sum(storage.amount) as amount "
            . "from "
            //
            //The storage table drives the reporting. Use alias to avoid 'Not 
            //unique table alia' error
                . "($storage) as storage "
                
            ."group by storage.client"        
            ,$parametrized);
    }

    //Returns the client and amount componennts for summarising purposes
    function get_local_sql($storage,bool $parametrized) {
        //
        return $this->chk(
            //    
            "select "
                //Select all (*) the fields from the storage. We will have to figure
                //out how to throw out primary and foreign key fields from the
                //user reports
                . "invoice.client, "
                . "$storage.amount "
            . "from "
            //
            //The storage table drives the reporting. Use alias to avoid 'Not 
            //unique table alia' error
                . "$storage "
                //
                //We need access to teh client, for grouping purposes
                . "inner join invoice on invoice.client = $storage.invoice "
            . "where "
            //
            //Add the invoice constraint, if needed.
            . ($parametrized ? "$storage.invoice =:driver " : "true ") 
            ,$parametrized    
        );
    }

    
    
}

//This class models balances the initial balances that are fed to the database
//manually. For this version, we assume that all initial balances are opening 
//balances, o/bm so that records dated month end are adjusted to be o/b for
//the month that follows. So, this item is a component of the opening balance. 
//
//Initial balances is a unary item, meaning that it only one posting operand. 
//This means that the query driver table, balance_initial,  is the same as the 
//storage for the psoeted item. Postinng is simly done by linking the driver to 
//the current invoice.
//
//This class asummes that there may be multiple initial available balances for
//any client. This is expected in the early days of lauching a revtal system.
class item_initial_balance extends item_unary{
    
    public function __construct($record) {
        //
        //The driver of manual balance is the initial balance table
        parent::__construct($record, "balance_initial", "Opening Balance");
        //
        $this->aesthetic=true;
    }
    
    //Initial balance item as a component of the opening balance. The sql 
    //returns balances manually input by the user, that match the current
    //month and year
    function detailed_poster(bool $parametrized, bool $postage){
        //
        $sql = $this->chk(
            "select "
                
                //Client reporting message fields
                //
                //Date of the initial balance as reported by the driving initial
                //balance
                . "balance_initial.date, "
                //
                //The o/b amount
                . "balance_initial.amount, "
                //
                //(Primary and foreign) key fields needed establishing joins 
                //with this qiery for posting purposes 
                //
                //The client field is needed for posting by linking to the 
                //current invoice
                 ."balance_initial.client, "
                //
                //This being a unary item, the primary key is rquired (for 
                //the where clause of the update sql during posting)
                . "balance_initial.balance_initial "
            . "from "
                //
                //The driver for this item
                . "balance_initial "
            //
            . "where "
                //        
                //Apply the client parametrized constraint, if requested        
                . ($parametrized ? "balance_initial.client = :driver ": "true ")
                //
                //Apply the postage constraint, if requested. 
                . "and ".($postage ? "balance_initial.invoice is null ": "true ")
                //
                //Match the balances to the current month and year
                ."and month(balance_initial.date) = month('{$this->cutoff()}') "
                ."and year(balance_initial.date) = year('{$this->cutoff()}') "
            , $parametrized
        );
        //
        return $sql;        
    }
    
    //Posting of initial balance performs 2 operations. It :-
    //(a) links the initial balance, a.k.a, ref, to the current invoice 
    //(b) links all the earlier unposted initial balances the same invoice
    //    as the ref
    //Do not be tempted to do (a) and (b) simultaneously, i.e., in one query
    //because (b) is not always guranteed to yield some results -- which would
    //cause (a) to fail some times.
    function post(){
        //
        //Link the initial balabce, ref, to the current invoice
        $this->query(
            "update "
                //
                //The table to update is the driver, the updateee
                ."balance_initial "
                //
                //Identify the data to be posted. Its all unparametrized data
                ."inner join ({$this->poster()}) as poster on "
                    //
                    //Use the primary key (as suggested by Sam) to identify the
                    //updater and teh updateee
                    . "balance_initial.balance_initial = poster.balance_initial "
                //
                //Bring in the current invoice to support linking of the o/b
                //to the invoice, via the update set clause. At this point, we 
                //assume that the current invoice has already been posted        
                . "inner join ({$this->current_invoice()}) as invoice "
                    //
                    //Ensure that we are talking about teh same client
                    . "on balance_initial.client = invoice.client "
            ."set "
                //Point the o/b to the invoice
                ."balance_initial.invoice = invoice.invoice "
        );
                
        //
        //Link the earlier initial balances to the same invoice as the ref 
        //initial balance
        $this->query(
            "update "
                //
                //Let earlier be the initial balances earlier than the ref. 
                //These ar teh ones to update
                ."balance_initial as earlier "
                //
                //We need access to the reference in order to determine the earlier
                //initial balances
                ."inner join balance_initial  as ref "
                    //
                    //Us the clien as the joiner for the earlier and reference 
                    //initial balances
                    . "on earlier.client = ref.client "
                //
                //Select all the generated initial balances as the references
                ."inner join ({$this->detailed_poster(false, false)}) as poster "
                    . "on poster.balance_initial = ref.balance_initial "
                //
                //At this time the ref initial b has its invoice field already set, so
                //it can vbe used for updating the earlier ones.
            ."set "
                //
                //Set the earlier balances to the same invoice as the ref        
                ."earlier.invoice = ref.invoice "
            ."where "
                //
                //Complete the definition of earlier unposted balances
                //     
                //The earlier balance date must less that the reference one        
                . "earlier.date < ref.date "
                //
                //Consider arlier balaces that are not posted        
                . "and earlier.invoice is null " 
        );         
          
        
                
    }

    //To undo the posting of an opening balance, we will need to 
    //(a)break the link from initial balance to the invoice and
    //(b) re-set the osietd flag of the calculated balance
    //for the current invoice
    function unpost(){
        //
        //Delink the initial balances for all clients from he current invoice
        $this->query(
            "update "
                //
                //The record to delink
                ."balance_initial "
                //
                //Select the initial balance to delink
                //
                //It must be linked to an if the current period is required
                . ($this->record->invoice->current 
                    ? "inner join ({$this->current_invoice()}) as invoice on "
                        . "balance_initial.invoice = invoice.invoice "
                    :" ")        
                //
            ."set "
                //
                //Now delink
                ."balance_initial.invoice = null "    
        );
        
        //Flag automatically genererated opening balances, i.e., closing 
        //balances of the yester period, as unposted
        $this->query(
            "update "
                //
                //The record to delink comes from calculated balances
                ."closing_balance "
                //
                //Select the balance to delink
                //
                //It must be linked to an invoice...
                . ($this->record->invoice->current 
                    ? "inner join ({$this->current_invoice()}) as invoice on "
                        . "closing_balance.invoice = invoice.invoice "
                    :" ")        
                //
            ."set "
                //
                //Now reset the posting flag
                ."closing_balance.posted = false "    
                
        );
        
        
    }    
    
    
}

//Automatically generated opening balances are the closing balances of the 
//previous period. This item is a component of the opeining balance that is driven
//by the closing balances. It is a unary item because the driver, closing_balance,
//is the same as the storage -- hence there is only oe posting operand
class item_auto_balance extends item_unary{
    
    public function __construct($record) {
        //
        //The closing balance of the previous month is the opening balance of 
        //the current month
        parent::__construct($record, "closing_balance", "Opening Balance");
        //
        //
        $this->aesthetic=true;
        
    }
    
    //Retuens the closng balances paoted in teh yester period as the opening
    //balances of the current period
    function detailed_poster(bool $parametrized, bool $postage){
        //
        return $this->chk(
           "select "
                //
                //The key message from the closing balance driver is the amount
                . "closing_balance.amount, "
                //
                //storage key identfiers, except invoce.period
                //
                //The client foreign key field needed for linking this component
                //to the compoind opening balance that it is a member of
                . "invoice.client "
                
            ." from "
                //
                //The real table that drives this sql is closing balances
                ."closing_balance "
                //
                //Add joins to support filtering the diver records to return only
                //those of current period. Note that current period is valid for
                //opening balances because the have alraedy been posted as 
                //closing baances in the previous period
                ."inner join ({$this->current_invoice()}) as invoice "
                    . "on invoice.invoice = closing_balance.invoice "
            //    
            . "where "
                //
                //Apply the client parametrized constraint, if requested        
                . ($parametrized ? "invoice.client = :driver ": "true ")
                //
                //Add the postage constraint, if requested. 
                . "and " . ($postage ? "not(closing_balance.posted) " : "true ")
            ,$parametrized);
    }
   
    //Post opening balances by sttring the post field of the closing balance.
    function post(){
        //
        //Unlike posting of closing balances, this one is simply an
        //update
        $this->query(
            "update "
                //
                //We need access to the storage table on which to perform the 
                //update. It is teh same as the driver
                ."closing_balance "
                //
                //Bring in the current invoice to link to the storage
                . " inner join ({$this->current_invoice()}) as invoice "
                    . "on closing_balance.invoice = invoice.invoice "
                //
                //Use the generated records to drive the update without the
                //parametrized client constraint; update everything
                ."inner join ({$this->poster()}) as poster on "
                    //
                    //The on expression uses all identifiers of storage, 
                    //closing_balance,  except period
                    . "poster.client= invoice.client "
            ."set "
                //Now do the the update switch.        
                ."closing_balance.posted= true"        
        );
    }
    
    //To unpost a closing balance is simply to set as false the posted
    //flag of the closing balance table
    function unpost(){
        //
        //Update the storage table for this item
        $this->query(
            "update "
                //
                //The table to update is the storage one
                ."closing_balance "
                //
                //Using a natural join that uses the shared cleint field to 
                //match storage and current apply, if requested, the current
                //period constraint
                .($this->record->invoice->current 
                    ? "inner join ({$this->current_invoice()}) as invoice "
                    . "using(invoice) "
                    : " ")
            ."set "
                //Rest the posting flag
                ."closing_balance.posted = false"
                        
       ); 
    }
    
    
}
