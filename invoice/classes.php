<?php
namespace invoice;

//
//Require all item the files. Note the referencing, relative to the current 
//directory which explains why the project and the library are organized the
//way they are
require_once "../library/invoice/items/item.php";
require_once "../library/invoice/items/adjustment.php";
require_once "../library/invoice/items/closing_balance.php";
require_once "../library/invoice/items/opening_balance.php";
require_once "../library/invoice/items/payment.php";
require_once "../library/invoice/items/services.php";
require_once "../library/invoice/items/item_invoice.php";
//require_once '../library/invoice/items/loan.php';

//
//A layout is how the infomation on  page is presented. There are two different 
//kinds of layouts:-
//Tabular: Presenting the data in a table format.
//Label: Presenting the data in a label format.
abstract class layout {
    
    //Tabular layout should not show labels, as it is assumed that the labels 
    //are in the header. For labeled layout, they should.
    public $show_label;
    
    //Tags for describing a layout, modelled along a tabular layout
    public $table_tag;
    public $record_tag;
    public $field_tag;
    //
    //The following data may be set using open tags; see, e.g.,  
    //open_record($record)
    public $record;
    //
    public $invoice;    
    //
    function __construct($show_label, $table_tag, $record_tag, $field_tag) {
        
        $this->show_label = $show_label;
        
        $this->table_tag=$table_tag;
        $this->record_tag=$record_tag;
        $this->field_tag=$field_tag;
    }
    
    //
    //Displays the table tag (for a tabular lyout) and save the invoice
    //for further references
    function open_table(invoice $invoice){
        //
        //Save teh invoive for further references
        $this->invoice=$invoice;
        //
        //Open the table tag; the invoice can be used to supply data for setting
        //any attributes that are needed
        echo "<$this->table_tag>";
    }
    
    function close_table(){
        echo "</$this->table_tag>";
    }

    //
    //Display the open tag of this layout and set the record associated with this
    //layout for future use
    function open_record(record $record){
       //
        //Save the record so that we can make referenecs within it
        $this->record=$record;
        //
        //Get the name field assuming the driver_sql retrieved it
        $name = $record->result['name'];
        //
        //Show the open record tag. In future derived from the recird may
        //attributes may be added
        echo "<$this->record_tag class='record' id='$name'>";
    }
    
    function close_record(){
        echo "</$this->record_tag>";
    }

    
    //Show the item display, e.g., <td> for a tabular layout. 
    function open_item(item $item){
        //
        //Set the class for this opening tag
        //
        //Use the $item to define condition for displaying money
        $double =
            //
            //Invoie is not a money item
            $item->name!=='Invoice'
            //
            //Only the summarised case is considered
            && ($item->record->invoice->level=='summary'
                || $item->record->invoice->level=='gross');
        //
        //Define the double class
        $class = $double ? "class='double'": "";    
        //
        //Output the open item tag; its <td> for tabular cases, field for label
        //layouts
        echo "<$this->field_tag $class>";
    }
    
    //Close the item tag is shown, e.g., </td>
    function close_item(){
        echo "</$this->field_tag>";
    }
    
    //
    abstract function display_header();

    //Display this layouts given record
    abstract function display_record();

    //Show the given statement using this layout; we assume that teh statemet
    //already has some result.
    abstract function display_statement(statement $statement);
    
}

//The tabular layout is characterized by a header
class layout_tabular extends layout{
    
    function __construct() {
        //
        //Tabular layout should not show the labels (only the data as it is assumed
        //that teh labels are at the header)
        parent::__construct(false, "table", "tr", "td");
    } 
    //
    //Show the head tag.
    function show_tag_head($open = true) {
    }

    //Show the header tags for a tabular layout
    function display_header() {
        //
        //Echo the thead tag.
        echo "<thead>";
        //
        //Print the colspan tiile row.
        echo "<tr>";
        //
        //Show the name of each item.
        foreach ($this->invoice->record->items as $item) {
            //
            //Print the item name as the table heads.
            echo "<th>$item->name</th>";
        }
        //
        //Close the header row.
        echo "</tr>";
        //
        //Close the thead tag.
        echo "</thead>";
    }
    
    
    //Display the current record of this layout's invoice in a tabular 
    //fashion. In this case, the display is very simple; in other cases, it can
    //be quite complex, e.g., in the case of layout_mutall 
    public function display_record() {
        //
        //Display each item of the underlying record
        foreach ($this->record->items as $item) {
            //
            //Open the field tag
            $this->open_item($item);
            //
            //Now use the invoice's layout to display the item
            $item->display();
            //
            //Close the field tag
            $this->close_item();
        }
    }
    
    //Display the given statement (of an item) in a tabular fashion
    function display_statement(statement $statement) {
        //
        //Get the fields of the statement
        $fields = $statement->get_show_fields();
        //
        //Output the table tag.
        echo "<table class='statement'>";
        //
        //Output the table header.
        echo "<tr>";
        foreach ($fields as $fld) {
            //
            //Print the fields names.
            echo "<th>".ucfirst($fld->name)."</th>";
        }
        //
        //Close the header.
        echo "</tr>";
        //
        //Show the body
        foreach ($statement->results as $result) {
            //
            //Open the table row 
            echo "<tr>";
            //
            //Output the body of the table.
            foreach ($fields as $fld) {
                //
                //Get the value
                $value = $result[$fld->name];
                //
                //Format it.
                $fvalue = $fld->format($value); 
                //
                //Show it, using the field's CSS style. 
                echo "<td $fld->style>" .$fvalue . "</td>";
            }
            //
            //Close teh table row
            echo "</tr>";
        }
        //
        //Output the summary row
        //
        //Get the column span of the closing row
        $span = count($fields)-1;
        //
        //Only cases where the span is greater than 1 need be considered
        $colspan = $span>1 ? "colspan=$span": "";
        //
        //Output the last row
        echo 
        "<tr>"
        . "<td $colspan>"
            . "Total"
        . "</td>"
        . "<td class='double'>"
        . number_format($statement->item->statements['summary']->results[0][0], 0, ".", ",")
        . "</td>"
        ."</tr>";        
        //
        //Close the table tag
        echo "</table>";
    }

