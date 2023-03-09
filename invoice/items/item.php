<?php
namespace invoice;

//The item class is designed to support posting, unposting, reporting and 
//editing of rental charges. The data to post must be derived from some entity
//in the data model, called a driver. To post means saving the data to to some
//other entity or entoties, called storages, or modifying the existing one. 
//When the driver and the storage are the same, then posting invoves only one 
//table. Such an item is referrerd to as unary; binary when one driver and one storage
//are need, and unclassified if more than 1 storage is involged, e.g., 
//opening_balance
abstract class item {

    //
    //The record that is the parent structure of this item. This allows 
    //access to data for this item's sibblings and ancestors
    public $record;
    //
    //The entit thet drives the genration of data to be posted
    public $driver;
    //
    //Name of this item, used in headers and other situations where a valid
    //name is required -- hence respect for naming rules, e.g. all lower case
    //and underbar for separating name components
    public $name;
    //
    //The more descriptive name of an item
    public $title;
    //
    //Indicates if the item takes part in the computing of closing balance or 
    //not. For instance, the client is simply a left margin marker; the closing
    //balance item, usually appearing as a right margin of a tabular invoice, 
    //cannot be used for calculating itself -- so these are aesthetic items. 
    public $aesthetic;
    
    //Indicates if this item credits the clients balance or not. By default it
    //does not; only payments and credits do
    public $is_credit = false;
    //
    //Statements for this item; currently 2 are expected--detailed and summary.
    //A 3rd one, gross, has been added(C. Odhiambo, 2019). These statements are
    //constructed during display of the invoice.
    public $statements=[];
    //
    public $dbase;
    //
    public $advance;
    //
    public $storage;
    //
    //
    //An item is charaterized by its parent records and driver entity. 
    //For instance, the water item is driven by water connection, 
    //rent by agreement, invoice by client, etc. The title is a more descriptive
    //name
    public function __construct(record $record, $driver, $title) {
        //
        //Set the parent record of the item, so that we can access sibblings and
        //ancestral data.
        $this->record = $record;
        //
        //Driver is the starting point of the posting process
        $this->driver = $driver;
        //
        $this->title = $title;
        //
        //The name of the item is used in report headings. It is derived from this
        //item's class name, after stripping off the item_ prefix and capitalizing
        //the first letter.
        //
        //Get the non-namespaced class of the item
        $reflect = new \ReflectionClass($this);
        $name = $reflect->getShortName();
        //
        $this->name = ucfirst(str_replace("item_", "", $name));
        //
        //By default, every item is is needed for computing closing balances.
        //Those not used as such ar edscribed as aesthetic cases because the used
        //to appear on the exrime right and left of the inoice dsplay. Now we
        //know that theu can appear anywhere. Invoice andclosing balance were
        //initially tthe only known cases. Then came auto and manual balances 
        //which are used for calculating opening balances
        $this->aesthetic = false;
        //
        //Define a shortcut for the item's databse.
        $this->dbase = $this->record->invoice->dbase;
        //
        //All payments and expences are in arrears, except rent and services 
        //which are paid in advance
        $this->advance = false;
    }
    
