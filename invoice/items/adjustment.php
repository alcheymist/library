<?php
namespace invoice;

//This group of classes supports management of adhoc, rather than routine, 
//adjustments that need to be made to account for unusual circumstances that 
//directly affect the closing balance of a client. 
//
//The following 2 entities  support this item:-
//
//credit(date, purpose, amount, invoice). This models situation when the clients
//  account needed to be credited
//debit(date, purpose, amount, invoice) -- ditto for debit
//
//When posted, the entities are linked to the current invoice

class item_credit extends item_unary {
    //
    public function __construct($record) {
        //
        parent::__construct($record, "credit", "Credit Note");
        //
        //Credits credit a client's invoice
        $this->is_credit = true;
    }
    
    //Returns the sql used for displaying and posting adjustments that credits
    //a client's invoice account for the current period
    function detailed_poster(bool $parametrized, bool $postage){
        //     
        return $this->chk(
            "select "
                //Client is required for posting purposes; its the very first 
                //field in a poster
                ."credit.client, "
                //
                //All unary cases need thoer primary key
                ."credit.credit, "
                //
                //The following fields are desired for user viewing
                //
                //The date of adjustment; it must lower or equal to the cutoff
                ."credit.date, "
                //
                //The reason why the adjustment was done; there must be one as 
                //it is used as an identifier
                ."credit.reason, "
                //
                //Crediting decreases the clients balance...
                ."credit.amount * ".invoice::credit. " as amount "
                
            ."from "
                //The driver of this item
                ."credit "
                //
                //Add a left join to invoice to support the posted constraint
                //which in turn addresses the transaction problem
                . "left join ({$this->current_invoice()}) as invoice "
                    . "on invoice.client = credit.client "
            ."where "
                //
                //Specify the client parameter if necessary. Every poster sql 
                //must be constrained to a specific client
                .($parametrized ? "credit.client=:driver ": "true ")
                //
                //Add the postage constraint, if requested. 
                . "and " . ($postage ? "credit.invoice is null " : "true ")
                //
                //Disregard future adjustments; only the past cases, relative to 
                //current  period,  are considered.
                ."and credit.date<='{$this->operational_cutoff()}' "
                //
                //Consider only the unposted credits, taking care of the 
                //transaction problem. his needs access to the invoive
                ."and not (".table::posted('credit').") ",
                $parametrized        
        );
    }
    
}

class item_debit extends item_unary {
    //
    public function __construct($record) {
        //
        parent::__construct($record, "debit", "Debit Note");
        //
        //Debits do not credit teh client's invoice
        $this->is_credit = false;
    }
    
    //Returns the sql used for displaying and posting adjustments that debit 
    //a client's invoice account for the current period
    function detailed_poster(bool $parametrized, bool $postage){
        //     
        return $this->chk(
            "select "
                //
                //The following fields are desired for user viewing
                //
                //The date of adjustment; it must lower or equal to the cutoff
                ."debit.date, "
                //
                //The reason why the adjustment was done; there must be one as 
                //it is used as an identifier
                ."debit.reason, "
                //
                //Debiting decreases the clients balance...
                ."debit.amount * ".invoice::debit. " as amount, "
                //
                //Client is required for posting purposes; its theh very first field in a
                //poster
                ."debit.client, "
                ."debit.debit "
            ."from "
                //The driver of this item
                ."debit "
                //
                //Add a left join to invoice to support the posted constraint
                //which in turn addresses the transaction problem
                . "left join ({$this->current_invoice()}) as invoice "
                    . "on invoice.client = debit.client "
            ."where "
                //
                //Specify the client parameter if necessary. Every poster sql 
                //must be constrained to a specific client
                .($parametrized ? "debit.client=:driver ": "true ")
                //
                //Add the postage constraint, if requested. 
                . "and " . ($postage ? "debit.invoice is null " : "true ")
                //
                //Disregard future adjustments; only the past cases, relative to 
                //current  period,  are considered, depending on whether we are
                //monitoring or invoiceing clients.
                ."and debit.date<='{$this->operational_cutoff()}' "
                //
                //Consider only the unposted debits
                ."and not (".table::posted('debit').") ",
                $parametrized
        );
    }
    
}