    //
    //Shows the footer of the page. If any is requred.
    function display_footer($open = true) {
        //
        //Check for the status of the open varible.
        if ($open) {
            //
            //Open the footer tag.
            echo "<footer>";
        } else {
            //
            //Close the footer tag.
            echo "</footer>";
        }
    }

}

//The label layout uses puts a label before its value, for every field in a 
//record. It us a singleton class
class layout_label extends layout{
    
    function __construct() {
        //
        //A labeled layout should show her labels
        parent::__construct(true, "report", "div", "field");
    }
    
 
    //Label layouts have no header
    function display_header() {}
    
    //Show the given record in a label layout. Ths method is not nteresting to 
    //implement, but needs to be don to make label_layout non-abstract
    function display_record(){
        throw new \Exception("This method is not defined");
    }
    
    //Display an items driver statement in a label format. We assume that the 
    //item's result is only one record. Multiple records are best shown in a 
    //tabular fashion
    function display_statement(statement $statement) {
        //
        //Get the item's only record to display
        $result = $statement->results[0];
        //
        $fields = $statement->get_show_fields();
        //
        $hidden = count($fields)==1 ? "hidden=true": "";
        //
        //Show the fields in a flexi display
        echo "<div class='fields'>";
        //
        //Step through all the fields of this items result 
        foreach ($fields as $field) {
            //
            //Get the field name
            $fname = $field->name;
            //
            //Get the field value
            $value = $result[$fname];
            //
            //Format the value
            $fvalue = $field->format($value);
            //
            echo 
            "<p class='field'>"
                //
                //Not the nonbreakng space after field name
                ."<span $hidden class='fname'>".ucfirst($fname).":&nbsp;</span>"
                ."<span $field->style class='fvalue'>"
                    //
                    //Show the formated value
                    . $fvalue
                 ."</span>"
            //Note the space after the <p> tag to invite a wordwrap point.        
            . "</p> ";
            
        }
        //
        //Close the flexi display
        echo "</div>";
        
    }
    
}
    

class dbase extends \PDO {

    //
    //The properties of this class are:-
    public $username;
    public $password = "";
    public $dbname;

    //The tables of this database 
    public $tables;
    //
    public $error;
    //
    //The constructor method to be called each time an instance of this class in made.
    public function __construct($username, $password, $dbname) {
        //
        //Set the dsn properties.
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
        //
        //Set the dsn.
        $dsn = "mysql:host=localhost;dbname=$dbname";
        //
        //Parent constructor take a data source name.
        parent::__construct($dsn, $username, $password);
        //
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        //
        //Set the tables of this tabalase
        $this->tables = $this->get_tables();
    }
    
    //Use th informatio scemea to retrive the tables of this database
    function get_tables(){
        //
        //Get the table names
        $results = $this->query(
            "select "
                . "table_name "
            . "from "
                . "information_schema.tables "
            . "where "
                . "table_schema = '$this->dbname'"    
        );
        //
        //Cerat an empty list of tables
        $tables = [];
        //
        //Retrieve the results an insert the indexed tables
        while($result = $results->fetch()){
            //
            //Get the table name
            $tname = $result[0];        
            //
            $tables[$tname] = new table($tname);
        }
        //
        //return teh tables
        return $tables;
    }

    //
    //Use the given paameters to check this sql. The returnrd value is the same
    //sql. The default is that tehere are no parameters
    public function chk(string $sql, Array $parameters=[]) {
        //
        //A prepared pdo statment throws no exception even if it has errors
        $stmt = parent::prepare($sql);
        //
        //We have to execute the query for pdo to throw exception if the
        //prepared statement has errors
        //
        try {
            //
            //Use the parameters to execute the query
            $stmt->execute($parameters);
            //
            $stmt->closeCursor();
            //
        } catch (\Exception $ex) {
            //
            //Rethrow the exception
            throw new $ex;
        }
        //
        //Return the same sql as the input
        return $sql;
    }

    //
    //Returns a result from a sql stmt.
    public function query($sql, ?int $fetchMode = null, mixed ...$fetchModeArgs) {
        //
        try {
            $result = parent::query($sql);
        } catch (\Exception $e) {
            //
            //Re-throw the exception
            throw $e;
        }
        //
        return $result;
    }

    //
    //Returns a result from a sql stmt.
    public function prepare($sql, $option = null) {
        //
        //
        //Check the sql
        $stmt = parent::prepare($sql);
        //
        //Check for errors
        if (!$stmt) {
            throw new \Exception($this->error . "<br/>$sql");
        }
        //
        //Return the perapered statement
        return $stmt;
    }

}

//Modellig the mutall_rental database on the development serve
class dbase_dev_mutall_rental extends dbase {

    public function __construct() {
        //
        $username = "mutallde";
        $password = "mutalldatamanagers";
        $dbname = "mutallde_rental";
        //
        parent::__construct($username, $password, $dbname);
    }

}


//Modelling eureka waters databse on the local server
class dbase_local_eureka_waters extends dbase {

    //
    public function __construct() {
        //
        $username = "mutallco";
        $password = "mutall_techub";
        $dbname = "eureka_waters";
        //
        parent::__construct($username, $password, $dbname);
    }

}

//Modelling the tables of this database. For now, the clas does not have much
//use, other than helping to identify primary and foreign key fieds
class table{
    //
    public $name;
    //
    function __construct($tname){
        $this->name = $tname;
    }
    