    //Prepare the statements of this item, one for detailed reporting, the other
    //for summaries -- depending on whether the underlying invoice is for posted
    //or unposted data
    function prepare_statements(){
        //
        //Let $suffix be the suffix of the function name, detailed_poster() or 
        //detailed_report, that returns the sql for reporting on posted
        //or unposted data.
        $suffix = $this->record->invoice->posted ? "report": "poster";
        //
        //Prepare the detailed satement
        //
        //Formulate the name of the sql to execute to retrieve the details
        //(or summaries) of the item, e.g., detailed_poster, summary_report,
        //e.t.c.
        $fname = "detailed_".$suffix;
        //
        //Get the sql, ensuring that it is parametrized (with a :driver) and 
        //that the postage constraint is consistent with the requested one 
        $sql =  $this->record->invoice->posted 
            //
            //Posted data data do not need a postage constraint
            ? $this->$fname(true)
            //
            //Unposted data does need postage
            : $this->$fname(true, $this->record->invoice->postage);
        
        //
        //Set the detailed statement using the parameterized sql
        $this->statements["detailed"] = new statement($this, $sql, true);
        //
        //Now prepare the summary statement with parametrization on.
        $this->statements["summary"] = new statement($this, $this->summary(true), true);
        //
        //Set the totals of the statements with no parameters.
        $this->statements["gross"] = new statement($this, $this->gross(false), false);
    }
    
    
    //Display the data of an item -- depending on the invoice's layout 
    //specification 
    function display(){//item
        //
        //Do not show items paid in advance, e.g., rent, charges, etc., if they 
        //are not due, i.e., premature.
        if ($this->advance){
            //
            //First test if there is any posted data
            if (!isset($this->record->items['rent']->statements['detailed']->results[0])){
                //
                //No rent data found in a rental system. This may or may not
                //be an error. Its not an error if you are looking at unposted data
                return;
            }
             //
            //An advance-paid item is due if the rent of the record has a non-null 
            //factor; otherwise it is premature
            $premature = is_null($this->record->items['rent']->statements['detailed']->results[0]['factor']);
           //
            //Skip display of premature advance payment items
            if ($premature){ 
                return;
            }
        }
        //
        //Get the level of detail to show for this item
        $level = $this->record->invoice->level;
        //
        //Let $s be the statement to work on
        $s = $this->statements[$level];
        //
        //Display the statement
        $s->display();
    }

    //Returns the sql for the reporting invoices using the storage tables
    //The key difference between this and detailed_poster is that the
    //former is driven by the invoice table; the latter by the client table.
    //
    //This method will work for all items because of the following facts:-
    //a) every item has a matching storage table
    //b) every posted data is linked to the its associated invoice
    //c) all posted fields of any item can be accessed through a wild card 
    //operator
    function detailed_report(bool $parametrized) {
        //
        return $this->chk(
            //    
            "select "
                //Select all (*) the fields from the storage. We will have to figure
                //out how to throw out primary and foreign key fields from the
                //user reports
                . "$this->storage.* "
            . "from "
            //
            //The storage table drives the reporting
                . "$this->storage "
            . "where "
            //
            //Add the invoice constraint, if needed.
            . ($parametrized ? "$this->storage.invoice =:driver " : "true ")
            ,$parametrized
        );
    }

    //Returns the summary sql depending on the detailed one
    function summary(bool $parametrized) {
        //
        //Get the detailed sql
        $posted = $this->record->invoice->posted ? "report": "poster";
        //
        $fname = "detailed_$posted";
        //
        return $this->chk(
            //    
            "select "
                //
                //The summary field is called value; by default it is based on 
                //the amount column of the detailed sql
                . "sum(detailed.amount) as value "
                //Select 
                . ""
            . "from "
                //
                //Ensure that the postage constraint is ommitted; i.e., there 
                //is none
                . "({$this->$fname($parametrized, false)}) as detailed "
            ,$parametrized
        );
    }
    
    
    //Return the total depending on the summary one
    function gross(bool $parametrized) {
        //
        return $this->chk(
            //    
            "select "
                //
                //The sum of the summarized detailed value as amount
                . "sum(summary.value) as amount "
            . "from "
                //
                //The totals are not set to any condition. The gross is only for 
                //finding the sum of every items for all records. As such, it is not
                //parameterized. We therefore set the paramatrized condition on
                //the summary to false
                . "({$this->summary(false, false)}) as summary "
            ,$parametrized
        );
    }
    

