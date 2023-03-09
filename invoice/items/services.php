<?php
namespace invoice;

//This class supports management of chargeable, monthly services that are not 
//covered by rental, water or electricity charges. Identifying and delivering 
//these services should be a core business of a rental system to promote client
//satisfaction. The current services for Mutall include:-
//
//. security
//. gabbage collection.
//
//The following servics are in the planning stages:-
// 
//. IT consultations
//. Stand-by power supply
//
//The model to support these and future services comprises of the following 
//entities:-
//. service(name*, price, auto)
//. subscription(service*, client*, factor)
//. charge(servive*, period*, amount) 
//
//Notes:-
//(i) The cost of a service is calculated in 2 ways:- 
//
//a) charge = subscription.factor * service_type.price or
//b) charge = service_type.price
//
//depending on whether the servce is automatic or not.
//Formula a) is suplied if the service is subscribed. This method is used to levy
//non-standard charges to selected clients
//Formula be is used if a service is automatic and ahas not been subscribed.
//subscriibed.
//If a service is not automatic and has not been subscribed, e.g., water charges
//then other criteria must be used for calculating the charge. That becomes a new
//item that is driven by client, usses charge as the storage and uses water meter
//connection to pick out shich clents to charge.
//
//The subscription factor is estimated by a joint assessment when a client is
//registered, but can be altered during rent life when the service usage is 
//better estimated. This fact is used to adjust charges on after the 3rd year of 
//client occupancy
//
//Service is a binay item because it is pposting it involves 2 tables: the client
//as the driver and the charge as the storage for the posted erecords.
class item_service extends item_binary {
    //
    public function __construct($record) {
        //
        parent::__construct($record,  "client", "charge", "Service Charge");
        //
        //Services are paid in advance, when rent is due
        $this->advance=true;
    }
    
    //Returns the sql that helps us to test if a service (service) subscription 
    //has posted or not for the current period and client.
    function posted_items(){
        //
        return $this->chk(
            "SELECT "
                //We will require a field for left joining to the subscription
                //system
                ."charge.*,  "
                ."invoice.client "
            ."FROM "
                //
                //poster services are saved in a matching table; this is true 
                //for all items
                ."charge "
                //
                //invoice links service to period
                ."inner join ({$this->current_invoice()}) as invoice on "
                    . "charge.invoice = invoice.invoice "
                
        );
    }
    
    //Returns the least staart date of a client's agreement
    function agreement(){
        return $this->chk(
            "select "
                . "agreement.client, "
                . "min(agreement.start_date) as start_date "
            . "from "
                . "agreement "
            . "group by "
                . "agreement.client"
        );        
    }    
    
    //Returns the all the unclasified charges associated with unstructured 
    //services. These charges are based on intuition and client/tenant agreement
    //In contrast Water, electricity and rent are charges drived from some raw 
    //inputs.
    function detailed_poster(bool $parametrized, bool $postage){
        //
        //There are 2 ways of calcluating service charges
        //
        //a) when a client has subscribed to a service
        $ca = "subscription.subscription is not null ";
        $a ="subscription.amount ";
        //
        //b when there is no subscription and the service is automatically charged
        $cb = "subscription.subscription is null and service.auto "; 
        $b = "service.price ";
        //
        //When non of the conditions apply, the service charge is not applied
        $price = "if($ca ,$a , if($cb, $b, null))";
        //
        //The fall of a quarter occurs when..
        $fall = "((month(agreement.start_date) - {$this->record->invoice->month}) %3) = 0";
        //
        $quarterly = "if ($fall, 3, null)";
        //
        //Monthly clients have a payment factor is 1
        $factor = "if(client.quarterly,  $quarterly, 1)";
        //
        $amount = "$price * $factor";
        //
        return $this->chk(
            "SELECT "
                //Client Messages to report:- 
                    ."service.name, "
                    //
                    //The amount to be charged
                    ."$price as price, "
                    //
                    //1 for monthly and 3 for a queterly client that is due
                    ."$factor as factor, "
                    //
                    //The amount to be charged
                    ."$amount as amount, "
                    //
                    //Keys needed for supporting this binary item
                    //
                    //used for posting purposes
                    ."client.client,  "
                    //
                    //keys for storage identifiers, beyond client and period
                    ."service.service "
            ."FROM "
                //
                //The driver for this service is client
                ."client "
                //
                //Add support for deriving client messages; its a lose join  
                    //
                    ."join service "
                    //
                    //Add support for testing subscription
                ."left join subscription on "
                    ."subscription.service = service.service "
                    . "and subscription.client = client.client " 
                //
                //Add support for the postage constraint by extending the driver
                //with the posted stored items. This subquery is driven by the 
                //storage items for the current period. This is important for
                //for autogeneated items
                . "left join ({$this->posted_items()}) as charge on "
                    //
                    //Fields for joining the driver to the postage constraint.
                    //These are all the identification fields of the storage
                    //table, excluding the period. It has the format
                    //storage.f1 = driver.f1 and storage.f2 = driver.f2 ... and storage.fk = driver.fk
                    //for the k identification fields
                    . "charge.service = service.service "
                    . "and charge.client = client.client "
                //
                //Add the clients agreement, so that we can access the start date
                . "left join ({$this->agreement()}) as agreement on "
                   . "agreement.client = client.client "
            ."where "
                //        
                //Apply the client parametrized constraint, if requested        
                . ($parametrized ? "client.client = :driver ": "true ")
                //
                //Add the postage constraint, if requested. 
                . "and " . ($postage ? "charge.charge is null " : "true ")
                //
                . "and ($amount) is not null"
                //
                //Exclude future contracts
                ." and agreement.start_date>={$this->record->invoice->cutoff()} ",
                $parametrized        
        );
    }
    
    //Posting of servivces follow the genaral procedure for autogeneratd itsms
    function post(){
        //
        return $this->query(
           //
           //auto generated records are alwas created for the storage table      
           "insert into "
                
                //The storage table is used for holding the posted data. The
                //fields of interest are teh user message
                . "charge ("
                    //specifify teh user messages
                    . "name, price, factor, amount, "
                
                    //Specify the charge identification field  
                    . "invoice, service "
                . ") "
                //
                // the poster sql to identify date to be posted
                . "(select "
                    //
                    //match the user messages 
                    . "poster.name, poster.price, poster.factor, poster.amount, "
                    //
                    //match teh storage identifiers
                    . "invoice.invoice, poster.service "
                . "from "
                    //
                    //No parametrization and Duplicates will be taken care of
                    . "({$this->poster()}) as poster "
                    //
                    //Bring in current invoice details
                    . "inner join ({$this->current_invoice()}) as invoice on "
                        //
                       //Reason no. 2 for including client the generated data
                       . "invoice.client = poster.client) "
                            . "on duplicate key update "
                            . "name=values(name), "
                            . "amount=values(amount)"     
        );        
    }
        
}