    //
    //Returns a condition to select posted table records disregarding those from
    //the current period, since when we begin posting, noting in the current 
    //period is assumed posted
    static function posted($tname){
        //
        //A reading is partially posted if:-
        $partially_posted = 
            //
            //There is a current invoice
            "invoice.invoice is not null "
            //
            //that is linked to the reading    
            . "and $tname.invoice = invoice.invoice ";    
        //
        //A table record is posted if:-
        $posted =
            //    
            //it is linked to an invoice
             "$tname.invoice is not null "
            //
            //...and not partially posted
            ."and not ($partially_posted)";
        //
        //Retrn the posted condtiopn; if if you want unposted, you simply 
        //negate this version
        return $posted;
    }

}

//
//These file are required to support the PHPMailer lass
require_once "../library/PHPMailer-master/src/PHPMailer.php";
require_once "../library/PHPMailer-master/src/Exception.php";
require_once "../library/PHPMailer-master/src/SMTP.php";

//The emailer class in the invoice namespace is an extension of the PHPMailer 
//version
class emailer extends \PHPMailer\PHPMailer\PHPMailer {
    //
    //
    function __construct() {
        //
        //Construct a PHPMailer and yes, throw exceptions
        parent::__construct(true);
        //
        //These are the initializations that extends the PHPMailer.
        //
        //Tell PHPMailer to use SMTP
        $this->isSMTP();
        //
        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $this->SMTPDebug = 0;
        //
        //Set the hostname of the mail server
        $this->Host = 'smtp.gmail.com';
        //
        // use
        // $mail->Host = gethostbyname('smtp.gmail.com');
        // if your network does not support SMTP over IPv6
        // 
        //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        $this->Port = 587;
        //
        //Set the encryption system to use - ssl (deprecated) or tls
        $this->SMTPSecure = 'tls';
        //
        //Whether to use SMTP authentication
        $this->SMTPAuth = true;
        //
        //Username to use for SMTP authentication - use full email address for gmail
        $this->Username = "mutallcompany@gmail.com";
        //
        //Password to use for SMTP authentication
        $this->Password = "mutall01";
        //
        //Set who the message is to be sent from. The system picks the name but ovverides
        //the username with the one specified above; so, it does not matter what you
        //type but it has to be a valid email address
        $this->setFrom('mutallcompany@gmail.com', 'Mutall Investment Co. Ltd');
    }
}

//A page is associated with a database.
abstract class page {
    //
    //A page has a Dbase associated with it.
    public $dbase;
    //
    //The constructor method. class by each instance.
    public function __construct() {
        //
        //Set the database that drives this page
        $this->dbase = $this->get_dbase();
    }
    
    //Returns the database to use for this page. Users must provde their own 
    //version of this method
    abstract function get_dbase();
    
    //Display any sql from the unrelying database
    function display_sql($sql){
        //
        //Get theh query statement
        $statement = $this->dbase->query($sql);
        //
        //Get the number of fields in the query
        $nfields = $statement->columnCount();
        //
        echo "<table>"; 
        //
        //Output the header
        echo "<tr>";
        //
        for($i=0; $i<$nfields; $i++){
            //
            $column = $statement->getColumnMeta($i);
            //
            echo "<th>".$column['name']."</th>";
        }
        echo "</tr>";
        //
        //Output the body
        while($result = $statement->fetch()){
            //
            echo "<tr>";
            //
            for($i=0; $i<$nfields; $i++){
                echo "<td>".$result[$i]."</td>";
            }
            echo "</tr>";
            
        }
        echo "</table>"; 
    }
     
    //
    //Try bind arg, tries to bind the reference variable, $value, and a matching 
    //property of this page, to the same value as it has. If it has a null then we 
    //look for a value in the super global variables $_GET AND $_POST under the 
    //given name. It returns false if the value is not found.
    static function try_bind_arg(page $page, $name, &$value = null, $validate = FILTER_DEFAULT) {
        //
        //Checking of the value has been passed.if so we set it as the value 
        //of the criteria.
        if (!is_null($value)) {
            //
            //Set the named property of the given page to the value argument.
            $page->$name = $value;
            //    
        } elseif (!empty($_POST[$name])) {
            //
            //Set the named property of the given page to the post global variable
            $value = filter_var($_POST[$name], $validate);
            $page->$name = $value;
            //    
        } elseif (!empty($_GET[$name])) {
            //
            //Set the named property of the given page to the get global variable
            $value = filter_var($_GET[$name], $validate);
            $page->$name = $value;
            //
        } else {
            //
            //We Return a false.
            return false;
        }
        return TRUE;
    }

    //
    //The Bind Arg method throws exception when the argument cannot be set
    static function bind_arg(page $page, $name, &$value = null, $validate = FILTER_DEFAULT) {
        //
        //Test for the try bind arg is it returns a false we through a 
        //new exception.
        if (!page::try_bind_arg($page, $name, $value, $validate = FILTER_DEFAULT)) {
            //
            //Throw a new exception with a massege.
            throw new \Exception("No argument value passed for argument " . $name);
        }
    }
    
    //Execute the requested method from the requested class, or display the home
    //page. This method was evoked from teh given basedir, thus helping us to 
    //track where we came from
    static function index($basedir){
        //
        //Enable error reporting for all types
        error_reporting(E_ALL);
        //
        //Switch on the errors for this production version
        ini_set('display_error', '1');
        //
        //Trap any exceptions
        try{
            //
            //
            if(!empty($_REQUEST['class'])){
                //
                //Set the class name.
                $classname = $_REQUEST['class'];
            }else{
                //
                //Redirect to the home page
                header('location: home.php');
                exit();
            }
            //
            //Add the namespace to the method
            $namespaced_class = __NAMESPACE__."\\".$classname;
            //
            //Make a instance of the class.
            $class = new $namespaced_class();
            //
            //Pass the base directory to the class object; this is important for
            //accessing local resources. The email->MsgHTML uses this directoty to
            //resolve relative addresses
            $class->basedir = $basedir;
            //
            //Set the method fom the global post or get variables
            if (!empty($_REQUEST['method'])){
                //
                $method = $_REQUEST['method'];
            }else{
               //
               //There must be a method.
               throw new \Exception('Method not found');
            }
            //
            //Determine if styling using a css is required for the output or not
            if (!empty($_REQUEST['css'])){
                //
                //Get teh CSS file
                $cssfile = $_REQUEST['css'];
                //
                //Use the file for styling output
                ?>

                <!-- 
                The html section is needed to support CSS from an external file -->
                <html>
                    <head>

                        <!--Keep title short and unique, so that this page can be identified
                        even when there are many open cases-->
                        <title>Invoice</title>

                        <link 
                            id="invoice.css" 
                            rel="stylesheet" 
                            type="text/css" 
                            href='<?php echo $cssfile;?>'>

                    </head>

                    <body>
                        <?php
                        //Call the desired method
                        $class->$method();
                        ?>

                    </body>
                </html>
            <?php
            //
            //Styling is not required
            }else{
                //
                //Call the raw method.
                $class->$method();
            }
        }
        catch(\Exception $ex){
            echo $ex->getMessage();
        }
    }

}