    //Shortcut for $this->query. The design is to ensure that the the reporet
    //erroe is as close as possible to the query that raised it
    function query($sql) {
        //
        //Check the sql
        try {
            //
            $stmt = $this->record->invoice->dbase->query($sql);
        } catch (\Exception $ex) {
            //
            //Re-throw the exception
            throw $ex;
        }
        //
        //Return the sql statement
        return $stmt;
    }

    //Prapere and execute the given query to check it for correctness. Add a 
    //:driver='test' parameter if the sql has a parameter; the default is that
    //it does not
    function chk(string $sql, bool $parametrized=false) {
        //
        //Prepare the sql statement
        $stmt = $this->record->invoice->dbase->prepare($sql);
        //
        //Only when we execite teh sql can we tell if it has an error or not
        try {
            //
            //Set the driver parameter, if necessary
            $param = $parametrized ? [':driver' => 'test'] : []; 
            //
            //Bind the client parameter to the test value and excute it
            $stmt->execute($param);
            //
            //The statement is no longer useful
            $stmt->closeCursor();
            //
        } catch (\Exception $ex) {
            //
            //Re-throw the exception
            throw $ex;
        }
        //
        //Return the same sql as the input
        return $sql;
    }
    
    //Write the necessary changes to the database to effect a posting. The 
    //general approach is to:-
    //- Save a storage record, if necessary, linked to the current invoice
    //- Link all the associated time variant entities to the invoice. 
    abstract function post();

    //Returns an sql for reporting  all posted records of this item in the current
    //period. This is used for testing of unposted items using a left join. 
    //Currently, this method works for only for the closing balance because of
    //its simple identifir, closing_balance(invoice). For all the other items
    //the set of unique identifies is more complex than this. They will need
    //to override the method. They are, for example:-.
    //
    // - electricity(invoice, eaccount)
    // - service(invoice, servive_type) 
    // - rent(invoice, agreement)
    // - payment(client, ref)
    // - adjustment(client, reason)
    // - opening_balance(client, date)
    //At the momemnt, more work needs to be done on this -- so every every item
    function posted_items() {
        //
        return $this->chk(
            "SELECT "
                //
                //The primary key, to be renamed storage, for carrying out the 
                //existence test, e.g., storage.storage is not null
                . "$this->storage.*, "
                //
                //To test for existence of a stored items in the current 
                //period, we need all identifiers except invoice, for 
                //fomulating a left join to the driver.
                //. "$this->key_storage_identifiers "
                . "invoice.client "
            . "FROM "
                //
                //The source of stored items
                . "$this->storage "
                //
                //Filter only closing balance from the current period
                . "inner join ({$this->current_invoice()}) as invoice "
                //
                //The driver must suppply a client
                . "on $this->storage.invoice = invoice.invoice "
        );
    }

    //To unpost is to undo a posting, for re-posting purposes. For auto-generated
    //items it means removing them from their storage tables; for manual cases, 
    //it means de-linking them from the invoice that posted them. Unppsting
    //items for current period only or those of the entire database is set at 
    //the invoice level
    abstract function unpost();

    //Returns the sql for a detailed poster of this item. This sql is used for:-
    //a) displaying the items to be posted and
    //b) posting them.
    //When used in (a) the clienet parametrization is need; in (b) it is not 
    //needed.
    //When the postage constranit is set to true, posted data will not be 
    //displayed
    abstract function detailed_poster(bool $parametrized, bool $postage);
    
    //Returns the sql for posting this item. This depends on 2 other sql's:-
    //a) the poster's driver and   
    //b)the detailed poster.
    //This function ensures that only records returned by the driver are posted
    function poster(bool $postage){
        //
        //Get the sql used for driving the poster display
        $driver = $this->record->invoice->get_driver_sql();
        //
        //Get the sql for deriving the data to be displayed. As we will use this
        //sql for CRUD operations we don't need the client parametrized constraint
        //The postage constration may be important for other database systems, 
        //say, postgresql, but not for mysql due to the availability of the 
        //'on duplicate key update' extension. To suport this general position, 
        //apply the poster criteria 
        $detailed = $this->detailed_poster(false, $postage);
        //
        //Now formulate the poster sql, noting that the client in the $detailed 
        //should be matched to the primary key field of the $driver
        //client is the primary key
        $sql = $this->record->invoice->dbase->chk(
            "select "
                . "detailed.* "
            . "from "
                . "($driver) as driver "
                . "inner join ($detailed) as detailed "
                . "on driver.primarykey = detailed.client"    
        );
        //
        //Ruturn the sql
        return $sql;
    }
    
