<?php
namespace invoice;

//
//This class supports management of payments received from a client. The payment
//table, with a link to invoice, supports this item. Pyment is a manually 
//generated table which means that its records are captured manually.
//Manual records are posted by establishing a link to the current inoice. They
//are unposted by nullifying it
class item_payment extends item_unary{
    //
    public function __construct($record) {
        //
        parent::__construct($record, "payment", "Payment Received");
        //
        //Payments credit a client's invoice
        $this->is_credit = true;
    }
    
    
    //Returns an sql that generates received payments to be posted during the
    //current period.
    function detailed_poster(bool $parametrized, bool $postage){
        //
        return $this->chk(
            "select "
                //Primary and foreign keys
                //
                //The primary key needed for psoting invoices
                ."payment.client, "
                //
                //Needed by all unary case, for filtering purposes
                . "payment.payment, "
                //
                //The key payment messages to be communicated to the client 
                //throug the a monthly invoice report are:-
                //
                //The acutal date when teh amount was paid
                ."payment.date, "
                //
                //Add the reference no.
                ."payment.ref, "
                //
                //The amount paid; it is a credit ampint
                ."payment.amount * ".invoice::credit." as amount "
                
            ."FROM "
                //The driver for payments
                ."payment "
                //
                //Add a left join to invoice to address the trasaction problemt
                . "left join ({$this->current_invoice()}) as invoice "
                    . "on invoice.client = payment.client "
            //    
            ."WHERE "
                //Add the client parametrized constraint
                .($parametrized ? "payment.client =:driver ": "true ")
                //
                //Add the postage constraint, if requested. 
                . "and " . ($postage ? "payment.invoice is null " : "true ")                
                //
                //Ignore future payments, depending on whether we are
                //monitoring or invoiceing clients.
                ."and payment.date<='{$this->operational_cutoff()}' "
                
                //Only unposted bills are considered; be careful of the transaction
                //problem
                . "and not (".table::posted('payment').") "
                ,$parametrized
        );
}
    
    //Posting payments follows the unary psoting rules to the letter
    function post(){
        //
        //Unlike posting of auto-generated records, this one is simply an
        //update
        $this->query(
            "update "
                //
                //We need access to the storage table on which to perform the update
                ."payment "
                //
                //Use the generated records to drive the update without the
                //parematrized client constraint; the postage constraint is 
                //needed so that only unposted data are updated
                ."inner join ({$this->poster()}) as poster on "
                    //
                    //Ensure that poster and storage records match by matching
                    //their shared identifiers. Store and poster identifiers
                    //are the same, except the period. The inner join's on 
                    //expression has a form simialar to:-
                    . "payment.payment = poster.payment "
                //
                //Bring in the current invoice to link to the storage
                . " inner join ({$this->current_invoice()}) as invoice "
                    . "on payment.client = invoice.client "    
            ."set "
                //Now do the manual link to the invoice.        
                ."payment.invoice = invoice.invoice"        
        );
    }
   
    //Delink storage items from the current invoice.
    function unpost(){
        //
        //Update the storage, i.e., payment, table for this item
        $this->query(
            "update "
                //The table to update is the storage one
                ."payment "
                //
                //Using a natural join that uses the shared cleint field to 
                //match storage and current apply, if requested, the current
                //period constraint
                .($this->record->invoice->current 
                    ? "inner join ({$this->current_invoice()}) as invoice on "
                    . "payment.invoice = invoice.invoice "
                    : " ")
            ."set "
                //Nullify the invoice link
                ."payment.invoice = null "
                        
       ); 
    }
    
}