//
abstract class invoice extends page {
    //
    //An invoice can present data before or after it is posted. A poster 
    //invoice presents data before it is is posted; a report presents it after
    //posting
    public $posted;
    //
    public $basedir;
    //
    public $postage;
    //
    public $current;
    //
    //Constants for converting credit and debit balances to amounts
    //
    //Credit adjustments decreases teh client's balance
    const credit = -1;
    //
    //The converse is true for debits
    const debit = 1;
    
    //An invoice is used for 2 purposes
    //a) monitoring the clients' performance during the the invoice month
    //b) informing clients about their obligations at the begining of the invoice 
    //month
    //Puspose b) should be repeatable, even even more data has come in during the
    //invoie month. Hence this property. When true, the invoive will include data
    //capture after the posting; if false, it excluded it. 
    public $monitor;
    //
    //The email sending support object derived from phpMail. Usere will override
    //propertes of the emailer to customize who, e.g., to sene emails to.
    public $emailer;
    //
    public $record;
    //
    public $layout;
    //
    public $level;
    //
    public $primarykey;
         
    //An invoice is characterised by:-
    //- Whether we are reporting data before or after it is posted? This in turn 
    //helps us to pick which sql of an item to use, detailed_poster or detailed 
    //report.If before, then the $posted is set to false; otherwise it is set 
    //to true.
    //-A selection criteria which limits the size of the invoice
    function __construct($posted, $criteria=null) {
        //
        $this->posted = $posted;
        //
        //The crierial for filtering data is optional
        page::try_bind_arg($this, 'criteria', $criteria);
        //
        //Construct the parent before anything else for the purpose of 
        //using method and properties in the construction of the child.
        parent::__construct();
        //
        //Each invoice has a record; set it here using an abstract function 
        //because we cannot instantiate a record, which is abstract.
        $this->record = $this->get_record();
        //
        //Create the emailer object (for this invoice), an extension of 
        //PHPMailer. Specific classes will re-est the default emailer properties
        //to customize them. E.g., poster_mutall ads CC's phylis and wycliffe
        $this->emailer = new emailer();
        
    }
    
    //Initialize this invoice with data from multiple sources, including
    //the arguements
    function initialize($layout=null, $level=null, $monitor=null){
        //
        //Bind the arguments to invoice properties
        page::bind_arg($this, 'layout', $layout);
        page::bind_arg($this, 'level', $level);
        //
        //Invoice for monitoring is optional; by default it is false
        if (!page::try_bind_arg($this, 'monitor', $monitor, FILTER_VALIDATE_BOOLEAN)){
            $this->monitor=false;
        }
        //
        //Change the layout name to the equivalent object
        //Let $obj be the desired layout object, complete with namespacing
        $obj = __NAMESPACE__."\\".$layout; 
        //
        $this->layout = new $obj();
        //
        //Prepare statements for all the items
        foreach($this->record->items as $item){
            //
            //Prepare detailed, summary and gross statements of the item to provide 
            //data for a parametrized client (or invoice) displays. 
            $item->prepare_statements();
        }
         //
        //Override the fact that prev_value and curr_value in the detailed statement
        //of water item are not money fields
        $this->record->items['water']->statements['detailed']->fields['prev_value']->is_money = false;
        $this->record->items['water']->statements['detailed']->fields['curr_value']->is_money = false;
        $this->record->items['water']->statements['detailed']->fields['consumption']->is_money = false;
    }
    