    //Returns an sql that identifies the current invoice. This query is 
    //repeatedly used for posting items. A call to this method is valid only 
    //after the current invoice record has been inserted. 
    function current_invoice() {
        //
        return $this->chk(
            "select "
                //
                //The primary key is needed
                . "invoice, "
                //
                //The user will have to bind the client, inorder fo the 
                //current invoice to be be fully defined. This is important for
                //posting purposes
                . "invoice.client, "
                //
                . "invoice.period "
            . "from "
                . "invoice "
                //
                //The invoice must also match current period
                . "inner join ({$this->current_period()}) as period on "
                    . "invoice.period = period.period "
       );                
    }

    //Returns an sql that identifies the current period. This query is 
    //repeatedly used for posting items. A call to this method is valid only 
    //after the current period record has been inserted.
    function current_period() {
        //
        $sql = $this->chk(
            "select "
                //
                //Select all the period fields
                . "period.* "
                //    
            . "from "
                . "period "
            . "where "
                //Constrain the period to the current year and month OF THE ITEM      
                . "period.year = year('{$this->cutoff()}') "
                . "and period.month=month('{$this->cutoff()}')"
            ,false    
        );
        //        
        //        
        return $sql;
    }
    
    //
    //Returns the cutoff period of this item. 
    //By default, rent and service charges /are charged in advance, so their 
    //cutoff period is $n. Expense are from previous period, so their 
    //cutoff period is $n-1.  Closing balance is associated with the next period, 
    //i.e., $n+1
    function cutoff($n=0){
        //
        return $this->record->invoice->cutoff($n);
    }
    
    //
    //Does this item take part in a monitor report? If so, its cutoff needs
    //to be adjusted, so that data for the invoice period are included or excluded
    function operational_cutoff($n=0){
        //
        //For monitoring purposes, include new data after posting; otherwise 
        //exclude it, by setting the cutoff date to that of the previous period
        $x = $this->record->invoice->monitor ? $n: $n-1;
        //
        return $this->record->invoice->cutoff($x);
    }
    
    
}


//This class models manually generated items, e.g., opening balance, payment 
//and adjustment. These items:-
//a) always drive the poster sql
//b) are posted by simply establishing a link to the invoice
//c) are unposted by nullifying the link
//d) must be dated, so that only cases below cuoff are considered
//e) the driver and the storage tables are the same.
//Manuually generated items are unposted by simply delinking them fro the invoice
//used to post them
//This class needs 1 posting operand, the driver, which  also serves as the storage
abstract class item_unary extends item_binary {
    //
    public $messages;

    //
    public function __construct(record $record, $driver, $title) {
        //
        //The storage of unary item is the same as its driver
        parent::__construct($record, $driver, $driver, $title);
    }

    //Identifies what manual data for this item is considered valid for 
    //displaying and posting in the current period
    function detailed_poster(bool $parametrized, bool $postage) {
        return $this->chk(
        "select "
            //
            //List the message fields needed for monthly communicating to 
            //the user monthly using an invoice report. The massage cannot be 
            //empty; otherwise it would invalidate validates the last comma
            . "$this->messages, "
            //
            //The client foreign key field , needed for calculating 
            //closing balances by grouping. 
            . "driver.client, "
            //
            //In the unary case where drier and storage are the same, we
            //need the driver's primary key for matching driver to the 
            //during storage during update
            . "driver.$this->driver "
        . " from "
            //
            //The table (virtual or real) that drives this sql; its the same
            //as the storage table        
            . "$this->driver as driver "
        . "where "
            //Apply the client parametrized constraint, if requested        
            . ($parametrized ? "driver.client = :driver " : "true ")
            //
            //Add the postage constraint, if requested. 
            . "and " . ($postage ? "storage.invoice is null " : "true ")
            //
            //Ignore future items
            . "and driver.date<='{$this->cutoff()}'"
            ,$parametrized
        );
    }

    //Post all current unary items by linking them to the current invoice.
    function post() {
        //
        //Unlike posting of binary items, which requires creating new records, 
        //this one is simply an update operation
        $this->query(
            "update "
                //
                //We need access to the storage table on which to perform the 
                //update. 
                . "$this->storage "
                //
                //Use the generated records to drive the update without the
                //parametrized client constraint. The postage constraint is 
                //needed so that only unposted data are updated.. This may be
                //important for other databases, e.g., postgresql, but not for
                //mysql which uses 'on duplicate key' clause.
                . "inner join ({$this->poster()}) as poster on "
                    //
                    //In the unary situation the driver and storage are matched
                    //using the primary keys
                    . "poster.$this->storage = $this->storage.$this->storage "
                //
                //Bring in the current invoice to link to the storage
                . " inner join ({$this->current_invoice()}) as invoice "
                    //
                    //using the client as the link
                    . "on $this->storage.client = invoice.client "
            . "set "
                    //Now do the actual link to the invoice.        
                    . "$this->storage.invoice = invoice.invoice"
        );
    }

    //Delink storage itsms from the current invoice.
    //The opening balance has an extra table to update -- the post flag of the 
    //closing balance
    function unpost() {
        //
        //Update the storage table for this item
        $this->query(
            "update "
                //The table to update is the storage one
                . "$this->driver "
                //
                //Using a natural join that uses the shared cleint field to 
                //match storage and current apply, if requested, the current
                //period constraint
                .($this->record->invoice->current 
                    ? "inner join ({$this->current_invoice()}) as invoice on "
                        . "$this->driver.invoice = invoice.invoice " 
                    : " "
                 )
            . "set "
                //Nullify the invoice link
                . "$this->driver.invoice = null "
        );
    }

}

//A binary item is an one where the driver and storage are 2 differnt entities
//This class supports management of data that is automatically generated. Such 
//data can be wiped out without fear, as it can be re-created through posting.
abstract class item_binary extends item {
    //
    public $messages;
    //
    public $key_storage_identifiers;
    //
    public $driver_modidifiers;
    //
    public $on_storage_identifiers;
    //
    public $message_joins ;
    //
    public $currency_constraints;
    //
    public $update;

    //
    //A binary item has one storage entity where the charges are posted
    public $storage;

    //A binary item requirs 4 arguments:-
    //a) the parent record (which should b protected to avoid recursion during 
    //json deconding
    //b) the driver table of this item
    //c) the storage table; in the case of unary items the driver and the storage
    ////tables are one and the same thing
    //d) a more decriptive name of the item, fit for reporting purposes
    public function __construct(record $record, $driver, $storage, $title) {
        //
        $this->storage = $storage;
        //
        //By default, every item is used for calculating the closing balances.
        //Such items are know as aesthetic non-items; the only known cases of aesthetic
        //items are invoice and closing balance. The use of aesthetic items in a 
        //report is simply aesthetic.
        $this->aesthetic = false;
        //
        parent::__construct($record, $driver, $title);
    }