    //Display a complete invoice, guided by the folllowing arguments:-
    //- The general layout of the invoice, tabular or label
    //- The index (of the detail level) to the item statemenet to use for 
    //reporting, i.e., detailed, summary, gross, etc.
    //-Is the report used for monitoring clients aor invoiciing them
    function display($layout=null, $level=null, $monitor=null) {//invoice
        //
        //Initialize this invoice with data from multiple sources, including the
        //given arguments
        $this->initialize($layout, $level, $monitor);
        //
        //Retrieve the data that drives the display
        $results = $this->query();
        //
        //Now show the data, modelled along a tabular layout
        //
        //Open the main report tag, e.g., <table> for a tabular layout. 
        $this->layout->open_table($this);
        //
        //Show the header of this report; this is relevant for tabular layouts only.
        $this->layout->display_header();
        //
        //Step thorugh the driver table records and display each one of them.
        while ($result = $results->fetch()) {
            //
            //Populate the this invoice's record with data to be displayed
            $this->record->populate($result);
            //
            //Display this invoice's record in the required (invoice) layout and 
            //item detail
            $this->record->display();
        }
        //
        //Output the the totals for each item only in cases where the display is tabular
//        if (get_class($this->layout)!== 'invoice\layout_mutall'){
//            //
//            //
//            $this->display_gross();
//        }
        //
        //Close the main report tag, e.g., </table>
        $this->layout->close_table();
    }
    
//    function display_gross() {//invoice
//        //
//        //Initialize this invoice with data from multiple sources, including the
//        //given arguments
//        $this->initialize('layout_tabular', 'gross', null);
//        //
//        //Retrieve the data that drives the display
//        $results = $this->query();
//        //
//        //Now show the data, modelled along a tabular layout
//        //
//        //
//        $result = $results->fetch();
//        //
//        //Populate the this invoice's record with data to be displayed
//        $this->record->populate($result);
//        //
//        //Display this invoice's record in the required (invoice) layout and 
//        //item detail
//        $this->record->display();
//    }
    
    
    //Send this invoice as emails of to clients. The emailer property is set 
    //during creatipn of the invoice
    function email(layout $layout=null, $level=null, $monitor=null){
        //
        //Initialize this invoice with data from multiple sources, including the
        //given arguments
        $this->initialize($layout, $level, $monitor);
        //
        //Retrieve the data that drives the email list
        $results = $this->query();
        //
        //Loop through all the results, outputting each one of them as an email
        while ($result = $results->fetch()) {
            //
            //Populate the invoice record with the data to email
            $this->record->populate($result);
            //
            //Email the record
            $this->record->email();
        }
    }
    
    
    //Query this invoice
    function query(){
    //        
        //Retrieve the data that drives the display
        //
        //Get the sql that drives this invoice.
        $sql = $this->get_driver_sql();
        //
        //Query the database for results.
        $results = $this->dbase->query($sql);
        //
        return $results;
    }
   
    
    //Returns the structure of the record that is used for driving an invoice.
    //Different websites must implement their own versions of a record. For 
    //mutall needs electricity and water items; eureka_waters need water only. 
    //Sidai has a different structure, etc. Mutall has impleented the 
    //record_mutall locally.
    abstract function get_record();
   
    //Returns the sql that drives this invoice report
    abstract function get_driver_sql();
    
    //Returns the name of the css file for styling outputs from this invoice
    //such as the poster display or email
    //The file:-
    //- can be specfied be the user from the browser through a request
    //-is named invoice.css in the website directory
    function get_cssfile(){
        //
        if (!empty($_REQUEST['css'])){
            return $_REQUEST['css'];
        }else{
            return 'invoice.css';
        }
        
    }
}

//A record models a single row in an invoice page that is laid out in a
//tabular fashion. 
abstract class record {
    //
    //Primary key of the that is associated with this record. 
    public $primarykey;
    //
    //The invoice. Either poster or report will be the parent at any creation.
    public $invoice;
    //
    //A record has one or may items. The item is the specific report element.
    public $items = [];
    //
    public $result;
    
    public function __construct(invoice $invoice) {
        //
        //Set the invoice.
        $this->invoice = $invoice;
        //
        //Defined the items in the order of display. Set the initial items, 
        //i.e., invoive and opeing balances
        $this->items = [
            'invoice' => new item_invoice($this),
            //'month' => new item_month($this),
            'opening_balance' => new item_opening_balance($this)
            ];
        //
        //Set the user defined items
        foreach($this->get_udf_items($this) as $key=>$item){
            $this->items[$key]=$item;
        }
        //
        //Set the items needed for balance calculations
        foreach([
            'payment' => new item_payment($this),
            'debit' => new item_debit($this),
            'credit' => new item_credit($this),
            'services' => new item_service($this)
        ] as $key=>$item){
            //
            $this->items[$key]=$item;
        }
        //
        //Insert closing balance as the lst item
        $this->items['closing_balance'] = new item_closing_balance($this);
    }
   
    //Set teh user defiend items
    abstract function get_udf_items();
    
    //Fill the record items with data
    function populate($result){
        //
        $this->result = $result;
        //
        //Set the primarykey of the table row for further use. The 
        //client/invoice parameter in the items sql is bounf from this key
        $this->invoice->primarykey = $result['primarykey'];
        //
        //Fill the record with data, item by item
        foreach($this->items as $item){
            //
            //Attend to both the detailed and summary, and gross statements of an item 
            foreach(['detailed', 'summary', 'gross'] as $level){
                //
                //Get the mutall statetement
                $statement = $item->statements[$level];
                //
                //Excute the statement (with a bound primarykey)
                $statement->execute();
                //
                //Fetch the statement's data 
                $results = $statement->stmt->fetchAll();
                //
                //Save the results internally for further refereces 
                $statement->results = $results;
            }        
        }
        //
        return;
    }
    
    //Returns the arrears amount for this record for use in preparing clients'
    //announcements
    function get_arrears(){
        //
        //The arrears amount is defiend as the sum of teh following summary 
        //item names
        $names = [
            'opening_balance',
            'payment',
            'credit',
            'debit',
            'loan'
        ];
        //
        //Start with no sum
        $sum=0;
        //
        //Sum all the named (arrears) items
        foreach($names as $name){
            //
            //Only available data is considered
            if (isset($this->items[$name]->statements['summary']->results[0][0])){
                //
                //Do teh acculumation
                $sum = $sum + $this->items[$name]->statements['summary']->results[0][0];
            }
        }
        //
        return $sum;
    }

    //
    //Returns the reference number for reporting as:-
    //(a) an email subject and  
    //(b) invoice header
    function get_ref(){
        //
        //Get the first recrord of the detailed satement of this record's invoice item 
        $rec = $this->items['invoice']->statements['detailed']->results[0];
        //
        //Return the two pieces of of data: document type and its reference number
        return "{$rec['id']}-{$rec['year']}-{$rec['month']}";
    }
    
    //Display this record according to the invoice's layout request
    function display(){//record
        //
        //Get the underlying layout
        $layout = $this->invoice->layout;
        //
        //Determine if the record needs to be displayed or not
        //
        //It does. Do it.
        //
        //Open the record (tag)
        $layout->open_record($this);
        //
        //Display the record; this can be a simple tabuler or label lauyout...or
        //a much ore complex layout, e.g., layout_mutall
        $layout->display_record();
        //
        //Close the record (tag)
        $layout->close_record();
    }
    