    //Returns the sql that is used for managing data to be posted in the current
    //period.  It is used in 2 ways:-
    //
    //(a) to display the data just before it is posted
    //(b) to implement the actual posting, thus changing the database by 
    //automatically creating new storage records or updating manually generated
    //cases.
    //
    //In case (a) the sql is executed for a very specific client, so, it contains
    //a client parameter so that we can program the record to be output the way 
    //we would wish. The parameter is not needed in case (b) since we use
    //the inbuilt features of the sql language to create, delete or update many 
    //database records at once.
    //
    //In addition, for display purposes, we do not wish to see posted data. 
    //
    //To support these 2 constraits, the method takes 2 boolean arguments
    //- $parametrized - to return a parametrized sql that constrains the result to 
    //  a specific client or invoice
    //- $postage - to select all postable records or just the unpostd ones
    function detailed_poster(bool $parametrized, bool $postage) {
        //
        return $this->chk(
        "select "
            //
            //List the message data fields needed for communicating to the user 
            //monthly through an invoice report, e.g., 
            //agreement.amount * driver.factor as amount.
            . $this->messages->data
            //
            //The client foreign key field , needed for :-
            //a) calculating closing balances by grouping
            //b) inner joining the generated data to current invoice 
            //  for posting purposes. 
            //c) left joining to support compound items, e.g.. opeing 
            //balances
            . "$this->driver.client, "
            //
            //Extra identifiers of the storage beyond client and period.
            //In driving the following keys, the user has to :- 
            //(a)be aware of which identfiers are in the messages 
            //(b)exclude the client field.
            //All storage identifiers, excluding period, are used for 
            //formulating the on expression use for testing if generated 
            //records are posted or not
            . "$this->key_storage_identifiers "
        . " from "
            //
            //The table (virtual, e.g. power, or real) that drives this sql
            . "$this->driver as driver, "
            //
            //Add support for modifyng (either rowwise or columnwise) that 
            //the appropriate data needed for deriving the user messages 
            //are correct. E.g.:-
            //- a join to the initial balance with the most recent unposted 
            //  date, 
            //- joins to the water connenction with the current and previous 
            //  water readings, etc.     
            . "$this->driver_modidifiers "
            //
            //Add support for the postage constraint by extending the driver
            //with the posted stored items. This subquery is driven by the 
            //storage items for the current period. This is important for
            //for autogeneated items
            . "left join ({$this->posted_items()}) as storage on"
            //
            //Fields for joining the driver to the postage constraint.
            //These are all the identification fields of the storage
            //table, excluding the period. It has the general format:-
            //
        //storage.f1 = driver.f1 
            //and storage.f2 = driver.f2 ... 
            //and storage.fk = driver.fk
            //
        //for the k identification fields. Be guided by the key 
            //storage identifies formulated by the key_storage_identifiers
            //during field selection
            . "on $this->on_storage_identifiers "
            //
            //Add extra joins for supporting the messages and constraints, 
            //if any. E.g., in the water item, we require a wmeter join to
            //access the serial number message.
            . "$this->message_joins "
        . "where "
            //Apply the client parametrized constraint, if requested        
            . ($parametrized ? "driver.client = :driver " : "true ")
            //
            //Add the postage constraint, if requested. This vald for
            //auto generatd items. for manual case it is simply
            //driver.invoice is not null    
            . "and " . ($postage ? "storage.storage is null " : "true ")
            //
            //Add the constraint for excluding future scenarios, e.g.,
            //ebill.date<='{$this->cutoff()}'    
            . "$this->currency_constraints" ,$parametrized
        );
    }