    //Compile the complete email; then send it (using PhpMailer)
    function email(){
        //
        //Get the emailer from the record's invoice
        $emailer = $this->invoice->emailer;
        //
        //Initialize the address list. This will remove all To addresses; 
        //the CC and BC will not be cleared.
        $emailer->ClearAddresses();
        //
        //The email is sent to the client's email address. This means that the 
        //record's driver data must return an email.
        $address = $this->result['email'];
        $emailer->addAddress($address);
        //
        //The subject is invoice/statement for the current period -- the same bit
        //that appears in the invoice header
        $emailer->Subject = $this->get_ref() ;
        //
        //The message comes from the display of the record. Collect the message
        //data
        //
        //Start output buffering
        ob_start();
        //
        //Display the invoice
        $this->display();
        //
        //Get the output and stop buffering
        $info = ob_get_clean();
        //
        //Compile the message as a complete HTML report
        $message = "
        <html>
            <head>
              <style>"
             //
             //The style comes from the mutall_rental.css. This is a shared
             //resource,   
            . file_get_contents($this->invoice->get_cssfile())
            //
            ."</style>
            </head>
            <body>
                $info
            </body>
        </html>
        ";
        //
        //Initialize the message body by converting the given html into plain text 
        //alternative body and referenced images to embedded versions. The 
        //base directory used for resolving relative paths to images is...
        $emailer->msgHTML($message, $this->invoice->basedir);
        //
        //
        //send the message, check for errors
        if (!$emailer->send()) {
            echo "Mailer Error: " . $emailer->ErrorInfo;
        } else {
            echo "$emailer->Subject sent to $address<br/>";
        }
    }
}

//A type of invoice. The driver table for this type of invoice is the client table.
//A poster invoice is reponcible for the calculation of poster items. 
//These are items that have not yet be reported on.
//Poster is now abstract because its users have to implement these methids:-
//a) the get dbase method. Now there is no default database value.
//b) the get record method
abstract class poster extends invoice {
    //
    //A poster has a month an year in which it is being posted.
    public $month;
    public $year;
    //
    public $postage;
    
    //Are we unposting for current period only, or for the entire database?
    //If set to true, its for the current period; otherwise its for entier database
    //The default is true
    public $current;
    //
    public $criteria;
    
    //The arguments of a poster constructor:
    //-Period of the posting , i.e., year and month -- which is optional; the 
    //  default is the current period
    //-The postage constraint, i.e., should posted items be displayed or not.
    //  When set to false, all data, whether posted or not, will be displayed, 
    //  allowing us to compare data before and after posting.  When set to true
    //  posted data will not be shown; allowing us to identify posted cases.
    public function __construct($year = null, $month = null, $postage=null) {
        //
        //If necessary, set the optional year...
        if (!page::try_bind_arg ($this, 'year', $year, FILTER_VALIDATE_INT)){
            //
            //...as the 4 digit version of teh curnt date
            $this->year = date('Y');
        }
        //
        //If necessary, set the optional month ..
        if (!page::try_bind_arg ($this, 'month', $month, FILTER_VALIDATE_INT)){
            //
            //...as the numeric version (without a leading 0) of the current date 
            $this->month = date('n');
        }
        //
        //Postage is optional; 
        if (!page::try_bind_arg($this, 'postage', $postage, FILTER_VALIDATE_BOOLEAN)){
            //
            //It defaults to true, i.e, apply the postage constraint, so that
            //posted data are hidden
            $this->postage = true;
        }
        //
        //A poster invoice presents data that has not been posted. 
        //This is important for selecting the driver sql 
        parent::__construct(false);
    }
    
    //By default, executing a recoord displays it using te underlying layout
    function execute_record(){
        //
        $this->layout->display_record($this->record);
    }
    
    //The n'th cutoff date. When n=0, its the cutoff of the current period; n=1
    //that of the next period; n=2 that of the next period, etc.; n=-1 that
    //of the previous period, etc.
    function cutoff($n=0){
        //
        //Let $dayn be the first day of the n'th period
        //
        //Let $day0 be the first day of the current period.
        $day0 = "$this->year-$this->month-01";
        //
        //Get the sign of the requested period
        $sign = $n<0 ? "-": "+";
        //
        //Formulate string expression of $dayn, e.g. '2019-02-3 + 4 month'
        $dayn = $day0." ".$sign." ".abs($n)." month"; 
        //
        //Convert the english string expresion to a Unix time value
        $time = strtotime($dayn);
        //
        //Format the time, to get the last day of $dayn
        $cutoff = date('Y-m-t', $time);
        //
        return $cutoff;
    }

    //Returns the sql that drives the poster invoice. It is bases based  on 
    //clients for whom there is an agreement
    function get_driver_sql() {//poster
        //
        //Consider only non teromated agreemsnte
        $terminated = 
            "agreement.terminated is not null "
            . "and agreement.terminated <'{$this->cutoff()}'";
        //
        //When an agreement is valid when it is not terminated and it is marked
        //as valid (which by default it is)
        $valid = " not ($terminated) and agreement.valid";
        //
        //The filtering criteria
        $criteria = isset($this->criteria) ? "and $this->criteria": "";
        //
        return $this->dbase->chk(
            //
            //Merge agreements for separate rooms for a single glient
            "select distinct "
                //
                //The driver has expects a primary key field
                . "client.client as primarykey, "
                //
                //For attributing record tags to implement Camilus search engine
                . "client.name as name, "
                //
                //We needa access to whether clients pay quaterly or monthly in 
                //order to compute arrears
                 . "client.quarterly, "
                //
                //For emailing clients. Replace this with the client's email 
                //when ready
                . "if(client.email is null, 'info@mutall.co.ke', client.email) as email "
            . "from "
                . "client "
                //
                //Filter to see only those clients with agreements
                . "inner join agreement "
                    . "on agreement.client = client.client "
            //
            . "where "
                //The agreement have to be valid. E.g., Lovinah's agreement 
                //is not valid
                . "$valid "
                //
                //Add the filtering criteria
                ."$criteria "
            . "order by client.name"     
        );
    }
    