    //Posting binary items generally involves 3 operations:-
    //a) creating new records in the storage table
    //b) linking any time-variant entity, a.k.a., the reference, used in 
    //calculating the posted record, to the current invoice.
    //c( linking all unposted entities, similar to the invoice and dated below
    //the reference, to the current invoice
    function post() {
        //
        return $this->query(
                        //
                        //Create the records to be posted...
                        "insert into "
                        //     
                        //..in the storage table for this item. 
                        . "$this->storage ("
                        //
                        //Specify the message field namess. If there are some, the step
                        //should insert a trailing comma because insert willl always need
                        //the torage identifies that follow. This process always adds a
                        //which spece to allow joins to teh next output
                        . $this->messages->names
                        //
                        //Specify all the storage identifier names, except those found in
                        //the messages. There must be at least one, identification key
                        //field storage.
                        . $this->storage->identifiers->names
                        . ")"
                        //
                        // Select ...
                        . "(select "
                        // 
                        //The poster fields to match the desired messages supply the data.
                        //The comma will be ignored if there are no message datas 
                        . $this->messages->data
                        //
                        //List the data sources of all the storage identifiers, except invoice
                        //These must all be supplied by teh poster.
                        . $this->storage->identifiers->data
                        //
                        //The (current) invoice primary key is needed by all storage
                        //tables
                        . "invoice.invoice "
                        . "from "
                        //Data come from this items's poster sql, with the following 
                        //conditions:-
                        //
                 //a) No parametrized client constraint is needed for posting the generated,
                        //because database CRUD operations are designed to work with multiple
                        //records.
                        //
                 //b) Only unposted data is considerd, so, the postage constraint needs to 
                        //be set. We will be using an insert statement, so existing records should
                        //be excluded
                        . "{$this->poster()} as poster "
                        //
                        //Bring in current invoice table. This is not a loose join as we
                        //need to match driver and poster clients. At this point, we assume 
                        //it that the current invoice is already posted.
                        . "inner join {$this->current_invoice()} as invoice on "
                        //
                        //The invoice and the poster are linked by client. This is
                        //one of the reasons why client must be a poster field. The
                        //other reason has do to with summarizing closing balance
                        . "invoice.client = poster.client) "
                        //
                        //The fields to update aare all fields of this storage 
                        //except the indentifiers. If every storage field is an
                        //an identifier, then pick just one dummy
                        . "on duplicate key update $this->update"
        );
    }

    //Returns the sql that helps us to test if a auto-generated item exists
    //in the storage table for the current periods. It returns:-
    //a) the primary key to the item, to be used in a null test
    //b) identification fields to be used in a left join a poster driver in 
    //order to isolate null cases. 
    function posted_items() {
        //
        return $this->chk(
            //    
            "select "
            //
            //Fields used to fully identify a storage, excluding invoice.period, 
            // and client
            . "invoice.client. "
            . $this->storage->idenfiers
            . "from "
            //
            //The driver for tis item, e.g., service, rent, electricity,
            //water, closing_balane, etc
            . "$this->storage "
            //
            //The storrage invoice must be the same as the current one, so 
            //that this query returns the posted cases for the current period
            . "inner join ({$this->current_invoice()}) as invoice on "
            . "$this->storage.invoice = invoice.invoice) "
        );
    }

    //Delete the storage items that match the current invoice from the this item's
    //storage table.
    function unpost() {
        //
        //For debugging. Allow us to inpect the sql
        $sql = null;
        //
        //This general routine is used by may items. By trapping any errors, we
        //can provide a better error message that tells us what item we were 
        //unposting when this happened
        try{
            //
            //Now delete the item from its storage, catching any errors i order to 
            //identify this offending item. First formulate teh query, so that we 
            //can check it
            $sql ="delete "
                    //
                    //The storage table to delete from
                    . "$this->storage.* "
                . "from "
                    //The joins needed to isolate records of the current period
                    //
                    . "$this->storage "
                    //
                    //Apply the current period constraint. Note the use of invoice2,
                    //alias just in case we are deleting from invoice table itself
                    .(
                        $this->record->invoice->current 
                        ? "inner join ({$this->current_invoice()}) as invoice2 on "
                            . "$this->storage.invoice = invoice2.invoice " 
                        : " "
                    );
            //            
            $this->query($sql);
        }
        catch(\Exception $ex){
            //
            //Add the item's name to the exception message and re-throw
            throw new \Exception("Item=$this->name. {$ex->getMessage()}");
        }
    }

}