    //Check if data has been posted or not. This is important to ensure that 
    //data is not posted twice
    function already_posted(){
        //
        //Set the next month. It is from the next month that we can note whether
        //data has been posted or not. 
        //Posted data only exist if the next month has an opening balance 
        //which is the saved closing balance of this month. 
        //Data is unposted if the next month in question has no opening balance 
        //which is the saved closing balance of the previous month
        $cuttoff_date=$this->record->invoice->cutoff(1);
        
        //
        //Formulate query for checking if there are any closing balances posted. 
        $exit = $this->dbase->chk(
            "Select "
                //
                //All posted data have a clossing balance
                . "closing_balance.amount "
            . "From "
                //
                //Select from the closing balance (because every posting must
                //have it.
                . "closing_balance "
                //
                //We need the invoice and the period to ensure we have 
                //the desired invoice and periods
                . "inner join invoice on closing_balance.invoice = invoice.invoice "
                //
                //To suppot test of balances in the period after current
                . "inner join period on invoice.period=period.period "
            . "where "
                //Set the desired period from the invoice class
                . "period.month= month('{$cuttoff_date}') "
                //Set the year from the invoice class
                . "and period.year=year('{$cuttoff_date}') "
        );
        //
        //If any results are provide the data has been posted.
        $data = $this->dbase->query($exit);
        //
        //Fetch all results
        $results=$data->fetch();
        //
        //Check if there is any data 
        if($results){
            return true;
        }
    }
    //Post the current invoice's items to the database
    function post() {
        //
        //If data is posted throw new exception
        if($this->already_posted()){
            throw new \Exception("Data Already posted, Please unpost to post again.");
        }
        //
        //post the closing balances because it's pre calculation is affected
        //by whether her dependents are posted or not.
        $this->record->items['closing_balance']->post();
        //
        //Post all the other items teh natial order
        foreach ($this->record->items as $key=>$item) {
            //
            //Exclued the closing balance.
            if($key !== 'closing_balance'){
              $item->post();
            }
        }
        //
        echo "Ok";
    }
    
    
    //Undo the postings of the current period, or those of the entire database
    //if the $current option is set to true
    function unpost($current = null) {
        //
        //if there is no data to unpost throw new exception
        if(!$this->already_posted()){
            throw new \Exception("No Data to Unpost, Please post to unpost again.");
        }
        //
        //Bind the optional $current argument; it boolean. 
        if (!page::try_bind_arg ($this, 'current', $current, FILTER_VALIDATE_BOOLEAN)){
            //
            //The unposting of an item is driven by this parent settong
             $this->current = true;  
        }
        //
        //Unpost all items, except the invoice -- which comes last due to 
        //integrity issues
        foreach ($this->record->items as $key => $item) {
            //
            //Skip the invoice; it wil be unposted last
            if ($key != 'invoice') {
                //
                //unpost any other item
                $item->unpost();
            }
        }
        //
        //Finally unpost the invoice
        $this->record->items['invoice']->unpost();
        //
        //Indicate when everything has run as expected
        echo "Ok";
    }

    
}

//A report invoice is a type of invoice that reports posted items.
//The driver table for this is the invoice table.
abstract class report extends invoice {

    //
    //The properties of  report are:-
    //The criteria for section of the reporting data.
    public $criteria;
    //
    public $order_by;

    //
    //The constructor's critaria and order by clauses are optional.
    function __construct() {
        //
        //A poster invoice presents data that has been posted. 
        //This is important for selecting the driver sql 
        parent::__construct(true);
    }
    
    //Returns an sql statement, based on this invoice,  that drives this report
    function get_driver_sql() {//report
        //
        return $this->dbase->chk(
            "select "
                . "invoice.invoice as primarykey, "
                //
                //For attributing record tags to implement Camilus search engine
                . "client.name "
                
            . "from "
                //
                //The table driving this process 
                . "invoice "
                //
                //To suppport critaria referencing client and period
                . "inner join client on invoice.client = client.client "
                . "inner join period on invoice.period = period.period "
            //
            //Add the where clause -- if necssary    
            .(isset($this->criteria) ? "where $this->criteria ": "") 
            .(isset($this->order_by) ? "order by $this->order_by": "")
        );
    }

}

//
abstract class report_statement extends report {
    //
    //The constructor method.
    function __construct() {
        //
        //A statement presents data that has been posted
        parent::__construct(true);
    }

}

//Reports a summarised version of the normal report in a tabular form. Users 
//implement thier own version by defining the get_record() abstract method
abstract class report_schedule extends report {
    //
    function __construct() {
        //
        parent::__construct();
    }

}

//
abstract class report_sms extends report {

    //
    //The constructor method.
    function __construct() {
        //
        //Construct the parent.
        parent::__construct();
    }

}

//Modelling a custom statement for driving an invoice
class statement{
    //
    public $item;
    //
    //The PDO::statement 
    public $stmt;
    //
    //The sql used for preparing this statement
    public $sql;
    //
    public $parametrized;
    //
    public $results;
    //
    public $fields;
    //
    //Indicate if teh sql is paremetrized or not. This will help in determining
    //whether to add a dummy paremeter or not, during tests
    //
    //Change this sql to string to support lawrance sql statements.
    //Is the sql parametrized or not
    function __construct(item $item, $sql, bool $parametrized){
        //
        $this->item = $item;
        $this->sql = $sql;
        $this->parametrized = $parametrized;
        //
        //Retrieve the item's database
        $dbase = $item->record->invoice->dbase;
        //
        //Prepare the statement
        $this->stmt = $dbase->prepare($sql);
        //
        //Collects the fields, as stdclasses, from executing this items sql
        $this->fields = $this->get_mutall_fields();
    }
    
    //Returns standard class fields for this item by  executing its sql
    function get_mutall_fields() {
        //
        //Add a dummy parameter if necessary
        $param = $this->parametrized ? [':driver' => 'dummy']: [];
        //
        //Excute the stmt to obtain the field count. Use a dummy driver
        $this->stmt->execute($param);
        //
        //Init the field collection.
        $fields = [];
        //
        //Get the number of column in the PDO statement.
        $column_count = $this->stmt->columnCount();
        //
        //Loop through the columns of the result.
        foreach (range(0, $column_count - 1) as $index) {
            //
            $column = $this->stmt->getColumnMeta($index);
            //
            //Get teh field name
            $fname = $column['name'];
            //
            //Get the field type
            $type = $column['native_type'];
            //
            //Get the length of the field.
            $len = $column['len'];
            //
            //Create a mutall field
            $mutall_field = new field($this, $fname, $type, $len);
            //
            //Use the indexed arrey to hold the mutall field
            $fields[$fname] = $mutall_field;
        }
        //
        //Return the field.
        return $fields;
    }

    //
    //Prepare the given statement using the given sql. Create, from the prpared
    //statement, the fields of the requested type, where type is either detailed 
    //summary
    function set_fields(){
        //
    }
    
    //Display this statement's data depending on whether its results yields 
    //a single, multiple or no record
    function display(){//statement    
        //
        //Count the no. of records in this statement's results
        $norecs = count($this->results);
        //
        //If the results is empty, do nothing
        if ($norecs==0){
            //
            //Display nothing
            return;
        }
        //If there is only one record, then use then... 
        elseif ($norecs == 1){
            //
            //use the label format to display the result...
            $local_layout = new layout_label();
        }else{
            //
            //..otherwise (for multiple records) use the tabular layout
            $local_layout = new layout_tabular();
        }
        //
        //Open the item as a block with 1% top magin 
        echo "<div class='item'>";
        //
        //Output the item's name as a label -- if the global layout is indeed
        //label
        $global_layout = $this->item->record->invoice->layout;
        //
        if ($global_layout->show_label){
            //
            //Show the item name as a block with color green
            echo "<p class='name'>"
            . $this->item->title
            . "</p>";
        }
        //
        //Use the layout to show the results of the selected statement
        $local_layout->display_statement($this);
        //
        //Close the item tag
        echo "</div>";
    }
    
    //
    //Executing the statement
    function execute(){
        //
        //Get the primary key of client or invoice driver
        $primarykey = $this->item->record->invoice->primarykey;
        //
        //Get the execution paramaters
        $params = $this->parametrized ? [":driver"=>$primarykey]: [];
        //
        //Execute the statement
        try{
            //What is this returned result type? 
            $result = $this->stmt->execute($params);
            return $result;
        }catch(\Exception $ex){
            throw $ex->getMessage();
        }    
    }
    
    //Returns all the fields to display, i.e., thoses not marked as hidden.
    //Primary and foreign key fields are marked as hidden
    function get_show_fields(){
        //
        return  array_filter($this->fields, function($field){
            //
            //Filter out the no-show cases.
            return $field->is_shown;
        });
    }
    
    
}

//A field is a key value pair. A name and a Value.
class field {
    //
    //The properties of a field are.
    //The field name
    public $name;
    //
    //The current field value.
    public $value;
    //
    //The parent item. The item is field's home.
    public $item;
    //
    //A field has a data type, e.g., string, real or boolean
    public $data_type;
    //
    public $length;
    //
    public $stmt;
    //
    public $style;
    
    //The data type constants
    const int  = "LONGLONG";
    const str = "VAR_STRING";
    const double = "DOUBLE";
    const date = "DATE";
    
    //Set this to true if the field is to be displayed. 
    public $is_shown;
    
    //By default all fields of double type are considerd as money fields. 
    //The user can ovrride this fact when this is not the case
    public $is_money;
    
    //The constructor method parameters are obtained a query field metadata.
    function __construct($stmt, $name, $data_type, $len) {
        //
        $this->name = $name;
        $this->data_type = $data_type;
        $this->length = $len;
        $this->stmt = $stmt;
        //
        //By default all fields will be displayed. Primary and foreign key 
        //fields will not be shown.
        $this->is_shown = $this->get_is_shown();
        //
        $this->style = $this->get_style();
        //
        //By default all fields of double type are considerd as money fields. 
        //The usr can ovrride this fact when this is not the case
        $this->is_money = $data_type==self::double ? true: false;
    }
    
    //Retuens the style to show for a field
    function get_style(){
        //
        //By default, there is no styling
        $style = "";
        //
        //Doubles are right aligned
        if ($this->data_type ===self::double){
            //
            $style = "class = 'double'";    
        }
        //
        return $style;
        
    }
    
    //Returns true if the field is to be shonw; false otherwise. Attributes are
    //shown; primary and foreign key fields are not.
    function get_is_shown(){
        //
        //Get the containing database
        $dbase = $this->stmt->item->record->invoice->dbase;
        //
        //If the field name match the name of an existing table, then, by 
        //mutall naming rule, it is not an attribute.
        if (key_exists($this->name, $dbase->tables)){
            //
            //The key is either primary or foreign. Do not show
            return false;
            
        }//
        //Remove teh posted field
        elseif($this->name=='posted'){
            return false;
        }
        else{
            //The key is an attribute. Show it
            return true;
        }
    }
    
    //Format the given value for this field
    function format($value){
        //
        //Start with the original (unformated) value
        $fvalue = $value;
        //
        //Null values are not formated
        if (empty($value)){
            //do nothing
        }
        //
        //Format all moneys no decimal point and a comma thousand 
        //separator.
        elseif($this->is_money){
            $fvalue = number_format($fvalue, 0, ".", ",");
        }
        //
        //Other real numbers, e.g., water meter readings are formated with 2 
        //places of decimal and no comma separator
        elseif($this->data_type == self::double ){
            $fvalue = number_format($fvalue, 2, ".", "");
        }
        //
        return $fvalue;
    }
}
