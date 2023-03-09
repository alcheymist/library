<?php
//
//All the classes below will be under the capure namespace to prevent
//name collision with those of the root namespace
namespace{
//
//
//Resolve the schema reference which allows a questionnaire to log
//itself.
include_once  $_SERVER['DOCUMENT_ROOT'].'/library/v/enhance/schema.php';
//
//Resolve the join reference which allows table depths to be set
include_once  $_SERVER['DOCUMENT_ROOT'].'/library/v/enhance/sql.php';
//
//(This class extends the earlier defined record in order to export
//large amounts of data large typically generated from sources other 
//than direct human inputs -- thats a future thought)
class questionnaire extends schema{
    //
    //The inputs that need to be exported expressed in the questionnaire 
    //format 
    public array /*Iquestionnaire|excelfile*/$milk;
    //
    //When labels in a questionnaire are separated from tables the result
    //is unpacked into 2 containers:-artefacts and tables
    //
    //The indexed list of aliased entities, a.k.a., artefacts
    public \Ds\Map /*artefact[[entity, alias]]*/ $artefacts; 
    //
    //An array of milk tables, indexed by the table name
    public \Ds\Map /*<tname, table>*/ $tables;
    //
    //Allow this questionnaire to be accessible from anywhere
    static questionnaire $current; 
    //
    //A questionnaire is characaterised by milk (data) organized as such.
    function __construct(/*Iquestionnaire|excel_filename*/$milk){
        //
        //Standardise the inputs to an array of table or label layouts
        $this->milk = $milk;
        //
        //Initialize the indexed arrays
        $this->artefacts = new \Ds\Map();
        $this->tables = new \Ds\Map();
        //
        //We don't have a special way of identifying a qustionnaire because 
        //there is only one in the system -- unlike databases, entities, and 
        //other shema objects
        parent::__construct('_');
        //
        //Set this questionnaire as the current one to allow global access
        self::$current = $this;
        //
        //Initialize the current )and only) barrel to be usd for savebg
        //milk tables
        capture\barrel::$current = new \capture\barrel();
    }
    //
    //Exports the data referenced by this questionnare to the correct database
    //Technically, this process converts static forms of label and table layouts
    //into artefcats and active tables before saving them to a database. The 
    //by-products are either syntax errors or runtime results, packed as an Imala
    //structure.
    //Dfferent questionnaires may override this method with therir own. For
    //instance, Outlook may use this method to save data and return an Imala 
    //that is fit for updting a theme panel.
    //
    //Imala is formally defined in Typescript as either 
    //type mala = 
    //  
    //  ...a list of syntax errors...
    //  {class_name:'syntax', errors:Array<string>}
    //  
    //  ..or list of labeled expressions with summarised errors returned 
    //  from loading the tables
    //  |{  class_name:'runtime', 
    //      label_errors:Array<label, error>, 
    //      table_errors:Array<{tname:string, error:string, count:int}>
    //   }
    function load(string $logfile):array/*mala*/{
        //
        //1 Initialize the loggin process by creating a log file in 
        //the current app's directory
        $log = (log::$current = new \log($logfile));
        //
        //2. Compile the inputs to produce tables and artefacts
        $syntax_errors = $this->compile_inputs();
        //
        //To continue, there must be no syntax errors
        if (count($syntax_errors)>0) {
            //
            //Compile the errors into cleaner text
            $errors = array_map(fn($error)=>"$error", $syntax_errors);
            //
            //Convert the errors to text
            $msg = implode("; ", $errors);
            //
            return ['class_name'=>'syntax', 'errors'=>$msg]; 
        }
        //
        //2. Sort the artefacts by order ascending of dependency to simplify the 
        //the look of the error log. NB> Depency is a function of depth and the 
        //size of the alias
        $this->artefacts->sort(
            function($a, $b){
                //
                //If both the artefacts belong to the same entity...
                if ($a->source->name === $b->source->name){
                    //
                    //....then sort them based on the alias legths...
                    return count($a->alias) <=> count($b->alias);
                }
                //
                //...otherwise sort them based on the dependency depths
                return $a->source->depth<=>$b->source->depth;
            }            
        );
        //
        //Set the milk table name for each artefact if it the artefact depends 
        //on the table; null if none. This supports the export of table 
        //independent and table dependent artefacts separately.
        foreach($this->artefacts as $artefact){$artefact->set_tname(); }
        //
        //3.Save foreign key columns by binding their simplified forms to 
        //their corresponding primary keys for all the artefacts. Saving of
        //foreigners, unlike those of attributes and primary keys, is done once
        //irrespective of the numbers and sizes of the milk tables. This is the
        //best place to do it.
        foreach($this->artefacts as $artefact){$artefact->save_foreigners(); }
        //
        //Set the data capture queries (CRU) for all the artefacts
        foreach($this->artefacts as $artefact){ $artefact->set_statements();}
        //
        //4. Export the table-independent artefacts in a 2 phase process
        $label_errors = $this->export_ti_artefacts();
        //
        //5. Export the table-dependent artefacts. 
        $table_errors = $this->export_td_artefacts();
        
        //6. Close the log to save the results to an external file.
        $log->close();
        //
        //7. Compile the processed milk, Imala, from and runtime results
        //Compile and return the runtime mala
        return [
            'class_name'=>'runtime',
            //
            //Use the labeled errors as they are
            "label_errors"=>$label_errors,
            //
            //Use the simplified table errors
            "table_errors"=>$table_errors
        ]; 
    }
    
    //Export all the table-independent artefacts.
    private function export_ti_artefacts():array/*<{label, msg}*/{
        //
        //Get the table independent artefacts
        $artefacts = array_filter(
            //
            //Remember that the artefacts are managed as a map; they are the 
            //values. Convert the values to an array
            $this->artefacts->values()->toArray(),
            //
            //An artefact is independt of any tale if its tname property is null
            fn($artefact)=>is_null($artefact->tname)
        );
        //
        //Save them using no table row (due to independence) ...
        $this->export_artefacts($artefacts, null);
        //
        //...and return the erroneous artefacts
        //
        //Collect the errorneous aftefacts
        $error_artefacts = array_filter(
            $artefacts, 
            fn($artefact)=>$artefact->pk()->answer instanceof myerror);
       //
       //Simplify the errors for reporting purposes. For instance:
       //[label=>mutall_user.student[0], msg=>'Duplicates noot allowed']
       return array_map(
           fn($artefact)=>[
               "label"=>
                   $artefact->source->dbname
                   .".".$artefact->source->name
                   .".".json_encode($artefact->alias),
               "msg"=>$artefact->pk()->scalar
           ], 
           $error_artefacts
       );
    }
    
    //Export the table dependant artefacts. The way the errors are reported is 
    //different from how it is done in the table independent case.
    private function export_td_artefacts():array/*<{tname, error, count}>*/{
        //
        //Prepare to convert the indexed table-based errors 
        //whose format is a map<{tname, msg}, count> to a jsonanble 
        //object of the following array format.
        $array/*:Array<{tname, msg, count}>*/ = [];
        //
        //This process (unlike the table independent version) is driven by the 
        //tables collection
        foreach($this->tables as $table){
            //
            //Export the tables data.
            $table->save(null);
            //
            //Combine the indexing keys and error occurrences in a single object
            //and push them to an array
            foreach($table->map->keys() as $key){
                //
                //Compile the error in the desired format: {tname, msg, count}
                $array[] = [
                    //
                    //The table map's key is {tname, msg}
                    "tname"=>$key->tname,
                    "msg"=>$key->msg,
                    //
                    //The indexed value is the number of times that this error 
                    //message occurs in the table
                    "count"=>$table->map[$key]
                ];
            }
        }    
        //
        return $array;
    }
    
    //
    //Save the artefacts in 2 modes; first using the non-cross members, then 
    //using the cross member columns. This is a publcs function because it is 
    //required both for table dependent and independnet scenarios.
    public function export_artefacts(
        array/*<artefacts>*/ $artefacts,
        /*null|row*/$row    
    ):void{
        //
        //For each save mode...
        foreach([false, true] as $cross_member){
            //
            //...loop through every artefact and save it.
            foreach($artefacts as $artefact){
                //
                //The saving is based on the requested mode, cross or non-cross
                //members
                $artefact->save_cross_members = $cross_member;
                //
                //Save the artefact without any reference to a table row (because 
                //they are independent) 
                $artefact->save($row);
                //
                //For non-cross members, ensure that the relevant update query is 
                if ($cross_member) 
                    $artefact->update['cross_members']->execute();
            }
              
        }
    }
            
    //Compile the questionnare inputs into artefacts and tables, returning 
    //syntax errors if any.
    private function compile_inputs():array/*<myerror>*/{
        //
        //Standardise the questionnaire inputs to a stataic list of labels and
        //tables. In particular, referece to an Excel file is resolved to a 
        //questionnaire
        $layouts = $this->standardise_milk();
        //
        //Compile layouts and and tables separately, statring with the tables 
        //because layouts require them.
        //
        //Start with an empty list of syntax errors...
        $syntax_errors =  [];        
        //
        //Tables are presented as objects
        $tables = array_filter($layouts, fn($layout)=>is_object($layout));
        //
        //Compile the tables
        foreach($tables as $table){$this->compile_table($table, $syntax_errors); }
        //
        //Labels are presented as arrays
        $labels= array_filter($layouts, fn($layout)=>is_array($layout));
        //
        //Compile the labels
        foreach($labels as $label){$this->compile_label($label, $syntax_errors); }
        //
        //Return any syntax errors
        return $syntax_errors;
    }
    
    //
    //Converts the data held as a questionaire or as commented Excel file into 
    //the questionnaire format
    private function standardise_milk():array/*<label|table>*/{
        //
        //If the input data, i.e., milk, is already in the questionnaire 
        //format...
        if (is_array($this->milk)){
            //
            //...then return it as it is...
            return $this->milk;
        }
        //..otherwise it must a commented Excel Microsoft worbook
        elseif (is_string($filename=$this->milk)){
            //
            //Assume the input milk is a commented Microsoft Excel filename. 
            //It must exist.
            //
            //Get the file extension
            $ext = pathinfo($filename, PATHINFO_EXTENSION); 
            //
            //The file must be in excel format
            if (!(ext==="xls"||$ext=="xslx")) throw new \Exception("Excel file expected, not $ext");
            //
            //The file must exist
            if (!is_file($filename)) throw new \Exception("File $filename does not exist");
            //
            //Use the comments in the given Microsoft Exce file to return an 
            //array of layouts. Researah on a PHP-Excel library suitable for 
            //this purpose
            return create_excel_layout($filename);
        }else{
            //
            throw new \Exception("Unknown Iquestionaire format for ".json_encode($milk));
        }
    }
    
    
    //Destructure the given labele= and check for syntax errors. If found, throw 
    //an exception to discontinue execution; otherwise initialize
    //the referenced artefact.
    private function compile_label(array $label, array &$errors):void{
       //
        //Destructure a label layout. NB: This will fail if a user supplies 
        //less than 5 columns
        try{
            list($dbname, $ename, $alias, $cname, $Iexp) = $label;
        }catch(\Exception $ex){
            $errors[] = new myerror("Label ".json_encode($label)." has less than 5 elements");
            return;
        }
        //
        //The named database must exist
        $dbase = $this->open_dbase($dbname);
        //
        //The named entity must be in the database
        if (!isset($dbase->entities[$ename])){
            $errors[] = new myerror("Entity $dbname.$ename is not found");
            return;
        }
        $entity = $dbase->entities[$ename];
        //
        //The named column must exist in the entity
        if (!isset($entity->columns[$cname])){
            $errors[] = new myerror("Column $dbname.$ename.$cname is not found");
            return;
        }
        //
        //The alias data type must be an array
        if(!is_array($alias)){
            $errors[] = new myerror("Alias ".json_encode($alias)." must be an array");
            return;
        }
        //
        //Create a capture expression using static label value
        $exp = \capture\expression::create($Iexp);
        //
        //Create the artefact if it does not exist
        if (!$this->artefacts->hasKey([$entity, $alias])){
            //
            //The artefact does not exist: create it.
            $artefact = new \capture\artefact($entity, $alias);
            //
            //Update the artefacts collection, using entity as part of the 
            //indexing key
            $this->artefacts->put([$entity, $alias], $artefact); 
        }else{
            //The artefact exist: get it
            $artefact = $this->artefacts->get([$entity, $alias]);
        }
        //
        //Save the expression under the named column. Overwriting is an
        //indicator of some potential problem.
        if (isset($artefact->columns[$cname]->exp)){
            $errors[] = new myerror(
               "This column $dbname.$ename.$cname for alias "
               .json_encode($alias). " is already set");
            return;
        }
        //Set the column's expression
        $artefact->columns[$cname]->exp = $exp;
        //
        //Set the booking status of this expression. An artefact is not booked
        //by us if its primary key is specified from the labeled input
        $this->booked = $cname === $ename?false:true;
        //
        //Set the milk table name the artefact and all her ancestors
        if ($exp instanceof capture\lookup) $artefact->set_tname(); 
    }
    
    //Check the given table for syntax errors and if none, add it
    //to the collection of tabular objects. The structure of the table
    //is:-
    //type Itable = {
    //  class_name:string, 
    //  args:Array<any>
    //}
    private function compile_table(stdClass $Itable, array &$errors):void{
        //
        //Get the table's class name; it must exist
        if (!isset($Itable->class_name)){
            $errors[] = new myerror("Class name not found for table ".json_decode($Itable));
            //
            return;
        }
        //
        //Get the class name
        $class_name = $Itable->class_name;
        //
        //The constructor arguments must be known
        if (!isset($Itable->args)){
            $errors[] = new myerror("No constructor arguents found for table ".json_encode($Itable));
            //
            return;
        }
        //Get the constructor arguments
        $args = $Itable->args;
        //
        //The constructor arguements must be supplied as an array
        if (!is_array($args)){
            $errors[] = new myerror("Constructor arguements must be an array, for table ".json_encode($Itable));
            //
            return;
        }
        //
        //The first argument must be the table name
        list($tname, $remainder) = $args;
        //
        //Get the table name; it must exist
        if (!isset($tname)){
            //
            $errors[] = new myerror("No tname found in ".json_encode($Itable));
            //
            return;
        }    
        //
        //Duplicate table names are not allowed in the same questionnaire
        if (isset($this->tables[$tname])){
            $errors[] = new myerror("This table name '$tname' is already used");
            //
            return;
        }    
        //
        //Create a new data table
        $table = new $class_name(...$args);
        //
        //Add the new table to its collection
        $this->tables[$tname] = $table;
    }    
    
    }

}

//The capture namespace is home for a new entity -- the capture version
namespace capture{

//The short forms of type answer
use \answer as ans;
    
//
//Define the 5 conditions under which the artefact writing occurs. The 
//conditions indicate a need to:-
define("HARD_UPDATE", "Update all the fields of a record, including identifiers");
define("ALIEN", "Report incomplete identifiers");
define("FRAUDULENT", "Report ambguity error");
define("INSERT", "Create brand new record");
define("SOFT_UPDATE", "Update all the fields excluding the identifiers");

//An artefact is a (schema root) table entity qualified with an alias.
//In practice, it cannot extend the current version of a table because 
//a table's columns are defined as a method for historicl reasons -- rather
//than a property.  
class artefact extends \table{
    //
    //The alias that makes this table an artefact
    public array $alias;
    //
    //The source table that was used to derive this artefact
    public \table $source;
    //
    //The (milk) table that this artefactdepends on. There may be none
    public /*string|null*/$tname;
    //
    //The statement that update a record. There will be 2 versions for 
    //updating the record based on cross- and non-crosss member columns.
    public array /*<update>*/ $update;
    //
    //Set te following flag to true when saving an artefact based on cross 
    //member columns; otherwise it is false.
    public bool $save_cross_members;
    //
    //The insert statement for creatting new records
    public insert $insert;
    
    //
    public function __construct(
        //
        //The table entity that is the source of data for this artefact
        \table $source,
        //
        //This is the bit that extends a table.
        array $alias
    ) {
        //
        $this->alias = $alias;
        //
        $this->source = $source;
        //
        parent::__construct($source->name, $source->dbname);
        //
        //Set the database, by opening one
        $this->dbase = $this->open_dbase($source->dbname);
        //
        //Set the columns of all the artefacts (before using them).
        //This is necessary because artefacts inherit \table which, from
        //the way it is constructed, has no initial columns
        $this->columns = array_map(fn($col)=>new column($col, $this), $source->columns);
        //
        //Set the indices of this artefact to ensure that the columns it references
        //are for this artefact.
        //(With better modelling, this method will not be necessary in future
        //as it should happen at he entity level)
        $this->indices = array_map(
            fn($ixname)=>new index($ixname, $this), 
            array_keys($source->indices)
        );        
    }
    
    //Returns the primary key of an artefact
    function pk():column{
        //
        //Get the primary key field'sname
        $fname = $this->source->name;
        //
        //Rturn the matching primary key column
        return $this->columns[$fname];
    }
    
    //Set the milk table name of this artefact, so that we can decide later
    //if this artefact should be exported table dependently or indepently
    public function set_tname():void{
        //
        //Don't waste time if the table's name is already set
        if (isset($this->tname)) return;
        //
        //Collect the table names that match these expressions for these
        //columns, starting with an empty list
        $tnames = []; $this->collect_tname($tnames);
        //
        //Remove duplicates
        array_unique($tnames);
        //
        //Get the number of table names
        $count = count($tnames);
        //
        //If there are no table names, then the tname to null
        if ($count==0) $this->tname=null;
        //
        //If there is one table, set it as the tname
        elseif ($count==1) $this->tname = $tnames[0];
        //
        //Multiple tables is not expected
        else throw new \Exception("Multiple milk tables, ".json_encode($tnames)." not expected");
    }
    
    //
    //Reset all answers for all the primary and attributecolumns of this 
    //artefact. Leave the foreigners alone. This should be done after saving
    //a table row
    public function reset_answers():void{
        //
        foreach($this->columns as $col){
            //
            if (!$col instanceof \foreign){
                $col->answer = null;
                $col->scalar = null;
            } 
        }
    }
    
    //
    //Collect the milk table names asscociated with this artefact
    function collect_tname(array &$tnames):void{
        //
        //Loop through all the columns of this artfavt to yield 
        //tnames based on their expressions
        foreach($this->columns as $column){
            //
            //For attribute columns, the expression must be set
            if (isset($column->exp)) $column->exp->collect_tname($tnames);
            //
            //For foreign keys colmns, the nearest artefact must be set
            elseif(isset($this->artefact)) $this->artefact->collect_tname($tnames);
        }
    }
    
    //
    //Initialize the CRUD (without the D) statements for writing data
    function set_statements():void{
        //
        //Initialize record CREATING statement
        $this->insert = new insert($this);
        //
        //Initalize the record REVIEWING statements as many times as there are 
        //indices
        foreach($this->indices as $index){
            $index->select = new select($this, $index);
        }
        //
        //Initialze the 2 record UPDATING statements, one for cross members
        //the other for the non-cross members
        $this->update =[
            'cross_members'=>new update($this, true),
            'non_cross_members'=>new update($this, false)
        ];
    }
    
    //
    //Log the attributes of this artefact
    function log_attributes($element):void{
        //
        //Add the save mode as an attribute
        $save = $this->save_cross_members ? "cross_members":"structurals";
        \log::$current->add_attr('alias', json_encode($this->alias), $element);
        //
        //Add the alias as an attribute.
        \log::$current->add_attr('save', $save, $element);
    }
    //
    //Writing this artefact proceeds by saving all her structural columns
    //with primary key as the last one. The non-structurals, i.e., cross members 
    //are saved in a later phase.
    function write(/*row|null*/$row):ans {
        //
        //Get the save mode; its either cross or non-cross members
        $is_cross = $this->save_cross_members;
        //
        //Collect all the columns that match the save mode
        $cols = array_filter(
            $this->columns, 
            fn($col)=>$col->source->is_cross_member()==$is_cross
        );
        //
        //Sort them such that the primary key is the last one; the order for the 
        //others does not matter. 
        usort($cols, fn($a, $b)=>$a->source instanceof \primary?1:0);
        //
        //Save all the colums
        foreach($cols as $col){$col->save($row);}
        //
        //Return the answer associated with the primary key
        return $this->pk()->answer;
    }
    
    //Save the foreign key columns by binding their simplified forms 
    //to the primary keys of the corresponding artefacts
    public function save_foreigners(){
        //
        //Collect all the foreign key columns of this artefact
        $foreigners = array_filter(
            $this->columns, 
            fn($col)=>$col->source instanceof \foreign
        );
        //
        //Fore each foreigner, do the home and away key bindings
        foreach($foreigners as $foreigner){
            //
            //Use the source of this column (it must be a foreigner) to get the
            //away entity
            $away = $foreigner->source->away();
            //
            //Use the away entity and the alias of this artefact to 
            //get the nearest available artefact
            //
            //Get the alias of this artefact
            $alias = $this->alias;
            //
            //Depending on whether we have a hierarchical situation or not,
            // formulate the source alias
            if ($foreigner->source->is_hierarchical()) {
                //
                //We have a hierarchial situation
                //
                //The source alias is the parent of the given one, if valid
                //
                //The aliased enity has no parent
                if (count($alias) === 0) {
                    $foreigner->nearest = new myerror("Missing parent of {$foreigner->source->ename}");
                    //
                    continue;
                }
                //
                //Drop the last array suffix to get the parent alias as the source
                $source_alias = array_slice($alias, 0, count($alias) - 1);
            } else {
                //
                //We have a non-hierarchical case.
                //
                //The source alias is the same as the given one
                $source_alias = $alias;
            }
            //
            //Get the nearest artefact. It must not be ambiguous. 
            //If there is none, return a (missing data) error
            $foreigner->nearest = $foreigner->get_nearest_artefact($away, $alias);
        }
    }
    
    //Save the indices of this artefact to return a conditioned 
    //expression.  
    public function save_indices(/*row|null*/$row):array/*{condition, exp}*/{
        //
        //A hard update is required if the primary key expression is already 
        //set from the user input, i.e., the old guard driver scenario. This
        //is occurs when:-
        if (
            //...the primary key column is set...
            isset($this->pk()->exp)
            
            //... with a sensible value...
            &&($pk=$this->pk()->exp) instanceof scalar
        ) return ["type"=>HARD_UPDATE, "exp"=>$pk];
        //
        //Use the identifiers to look up this artefact from the database
        //
        //Make sure that this table's indices isset otherwise throw an exeception
        if(!isset($this->indices)){
            throw new myerror("Bad data model; no unique indentification indices found for table '$this->name'");
        }
        //
        //Save all the indices of this artefact
        $results/* Array<exp> */ = array_map(
            fn($paper) => $paper->save($row),
            $this->indices
        );
        //
        //Select the resulting expressions that indicate valid saves
        $valids = array_filter(
            $results, 
            fn($result) => $result instanceof scalar);
        //
        //Extract the alien driver. A driver is an alien if he 
        //has no valid paper
        if (count($valids) === 0) {
            //
            //Compile the error to return 
            $exp = new myerror("No valid index found for table '{$this->name}'"
                . " because one or more identification "
                . "columns is missing or oversized");
            //
            //Returna the alint condition
            return ["type"=>ALIEN, "exp"=>$exp];
        }
        //
        //Get and clean the licences
        $dirty_licences = array_map(
            fn($paper) => $paper->value, 
            $valids);
        //
        //Clean them 
        $licences = array_unique($dirty_licences);
        //
        //Count the clean licences
        $no = count($licences);
        //
        //Fraud:
        //A driver is a fraud if his papers resolve to mutiple licences      
        if ($no > 1) {
            //
            //Compile the inconsistency error (multiple pk error)
            $exp = new myerror("$no Primary key found associated with this entity"
                    . " $this->name consider merging");
            //
            //Return the expression
            return ["type"=>FRAUDULENT, "exp"=>$exp];
        }
        //
        //Get the licences obtained by inserting 
        $inserts = array_filter(
            $valids, 
            fn($licence) => isset($licence->type) && $licence->type === 'insert'
        );
        //
        //Post_graduate 
        //A driver is a post graduate if one of his valid licences is an insert 
        if (count($inserts) > 0) {
            //
            //Get the first insert
            $insert = array_values($inserts)[0];
            //
            //Return the insert
            return ["type"=>INSERT, "exp"=>$insert];
        }
        //
        // A driver is an under graduate if there are no inserts. 
        if (count($inserts) === 0) {
            //
            //Get the first member of the valid ones
            $valid = array_values($valids)[0];
            
            //
            return ["type"=>SOFT_UPDATE, "exp"=>$valid];
        }
        //
        //I will never het here, but, make peace with the compiler 
        throw new \Exception("Unusual entity write situation");
    }

    
}

//The data capture column extends the root column with an alias and a data 
//source of the same type as the root version
class column extends \column {
    //
    //The (root) source of data for this column 
    public \column $source;
    //
    //The artefact that is the home of this column
    public artefact $artefact;
    //
    //Data capture expression that is the main reason for extending the root
    //column.
    public expression $exp;
    //
    //This (simplifid form of an expression) helps to establish if the scalar 
    //form is valid or not. It is initialized to null to make the property's
    //referevce available.
    public /*ans*/ $answer=null;
    //
    //Holder for the (scalar) value that is bound to a statement here.
    //It is initialized to null to make the property's referencce available.
    public /*basic_value*/ $scalar=null;
    //
    function __construct(\column $source, artefact $artefact) {
        $this->source = $source;
        $this->artefact = $artefact;
        parent::__construct($source->dbname, $source->ename, $source->name);
    }
    
    
    //Writing a column depends on its type. This function is the main evidence
    //that our OO model comprising of a database-entity-column-artefact needs
    //revision
    function write(/*row|null*/$row):ans{
        //
        //Start with a null expression to return
        $exp = null;
        //
        //Save attributes
        if ($this->source instanceof \attribute){
            //
            //Saving an attribute involves simplifying its input/capture 
            //expresssion and returning the output version
            $exp = $this->write_attribute($row);
        }
        //
        //Save foreign keys
        elseif ($this->source instanceof \foreign){
            //
            //If the nearest artefact is an error, then set home column to
            //the error and return
            if ($this->nearest instanceof \myerror) {
                //
                $exp = $this->nearest;
            }
            //If the answer of this nearest artefact is still null, somethin is 
            //wron
            elseif($this->nearest->pk()->answer==null){
                $ename = $this->nearest->pk()->ename;
                $cname = $this->nearest->pk()->name;
                throw new \Exception("$ename.$cname is not set as expected. Check sorting");
            }else{
                //
                //The nearest is an artefact. Bind its primary key to that of this
                //foreigner.
                $exp = $this->nearest->pk()->answer;
                
            }
            //
            //It is an error if the expression is not an answer
            if (!$exp instanceof ans) 
                throw new \Exception("The foreign key must be of type answer");
        }
        //
        //Save the primay key, by saving indices
        elseif ($this->source instanceof \primary){
            //
            //Deriving a primary key may or may not involve inserting a record 
            //to the database -- deending on the available identification keys
            $exp = $this->write_primary($row);
        }
        //Any other type of column is not expected
        else{
            throw new \Exception(
                  "A column of type "
                    . json_encode($exp)
                    ." cannot be writen"
             );
        }
        //
        //Set the scalar value to support binding of sql statements
        if ($exp instanceof scalar) $this->scalar = $exp->value;
        //
        //Set the answer to support testing of whether the scalar is valid or ot
        $this->answer = $exp;
        //
        //Return the expression
        return $exp;
    }
    
    //
    //Write an attribute column to the database by simplifying its expression
    //to a basic value (or error).
    private function write_attribute(/*row|null*/$row):ans{
        //
        //We are done, if the answer of this column is known. It may have been
        //set during the writing of table independent artefacts. It is null when
        //not set
        if (!is_null($this->answer)) return $this->answer;
        //
        //Ensure that the attribute's size for identifiers
        //is less or equal to the size of the column. 
        if (
            isset($this->exp)
            && $this->exp instanceof scalar
            && $this->source->data_type=== "varchar"
            && $this->source->is_id()
            && ($size= strlen(strval($this->exp->simplify())))>$this->source->length        
        ){
            $exp = new \myerror("The size of column ".$this->source->full_name()
                    . "is $size which is larger than ".$this->source->length);
        }
        //
        //If the expression is set, simplify it
        elseif (isset($this->exp)){
            $exp = $this->exp->simplify();
        }
        //
        //The attribute's value not set; try the default.
        elseif($this->source->default!== 'NULL' &! is_null($this->source->default)){
            //
            //Parse the default value to get an expression.
            $exp = \mutall::parse_default($this->source->default);
        }
        else{
            //
            //Create an erroneous expression for missing data 
            $exp = new \myerror("Attribute $this->ename.$this->name is not found");
        }
        //
        //Return the expression
        return $exp;
    }
    
    //Returns the naerest artefact (contextually) as an expression. 
    //There may be ambiguty or none, in which case the corresponding error is 
    //returned
    public function get_nearest_artefact(\entity $entity, array $source_alias){
        //
        //Collect all the aliases associated with the given entity name. They
        //are part of the keys of the artefacts \Ds\Map collection in the 
        //current questionaire
        //
        //Get the artefact/alias pairs used for indexing the artefacts
        $allkeys = \questionnaire::$current->artefacts->keys()->toArray();
        //
        //Isolate the keys that match the given entity. The entity component is
        //the first part of a key. 
        $keys = array_filter($allkeys, fn($key)=>$key[0]===$entity); 
        //
        //Collect all the keys' aliases. The alias is the 2nd component of a key
        $aliases = array_map(fn($key)=>$key[1], $keys);
        //
        //Compute the contextual distances/alias pairs
        $pairs = array_map(
                //
                //Compute the distance of each destination alias from the source
                fn($alias) => [
                    'alias' => $alias, 
                    'distance' => $this->distance($source_alias, $alias)],
                //
                $aliases
        );
        //
        //Collect all the contextual distances of the aliases
        $distances = array_map(fn($pair) => $pair['distance'], $pairs);
        //
        //There is no nearest artefact if no distances are found
        if (count($distances)==0) return new \myerror("Missing data for this foreign key");
        //
        //Get the least distance. 
        $distance = min($distances);
        //
        //Filter the alias/distance pairs with the least distance
        $least_pairs = array_filter($pairs, fn($pair) => $pair['distance'] === $distance);
        //
        //Report ambiguity
        if (count($least_pairs) > 1) return new \myerror('Ambiguity error', $least_pairs);
        //
        //Ensure that the pairs are indxed numerically. Get the first pair as 
        //the only one. The distance is immaterial. Its the matching alias part
        //that you want 
        $least_alias = array_values($least_pairs)[0]['alias'];
        //
        //Use the nearest alias to get the matching artefact
        $artefact = \questionnaire::$current->artefacts->get([$entity, $least_alias]);
        //
        //Return the nearest artefact
        return $artefact;
    }
    
    //Returns the contextual distance between two contexts, a.k.a, aliases
    //E.g the distance between [3,8,6,2] and [3,8,4,3] is 4 
    //The shared elements are [3,8].
    private function distance(array $alias_source, array $alias_dest): int {
        //
        //Start with a shared length of 0
        $l=0;
        //
        //Loop throgh the elements of the source context and stop when ...
        for(;$l<count($alias_source);$l++){
           //
           //...the index to the destination alias is not defined...
           if(!isset($alias_dest[$l])){break;}
           // 
           //... or the elements of the indexed source and destination are 
           //different
           if($alias_source[$l] !== $alias_dest[$l]){break;} 
        } 
        //
        //At this point $l represents the number of elements in the shared 
        //array
        //
        //return the distance as the sum of the size of the source (without the 
        //shared elements)and the size of the destination (also without the shared
        //elements)
        return count($alias_source) + count($alias_dest) - 2*$l;
    }

    //Writing the primary key column
    private function write_primary(/*row|null*/$row):ans{
        //
        //Save the arterfact's indices and return the conditioned results
        $condition = $this->artefact->save_indices($row);
        //
        //Determine if the results need to be updated or not.
        switch($condition['type']){
            //
            //The resulting expression need to be adjusted for updates
            case HARD_UPDATE:
            case SOFT_UPDATE:
                //
                //Save the expression, so that aupdate can re-access it
                $this->artefact->pk()->answer = $condition['exp'];
                //
                //Update the non-cross mmebers of this artefact
                $exp= $this->artefact->update['non_cross_members']->execute();
                break;
            //
            //The results do not need adjustment for all the other conditions
            case ALIEN:
            case FRAUDULENT:
            case INSERT:
                $exp = $condition['exp'];
                break;
            default:
                throw new \Exception("Index condition '".$condition['type']."' is not known");
        }
        //
        return $exp;
    }
}


//Modelling the tabular data layout, a.k.a., milk. It partcipates in 
//logging
abstract class table extends \schema{
    //
    //The table name
    public $name;
    //
    //The following functions will be need to be implemented
    //
    //Initialize the data stream
    abstract function open():void;
    //
    //Returns the header column names. This must be called after the a table
    //is opened
    abstract function get_header_columns():array;/*<cname>*/
    //
    //The fuel table header
    public array/*= Map<cname, dposition>*/ $header;
    //
    //Return a row of data as an 1-d array, or false if at the end of the 
    //stream
    abstract function read():\Generator/*array<basic_value>*/;
    //
    //Close the data stream
    abstract function close():void;
    //
    //The current row Index of the body
    public int $rowIndex;
    //
    //Initialize the collection of table-based errors.
    public \Ds\Map $map/*:map<[tname, msg], count>*/;
    //
    //The row number, starting from 0, where the table's body starts.        
    public int $body_start;
    //
    function __construct(string $tname, int $body_start){
        //
        $this->name = $tname;
        //
        $this->body_start=$body_start;
        //
        //Initialize the schema
        parent::__construct($tname);
        
        //Initialize the collection of table-based errors.
        $this->map/*:map<[tname, msg], count>*/ = new \Ds\Map();
        //
        //Open this table; This means difffert things for different descendants
        //of this class. For example, for:-
        //  -simple arrays, i.e., ifuel, nothing happens.
        //  -sqls, a connection is made.
        //  -text, a file is opended
        $this->open();
        //
        //Set the header property to allow us lookup named column 
        //positions, specially needed in a lookup expression.
        $this->set_header();
    }
    //
    //Export the data from this given table. For each table row, tr, :-
    //- Set the table header expresions
    //- Simplify all the labeled entities that use the table
    //See file 'questionnaire.ts' to view the structure of a table
    function write(/*null|row*/$row):ans{
        //
        //Select all the artetacts that depend on this table
        $artefacts = array_filter(
            //
            //Use the current pool of artefacts
            \questionnaire::$current->artefacts->values()->toArray(), 
            //
            //An artefact depends on table, if it has a lookup
            //expression referencing this table
            fn($artefact)=> isset($artefact->tname) &&  $artefact->tname== $this->name
        );
        //
        //Export the table's body
        //
        //For each table row, set the header expressions. (Rememeber to track 
        //the row counter in case it is used for formulating expressions)
        $this->write_body($artefacts);
        //
        //You must return annswer; go for string scalar
        return new scalar('check');
    }
    
    //Export the body of a table, given the artefacts that depend on it
    private function write_body(array $artefacts){
        //
        //Start counting from row number 0
        $this->rowIndex = 0;
        //
        //Get the current barrel. Remeber that by design, only one barrel is 
        //used for writing the data, to conserve memory
        $barrel = barrel::$current;
        //
        //Attach the current table name to the barrel
        $barrel->tname = $this->name;
        //
        //Attach the table's artefacts to the barrel
        $barrel->artefacts = $artefacts;
        //
        //Ensure that we are at the top of the file. This is specific to csv.
        //Generalize it.
        //rewind($this->stream);
        //
        //Loop through all the body rows to export them one by one.
        foreach($this->read() as $Ibarrel){
            //
            //If the body start is set to something greater than 0, then
            //repect it, by skipping this iteration if necessary.
            if ($this->body_start>0 && $this->rowIndex<$this->body_start) {
                //
                //Update the row counter
                $this->rowIndex++;
                continue; 
            }
            //
            //Set the barrel's row index counter
            $barrel->rowIndex = $this->rowIndex;
            //
            //Reset the non-foreign key answers of every artefact being saved
            foreach($artefacts as $artefact){ $artefact->reset_answers(); } 
            //
            //Save the values to be accessed during evaluatons of 
            //lookup functions
            $barrel->Ibarrel = $Ibarrel;
            //
            //Formulate the row object 
            $row = [
                'rowIndex'=>$this->rowIndex, 
                'tname'=>$this->name
            ];
            //
            //Update the barrels partial name. The partial name is 
            //typically for fomulating xml tags during data logging
            $barrel->partial_name = "r$this->rowIndex";
            //
            //Log the barrel as you carry out the save. Log any pdo errors
            try{
                $barrel->save($row);
            }catch(\PDOException $ex){
                echo "At ".$this->rowIndex.". ".$ex->getMessage()."<br/>";
            }    
            //
            //Increase the row counter
            $this->rowIndex++;
        }
        //Close the body
        $this->close();
    }
    //Set the header property, if necessary. It is not necessary if the
    //tabble does not have one, e.g., a text file without header.
    //This mthod is called after a table is opene
    private function set_header():void{
        //
        //Get the header column names
        $cnames = $this->get_header_columns();
        //
        //Th header remains unset if the number of colmns is empty
        if (count($cnames)==0) return; 
        //
        //Ensure that this list is unique and report error if not
        //
        //Get the column name frequencies
        $cols = array_count_values($cnames);
        //
        //Isolate duplicate keys
        $dups = array_filter($cols, fn($freq)=>$freq>1);
        //
        //If there are duplicates, report them and stop the process
        if (count($dups)>1){
            //
            //Get the dulicate column names and join them with comma separation
            $dupstr = implode(", ", array_keys($dups));
            //
            //Compile the final message
            $msg = "The following header columns for table $this->tname are duplicated: $dupstr";
            //
            throw new \Exception($msg);
        }
        //Get the positions of the header columns. The positions correspond to 
        //matching data in the tables body
        $positions = array_keys($cnames);
        //
        //Save the table header as an array of psotions indexed by the column
        //names. This is goin to be useful for simplifying lookup expressions
        $this->header = array_combine($cnames, $positions);      
    }
  
}

//Modelling a simple 2-d array of basic values
class fuel extends table{
    //
    //The 2-d array of basic values
    public array/*Array<Array<basic_value>>*/ $ifuel;
    //
    function __construct(
        //
        //The table's name, used in formulating lookup expressions    
        string $tname,
        //
        //The table's headee as an array of colum names indexed by their 
        //positions     
        array /*Array<position, cname>*/$cnames,    
        //    
        //A tables fueuel, as a double array of basic values    
        array /*Array<<Array<basic_value>>>*/ $ifuel,
        //
        //Where does the body start    
        int $body_start    
    ){
        $this->cnames = $cnames;
        $this->ifuel = $ifuel;
        parent::__construct($tname, $body_start);
    }
    //
    //Opening a simple array does nothing since the data is already in memory
    function open():void{}
    
    //Returns the header clums
    function get_header_columns():array/*<cname>*/{
        return $this->cnames;
    }
    
    //Fetch a row of data
    function read():\Generator/*array<basic_value>*/{
        //
        //Loop through all the data rows
        foreach($this->data as $row){yield $row; };
    }
    
    //Closing a an array does nothing
    function close():void{}
}

//Modelling a text file that is line terminated by carriage returns
class csv extends table{
    //
    public string $filename;
    //
    //The header colmumn names. If empty, it means teh user wishes to use 
    //the default values
    public array $cnames; 
    //
    //The row number, starting from 0, where column names are stored
    //A negative number means that file has no header     
    public int $header_start;
    //
    //Text stream when opened
    public $stream;
    //
    //The default specs for colummn and body locations are designed to accomodate
    //loading of a data file with no header column names, either supplied
    //by the user or fond in the data file
    function __construct(
        //
        //The name of the text table    
        string $tname, 
        //
        //The filename that holds the (milk) data    
        string $filename,
        //
        //The header colmumn names. If empty, it means teh user wishes to use 
        //the default values
        array $cnames = [],
        //
        //Text used as he value separator
        string $delimiter=",",
        //
        //The row number, starting from 0, where column names are stored
        //A negative number means that file has no header     
        int $header_start = -1,
        //
        //The row number, starting from 0, where the table's body starts.        
        int $body_start = 0
    ){
        //
        $this->filename = $filename;
        $this->cnames = $cnames;
        $this->header_start = $header_start;
        $this->delimiter = $delimiter;
        //
        parent::__construct($tname, $body_start);
    }
    //
    //Returns the header column names of a text file
    function get_header_columns():array/*<cname>*/{
        //
        //Respect the user suplied columns as a priority. The default is an 
        //empty list
        if(count($this->cnames)>0) return $this->cnames;
        //
        //Check if the header is part of the data; it is if it is a positive 
        //number
        if ($this->header_start>0){
            //
            //Get the data at that row position
            for($i=0; ($line=fgets($this->stream))!==false; $i++){
                if ($i==$this->header_start){
                    //
                    //Parse the ans to a string, assuming csv format
                    $cnames = str_getcsv($line);
                    //
                    return $cnames;
                }
            }
        }
        //Return an emprly list to signify no column names
        return [];
    }
    //
    //
    //Open the file stream in read only mode
    function open():void{
        $this->stream= fopen($this->filename, "r");
    }
    //
    //Fetch a row of data
    function read():\Generator/*array<basic_value>*/{
        //
        while(!feof($this->stream)){
            //
            //Get an unlimited line length of data and parse it using CSV format
            yield \fgetcsv($this->stream, 0, $this->delimiter);
        }
    }
    //
    //
    //Close the text stream
    function close():void{\fclose($this->stream); }
}

//Modllng an sql statement as a source of body rows
class query extends table{
    //
    //The sql statement and the default database
    public string $sql;
    public string $dbname;
    //
    //The thE database connction
    public \PDO $pdo;
    //
    //The SQL statement handle
    public \PDOStatement $stmt;
    //
    function __construct(
        string $tname, 
        string $sql, 
        string $dbname,
        int $body_start
    ) {
        //
        $this->sql= $sql;
        $this->dbname = $dbname;
        //
        parent::__construct($tname, $body_start);
    }
    
    //Openig a query does nothomg as most operations have alleady
    //been done at in teh colstructor
    function open():void{
        //
        //Formulate the full database name string, as required by MySql. Yes, this
        //assumed this model is for MySql database systems
        $dbname = "mysql:host=localhost;dbname=$this->dbname";
        //
        //Initialize the PDO property. The server login credentials are maintained
        //in a config file.
        $this->pdo = new \PDO($dbname, \config::username, \config::password);
        //
        //Throw exceptions on database errors, rather thn returning
        //false on querying the dabase -- which can be tedious to handle for the 
        //errors 
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        //
        //Execute the sql statement
        $this->stmt = $this->pdo->query($this->sql);
    }
    
    //Returns the columns of sql statement
    function get_header_columns():array/*<cname>*/{
        //
        //Start with an empty list of columns
        $cnames = [];
        //
        //Stepth through all the columns in a statement
        for($i=0; $i<$this->stmt->columnCount(); $i++){
            //
            //Get the i'th column
            $column =  $this->stmt->getColumnMeta($i);
            //
            //Retrieve the ame component an add it to the list
            $cnames[] = $column['name'];
        }
        //
        //Return the names
        return $cnames;
    }
    
    //Fetch a row of data
    function read():\Generator/*array<basic_value>*/{
        //
        while($row = $this->stmt->fetch(\PDO::FETCH_NUM)){
            yield $row;
        }
    }
    
    //Close the data stream
    function close():void{
        $this->stmt->closeCursor();
    }
    
}


//This class models a table row of data values; it was introduced to primarily 
//support logging of individual table rows without having to create multiple 
//copies of this instance in order to ssave memory for large data loads.  
class barrel extends \schema{
    //
    //The artfacts being saved
    public array $artefacts;
    //
    //The shared barrel used for exporting table rows; it is initialized
    //when a questionnaire is created. 
    static barrel $current;
    //
    //The last row of values fetched
    public array $Ibarrel;
    //
    //The table being saved
    public string $tname;
    //
    //The row being saved
    public int $rowIndex;
    //
    function __construct(){
        parent::__construct('barrel');
    }
    //
    //Write the barrel in 2 phases. In phase 1, the arteg=fact is saved
    //using structural columns; in the next phase, cross members are used to
    //update the same artefact, thus preventing possibility of endless looping.
    function write(/*row*/ $row):ans{
        //
        //Get the table-dependent artefacts being exported
        $artefacts = $this->artefacts;
        //
        //Save them using the given table row
        \questionnaire::$current->export_artefacts($artefacts, $row);
        //
        //You must return answer; go for string scalar
        return new scalar('check');
    }
}

//Modelling expression in the questionnare namespace, i.e., used for expressing
//data inputs. In contrast, expression defined in the root namespace is for 
//supporting the save operations asw well as modelling sql views
abstract class  expression implements \operand{
    //
    function __construct(){}
    
    //Create an expression class object from the given input
    static function create(/*Iexp*/ $Iexp):expression{
        //
        //SCALAR:Basic values are converted to the scalar object. In the sql 
        //namespace, this is referred to as a literal
        if (is_scalar($Iexp)){
            return new scalar($Iexp);
        }
        //
        //All other forms of functions are specified as a tupple with 2 
        //arguments: [fname, ...arg]
        if (!is_array($Iexp)){
            //
            return new myerror(json_encode($Iexp)." must be a multi-element tupple");
        }
        //
        //Destructure the tuple to get the tunction's name and its
        //arguements
        //
        //The firste elemen is teh functions's name; its must be set
        if(!isset($Iexp[0])) 
            return new myerror("Name of the function not found");
        $fname = $Iexp[0];
        //
        //The rest of the arguments depend on the duction
        $args = array_slice($Iexp, 1);
        //
        //Create the function and catch any construction error.
        try{
            //Formulate the function and return it......
            return  new $fname(...$args);
        } catch (Exception $ex) {
            return new myerror("Function error in '$fname': ". $ex->getMessage());
        }
    }
    
   //collect table names that this expression depends. 
   abstract function collect_tname(array &$tnames):void;
   
   //
   //Check this expression for integrity errors
   abstract function pass_integrity(&$error):bool;
   
}

//The capture version of a scalar
class scalar extends expression implements \answer{
    //
    //Borrow the shared methods between scalar the root and capture versions
    use \scalar_trait;
    //
    function __construct($value, $type = null) {
        //
        //The value of a literal is a scalar. See PHP  definition of a scalar
        if (!is_scalar($value)) {
            throw new \Exception(
                'The value of a literal must be a scalar. Found '
                . json_encode($value)
            );
        }
        //
        //save the value
        $this->value = $value;
        $this->type = $type;
        //
        parent::__construct();
    }

    
    //A scalar does not yield any tname
    function collect_tname(array &$tnames):void{}
    
    //A scalar expression always passe integrity checks always
    function pass_integrity(&$error):bool{return true;}
}

//A lookup is an expresion associated with a specified column of a give 
//table
class lookup extends expression{
    //
    //The name of the table to be looked up
    public string $tname;
    //
    //The column of to be used for simplifying a looking expression can be 
    //specified as eiter a name or a (0-based) position
    public /*string|int*/ $cname;
    
    //Creates a lookup function from the given arguments. 
    function __construct(string $tname, /*int|string*/$cname){
        //
        $this->tname = $tname;
        $this->cname = $cname;
        //
        parent::__construct();
    }
    
    //A lookup expression yields the exprsession's table name
    function collect_tname(array &$tnames):void{
        $tnames[] = $this->tname;
    }
    
    //Simplify a lookup function by looking it up its values in the last fetched
    //ones in the underlyng  table's body
    function simplify():ans{
        //
        //Get the (milk) table name of this this lookup expression
        $tname = $this->tname;
        //
        //The table must exist
        if(!isset(\questionnaire::$current->tables[$tname]))
            return new myerror("Table '$tname' is not found");
        //
        //Get the table that is referenced
        $table = \questionnaire::$current->tables[$tname];
        //
        //Get the colum associated wth the looku expression
        $cname = $this->cname;
        //
        //Get the position of the required value. Remember:-
        //1- No position computations are necessary if he column is an integer
        //2- The header header are column positions indexed by the column 
        //names
        $position = null;
        //
        //If the column is a name, then there must be a header associated with 
        //the table 
        if (is_string($cname)){
            //
            //The header must be available
            if (!isset($table->header)){
                throw new \Exception("Table $tname has no header");
            }
            //
            //Check that the named column exists in the header
            if (!isset($table->header[$cname])) 
                return new myerror("Column $cname is not found in table $tname");
            //
            $position = $table->header[$cname];
        }
        //When the column is given as a position
        elseif (is_int($cname)){
            //
            //The column name is specified in terms of the position
            $position = $cname;
        }
        //The columns data type is not known
        else{
            throw new \Exception("Data type for column $cname in table $tname is not expexted");
        }
        //
        //Get the current row (a.k.a., barrel) being processed
        $barrel = barrel::$current;
        //
        //Ensure that the barrel is associated with this table
        if ($barrel->tname!==$tname) throw new \Exception(
            "The curent barrel is not assciated with table '$tname'"    
         );
        //
        //The current barrel has the data we are looking for. It must
        //have been set by the loop that visited each row of the milk table
        if (!isset($barrel->Ibarrel[$position])) throw new \Exception(
            "Column '$cname' at position '$position' of table '$tname' is not set at row '$barrel->rowIndex'"
        );
        //
        //Get the basic value at the requested position
        $value = $barrel->Ibarrel[$position];
        //
        //If the value is null convert it to a null exprssion
        if (is_null($value)) return new \null_();
        //
        //if it is a scalar reurn a scalar
        if (is_scalar($value)) return new scalar($value);
        //
        //This must be an unknown data type
        return new myerror("Invalid data type at current row for $tname.$cname");
    }
    
     //A lookup can pass or fail ntegrity checks
    function pass_integrity(&$error):bool{
        //
        //Get the table and column names for the lookup
        $tname = $this->tname; $cname = $this->cname;
        //
        //Get the string version of this expression
        $lookupstr = "lookup($tname, $cname)";
        //
        //The named table must exist in the current questionnaire
        if (!isset(\questionnaire::$current->tables[$tname])){
            $error = new myerror(
                "Table name '$tname' in $lookupstr is not known"
            );
            return false;
        }    
        //
        //Get the actual milk table
        $table = \questionnaire::$current->tables[$tname];
        //
        //The named column must be exist in the table
        if (!isset($table->header[$cname])) {
            $error =  new myerror(
               "Column '$cname' not found in table '$tname'"     
            );
            return false;
        }
        //
        //Integrity test is passed
        return true;
    }
}

//Modelling the concatenation function
class concat extends expression{
    //
    //The contantenation arguments
    public array $args;
    //
    function __construct(...$args){
        //
        //Convert the arguments to expressions
        $this->args = array_map(fn($arg)=>expression::create($arg), $args);
        parent::__construct();  
    }
    
    //When a function is simplified, we get a literal or 
    //undefined
    function simplify():ans{
        //
        //Simplify all the arguments
        $args = array_map(fn($arg)=>$arg->simplify(), $this->args);
        //
        //The concat function result is undefined if any of its 
        //simplified arguments is not a scalar
        $undefineds = array_filter($args, fn($arg)=>!$arg instanceof scalar);
        
        //
        //Test for undefined arguments and return the erroneous arguements if 
        //any
        if (count($undefineds)>0){
            //
            //Compile the undefineds to a friendly message
            $strs = array_map(fn($undef)=>"$undef", $undefineds);
            //
            //Join the strings, sepated by a comma
            $msg = implode(", ", $strs);
            //
            //Simplification is not possible
            return new myerror("Error in concat arguements: $msg");
        }
        //
        //Perform the concat operation
        //
        //Get the bits to concatenate
        $strs = array_map(fn($arg)=>$arg->value, $args);
        //
        //Do the concatentation and return the simple scalar
        return new scalar(implode("", $strs));
    }
    //
    //The concat expression yields as many tables as its arguments
    function collect_tname(array &$tnames):void{
        //
        foreach($this->args as $arg){
            //
            $arg->collect_tname($tnames);
        }
    } 
    
    //A concat expression passes the integrity test if all its arguments
    //pass the test
    function pass_integrity(&$error):bool{
        //
        //Test all the arguments
        //
        //Start with an empty list of error collection
        $errors = [];
        //
        //Loop through all the arguements and test for integrity
        foreach($this->args as $arg){
            //
            //Add the resulting error if this argument fails the test
            if (!$arg->pass_integrity($error)) $errors[]=$error;
        }
        //
        //Concan fails the integrity test if at least one of its arguments
        //fails the test
        if (count($errors)>0){
            //
            //Collect the error message
            $error = "Concat fails integrity test because of ".json_encode($errors);
            //
            return false;
        }
        //
        return true;
    }
}

//An atom is a positioned scalar;it has row and column indices
class atom extends scalar{
    //
    //Mandatory row
    public int $rowIndex;
    //
    //Optional column
    public /*int|null*/ $colIndex;
    //
    function __construct(
        int $rowIndex, 
        /*int|null*/$colIndex, 
        /*basic_value*/$value
    ) {
        //
        $this->rowIndex = $rowIndex;
        $this->colIndex = $colIndex;
        parent::__construct($value);
    } 
}

//For reporting errors in the capture namespace. It has very different 
//requirements to those of the root namespace version
class myerror extends expression implements ans{
    //
    //The error message
    public string $msg;
    //
    function __construct(string $msg){
        $this->msg = $msg;
        parent::__construct();
    }
    //
    //An error message cannot be simplified
    function simplify():ans {return $this; }
    //
    //An error does not yield any tname
    function collect_tname(array &$tnames):void{}
    //
    //An error has no checks for integrity. So, it always pass the integrity 
    //tests
    function pass_integrity(&$error):bool{return true; }
    
    //The string representation of an error
    function __toString(): string {
        return "Error. $this->msg";
    }
}

//Modelling prepared and ordinary sql staments for capturing data
abstract class statement{
    //
    //Types of statements: ormal or prepared
    const normal = "normal";
    const prepared = "prepared";
    //
    //The base of this statement    
    public artefact $artefact; 
    //
    //The pdo statement handle
    public \PDOStatement $handle;
    //
    //The columns of this statement that must be bound
    //before this statement is executed
    public array /*<column>*/$columns;
    //
    function __construct(artefact $artefact){
        //
        //Save the input artefact for this statement.
        $this->artefact = $artefact;
        //
        //Set this statement's columns that need to be bound
        $this->columns = $this->get_columns();
        //
        //Prepare the statement and set the handle
        $this->handle = $this->prepare_statement();
        //
        //Use the handle to bind parameters
        $this->bind_parameters();
    }
    
    //Prepare this statement, set the handle and bind her parameters
    function prepare_statement(): \PDOStatement{
        //
        //Get the sql text fit for preparing this statement. Yes, a prepared,
        //rather than ordinary statement is rquired
        $this->stmt = $this->sql($this->columns, true);
        //
        //Prepare its pdo handle
        $handle = $this->artefact->dbase->pdo->prepare($this->stmt);
        //
        return $handle;
    }
    
    //The prepared or normal sql text is driven by the given columns
    abstract function sql(array $columns, bool $prepared):string;
    
    //Returns the columns of this statement that need to be bound 
    function get_columns():array/*<column>*/{
        //
        return array_filter(
            //
            //Select from the underlying artefact all those columns that...
            $this->artefact->columns, 
            //
            //that are fit to participate in a bound stateent. Yes, this is the 
            //case if the column...
            function($col){
                //
                //For debugging purposes...
                if (
                    $this instanceof update
                    && $this->is_cross_member    
                    && $col->ename=='todo'
                    && $col->name =='developer'    
                ){
                  //Do nothig
                  $x = null;  
                }
                //
                //
                //
                $yes = //
                    //...is not a primary key, as this does not take part in data 
                    //export
                     !($col->source instanceof \primary)
                    //
                    //...and must partcipate in the current questionnnaire. 
                    && (
                        //An sttribute participates if its expression is not  
                        //erroneous 
                        (isset($col->exp) && !($col->exp instanceof myerror))
                        //
                        //A foreigner particiates if it points to a valid artefact
                        || (isset($col->nearest) && $col->nearest instanceof artefact)
                    )
                    //
                    //...and must satisfy the statement specific condition.
                    // E.g., a cross_member column is valid only for cross 
                    // member update
                    && $this->is_valid($col);
                //
                return $yes; 
            }            
        );
    }
        
    //The statement specific condtion for a column to take part in the
    //binding process. E.g., ???
    abstract function is_valid(column $column):bool;
    
    //Bind the all the parameters of this statement
    function bind_parameters(){
        //
        //Bind the column selection parameters to their scalar values
        foreach($this->columns as $column){
            //
            //Bind the named column 
            $this->bind_parameter(":{$column->source->name}", $column->scalar);
        } 
    }
    
    //Execute a statement using either a normal or prepared statement 
    //to return an expression as the result. The prepared version is used
    //only when all the columns bound to parameters of this statment are
    //valid; othersise the ordinary one is used.    
    //Typically the result is a primary key value, error, or null
    function execute():ans{
        //
        //Collect the result for saving all the columns bound to this 
        //statement that were truely saved.
        $cols = array_filter(
            $this->columns, 
            fn($col)=>$col->answer instanceof scalar
        );
        //
        //Count the valid cases
        $all_valids = count($cols);
        //
        //Prepare for successful or failed execute -- depending on
        //how many columns were validly saved
        //
        //No bound column of this statement is valid
        if ($all_valids==0) {
            //
            //Handle the case of empty inputs. Only for update is this
            //not an error
            return $this->handle_empties(); 
         }
        //
        //The bound columns are fewer than expexted
        if($all_valids<count($this->columns)){
            //
            //We can still execute this statement but using the normal
            //rather than the prapered version.
            return $this->handle_fewer();
        }
        //
        //Use the prepared statement to execute this statement
        try{    
            $this->handle->execute();
            //
            //Return the answer to a successful execute. The select statement
            //returns either a primary key or null; the nsert returns the 
            //last inserted id and the update returns the primary key of
            //the underlying artefact
            $answer = $this->handle_success($this->handle);
        }    
        catch(Exception $ex){
            //
            //Return the exception error as the answer
            $answer = new myerror($ex->getMessage());
        }
        //
        return $answer;
    }
    
    //Handle the cases when there are no valid columns bound to 
    //parameters of this statement.
    abstract function handle_empties():ans;
    
    //Handle the case of a successful execute. Generally, this returns the
    //answer associated with the primary key, but for insert, its the last 
    //inserted id, as an expression
    abstract function handle_success(\PDOStatement $stmt):ans;
    
    //Handle the cases where the number of bound columns is fewer than
    //the available data (expressions). Gnerally, this executes a non prepared
    //statement. Throwing of an exception is specific to the select statement
    function handle_fewer():ans{
        //
        //Collect columns of this statement that have valid data 
        $columns = array_filter(
            $this->columns, 
            fn($col) => $col->answer instanceof scalar
        );
        //
        //Use the columns to formulate an ordinary(note prepared) statement .
        //(true is for prepared statements)
        $sql = $this->sql($columns, false);
        //
        //Query the associated database, returning a temporary pdo statement
        $stmt = $this->artefact->source->dbase->query($sql);
        //
        return $this->handle_success($stmt);
    }
    //
    //Bind the given parameter to the given variable
    function bind_parameter(string $parameter, &$variable):void{
        //
        if (!$this->handle->bindParam(
            //    
            $parameter ,
            //    
            $variable
            //
            //All mysql paremeters are assumed to be of type string
            //int $data_type = PDO::PARAM_STR, 
            //
            //What is the importamce of these?
            //int $length = ? , 
            //mixed $driver_options = ? 
        )){
            throw new \Exception("Unable to bind parameter $parameter in {$this->sql()}");
        }
    }    
        
    //
    //How to export data using a statement
    function export():expression{
        //
        //Decide which form of execute you want -- prepaed or normal 
        //query
        try{
            if ($prepared){
                $this->handle->execute();
            }else{
               $pdo->query($sql); 
            }
            //
            $result = $this->get_result();
            
        } catch (Exception $ex) {
            $result = new myerror($msg);
        }
            
        //Return the result
        return $result;
    }
}

//Modelling the prepared and normal insert statements as a function of all
//data capture columns that are not cross members
class insert extends statement{
    //
    function __construct(artefact $artefact) {
        parent::__construct($artefact);
    }
    //
    //A column is considered valid for insert if it is not a cross member
    function is_valid(column $col): bool{
        return !$col->source->is_cross_member();
    }
    //
    //Return the insert statement which goes like...
    //
    //INSERT INTO $ename ($cname, ...) VALUES($string, ...)
    //
    //As this may be a prepared or normal statements, the participating
    //columns must be provided
    function sql(array $columns, bool $prepared):string{
        //
        //Get the array indexing column names
        $columns_names = array_keys($columns);
        //
        //Map the column names a backtick enclosed comma separated list.
        $column_str = implode(
            //
            //Use the comma as a separator    
            ',', 
            //
            //Enclose names with backticks    
            array_map(fn($cname) => "`$cname`", $columns_names)
        );
        //
        //Collect all the values as a comma separated string
        $values_str = implode(
            //
            //Use the comma separator    
            ',', 
            //
            //The inserts may be either parameters or actiual values
            array_map(
                fn($col) => $prepared ? ":$col->name": "'{$col->scalar}'", 
                $columns
            )
        );
        //
        //3. Compile the sql insert statement
        //
        //sql string statement
        return "INSERT \n"
                //
                //Get the from 'table' of the sql 
                . "INTO  $this->artefact\n"
                //
                //the column names
                . "($column_str)\n"
                //
                //The values
                . "VALUES ($values_str)\n";   
    }
    
    //
    //Handle the case of a succesful insert where we return the last inserted
    //id as an expression
    function handle_success(\PDOStatement $stmt):ans{
        //
        //The primary key is the last insert id
        $pk = $this->artefact->dbase->lastInsertId();
        //
        //Formulate it as a literal expression and flag it as an
        //insert. This is used later to figure out if the parimary
        //key was derived via an insert or an update
        return new scalar($pk, "insert");
    }
    
    //It is a sign of a problem if there is notinh to insert
    public function handle_empties(): ans {
        throw new \Exception("An empty insert statement is not expected");
    }
}

//The prepared and normal select statement is a function of all
//columns of the associated index
class select extends statement{
    //
    //The index that characterises this select 
    public index $index;
    //
    function __construct(artefact $artefact, index $index) {
        //
        $this->index = $index;
        parent::__construct($artefact);
    }
    //The columns of a select statement are those of its associated index
    //Note that this method overrides the generalized version
    function get_columns():array/*<schema\columns>*/{
        //
        return $this->index->columns;
    }
    //
    //Implement the required (abstract) method. Its a sign of a problem
    //if you ever call this method. Why? Because get_columns is implemeneted
    //directly for the select case
    function is_valid(column $col):bool{
        throw new Exeption("A call to this method 'is_valid' for column '$col->name'is not expected");
    }
    
    //The sql text of a select statement in the context of data 
    //capture tries to retrieve a primary key for a know set of
    //indexing column values, i.e.,
    //SELECT $p FROM $ename WHERE $indexers
    function sql(array $columns, bool $prepared):string{
        //
        //The where condition is an array of "anded" facts based columns 
        //of this statement's index.
        //
        //Starting with an empty list of "ands"...
        $condition = [];
        //
        //...build the where conditions.
        foreach ($columns as $col) {
            //
            //Compile an ordinary or parametrized value
            $value = $prepared ? ":$col->name": "'{$col->scalar}'";
            //
            //Create a where clause in the form, e.g., "`cname`= '1'";
            $condition = "`$col->name`=$value";
            //
            //push the new "where" into the array
            $conditions[] = $condition;
        }
        //
        //Stringify the where array in order to formulate a complete where 
        //clause string 
        $where = implode(' and ', $conditions);
        //
        //Formuate tehsql statement to test for existence of abscence of a record
        return "SELECT\n"
                //
                //Ensure this is teh primary key
                ."\t$this->artefact.`{$this->artefact->source->name}`\n"
            . "FROM\n"
                . "\t$this->artefact\n"
            . "WHERE\n"
                ."$where";
    }
    
    //A select statement used by an index for retriving an identified record
    //cannot be empty
    function handle_empties():ans{
        return new myerror("Select statement for index {$this->partial_name->name} has empty columns");
    }
    //Handle the cases where the number of bound columns is fewer than
    //the available data (expressions). Gnerally, this executes a non prepared
    //statement, but for select, this should throw the incomplete index error
    function handle_fewer():ans{
        //
        //Collect all the columns of this statement that are erroneous
        $columns = array_filter(
            $this->columns, 
            fn($col)=>!($col->answer instanceof scalar)
        );
        //Get the column names
        $names = array_map(fn($col)=>$col->name, $columns);
        //
        //Convet the name array text
        $strs = json_encode($names);
        //
        return new myerror(
            "Incomplete index. Data for columns, $strs, is missing or erroneous"
        );
    } 
    
    //For a successful select, return the result ot the select. Its ether the
    //primary key scalar or a null if no record selected
    function handle_success(\PDOStatement $stmt ):ans{
       //
       //Use the handle of this statement to fetch the only record
       $result = $stmt->fetchAll(\PDO::FETCH_NUM);
       //
       //Return a null expresssion, if the result is empty
       if (count($result)==0) return new \null_(); 
       //
       //It is an error to return more than one value
       if  (count($result)>1) 
           throw new \Exception("Multiple values in the select statement:<br/>{$this->handle->queryString}");
       //
       //Convert the only result to a scalar and return 
       return new scalar($result[0][0]);
    }
   
}

//The prepared and normal update statement is driven by all
//capture columns that are either:-
//(a) cross members only or
//(b) not-cross members.
class update extends statement{
    //
    //Is this a prepared statemet or not?. 
    /*
        //A prepared statement uses all columns of the statement as parameters??
        {type:'prepared'} 
        
        //A normal statement has no parameters to bind     
        |{type:'normal', columns:array<column>}
        
        //If no columns are available to update, then the statement is marked
        //as such
        |{type:'none'}
     */
    public array $preparedness;
    //
    //The indicate if this update is for cross members or not
    public bool $is_cross_member;
    //
    function __construct(artefact $artefact, bool $is_cross_member) {
        //
        $this->is_cross_member = $is_cross_member;
        //
        parent::__construct($artefact);
    }
    
    //Override the binding of parameters to add the where one
    function bind_parameters(){
        //
        //Bind the column selection parameters to their scalar valies
        parent::bind_parameters();
        //
        //Bind the entity limiting parameter in the where clause
        //
        //Get the primary key of this statement's artefact
        $pk = $this->artefact->pk();
        //
        //Bind the primary key scalar, under the matching parameter name
        $this->bind_parameter(":$pk->name", $this->artefact->pk()->scalar);
    }
           
    //A column is considered valid for binding in a statement depending 
    //on the request, i.e., whether cross member or not
    function is_valid(column $col):bool{
        // 
        //A column should not be considered for update if it is defaulted. This 
        //means if...
        $defaulted = fn($col)=>
           // 
           //...there is no user supplied data associated with it....
           !isset($col->exp)
           //
           //... and it is a attribute... 
           && $col->source instanceof \attribute
           //
           //...with a predefined default value.
           &! is_null($col->source->default);
        //
        //A column is valid for update if...
        return  
            //...its cross member status match the current request...
            $col->source->is_cross_member() == $this->is_cross_member
            // 
            //..and it is not defaulted (See above for definition of defaulted)
            &! $defaulted($col);    
    }
    
    //
    //Returns the normal or prepared sql update statement:
    //UPDATE $table SET $column_values WHERE $condition
    function sql(array $incolumns, bool $prepared):string{
        //
        //1. Formulate the SET clause
        //
        //Select the columns that participates in the sql; they are either
        //all the columns of this statement, if we are preparing the 
        //statement, or only the selected ones.
        $columns = $prepared ? $this->columns: $incolumns;
        //
        //Begin with an empty set clause
        $set = [];
        //
        //Loop through the select columns pairing their names with their 
        //respective values, e.g., `name`=:name for prepared cases,
        //otherwise `name`='kamau' 
        foreach ($columns as $cname => $column) {
            //
            //Depending on the type, get the colum's value
            $value = $prepared
                //The column's value is a colon prefixed name for prepared 
                //statement...    
                ? ":$cname"
                //
                //..or the actual quoted scalar value for a normal statement     
                : "'{$column->scalar}'";
            //
            //Populate the set clause with the value/pair
            array_push($set, "`$cname` = $value");
        }
        //
        //Convert the set array to a comma separated text as required
        //for the clause
        $str_set = implode(',', $set);
        //
        //2. Formulate the WHERE value
        //
        //The where's condition is either bound to a parameter or set 
        //to scalr value of this statement's artefact's primary key value
        // -- depending on its preparedness
        $pk = $prepared
            //    
            //The primary key is either the name of the bound parameter, 
            //e.g., :client
            ? ":{$this->artefact->name}"
            //
            //...or the actual value 
            : "{$this->artefact->pk()->scalar}";
        //
        //This is an update statement 
        $text = "UPDATE \n"
                //
                //Update this entity using the fully sql qualifield name
                //e.g. `mutall_user`.`developer`    
                . "\t{$this->artefact} \n"
            ."SET \n"
                //
                //The update values as a set of anded key-value pairs 
                . "\t$str_set \n"
            //
            //The joins, if any;for now there is none
            //
            //The where condition
            . "WHERE\n"
                . "\t$this->artefact.{$this->artefact->name}= $pk\n";
        //
        //Return the sql text
        return $text;    
    }
    
    //It is not an issue, if there is nothing to update. jyst return 
    //the primary ey of the underlying artefact
    function handle_empties():ans{
        return $this->artefact->pk()->answer;
    }
    
    //For a successful update, return the primary answer
    function handle_success(\PDOStatement $stmt):ans{
        //
        //Get the primary key as a scalar answer
        return $this->artefact->pk()->answer;
    }
    
}

//
//Models the index of an entity (needed for unique identification of database 
//entries) as a schema object. That means that it is capable of writing to a 
//database
class index extends \schema {
    //
    //Theindex ame
    public string $ixname;
    //
    //The artefact that is the base for this index
    public artefact $artefact;
    
    //The columns of thisindex
    public array /*<capture\column>*/$columns;
    //
    function __construct(
        //
        //Name of ths index
        string $ixname,        
        //
        //The parent artefact    
        artefact $artefact
    ){
        $this->xname = $ixname;
        $this->artefact = $artefact;
        //
        //Compile the partial name of this index
        $partial_name= "{$artefact->source->name}.$ixname";
        parent::__construct($partial_name);
        //
        //Map the incoming cnames to columns of this artefact
        $this->columns = array_map(
             fn($cname)=>$artefact->columns[$cname], 
             $artefact->source->indices[$ixname]
        );
    }

    //Returns the string version of this index ???? 
    function to_str(): string {
        return "`$this->ename`";
    }

    //Returns the ename of this index???????
    function get_ename(): string {
        return "`$this->ename`";
    }

    //Define the entities of this index as a function. This cannot be defined
    //as a propertu because of recurssion during serialization.
    function entity() {
        //
        //Open the database of the this index
        $dbase = $this->open_dbase($this->dbname);
        //
        //Retrive the entity mathing this indx
        $entity = $dbase->entities[$this->ename];
        //
        //Return it.
        return $entity;
    }
    
    //
    //Save the current record using this index. 
    // 
    //If any column is erroneous, this index cannot be used for saving;
    //otherwise we use use the index columns to either insert or update 
    //this record.
    function write(/*row|null*/$row): ans {
        //
        //Collect all the invalid scalars of this index
        $invalids= array_filter(
            $this->columns, 
            fn($col) => !($col->answer instanceof scalar) 
        );
        //
        //Test if this index is valid to save the current record. An index is
        //invalid if at least one of its columns is erroneous
        if (count($invalids) > 0) {
            //
            $col_str = implode(',', array_keys($invalids));
            //
            //At least one indexing column is erronoeus. The index is unusable.
            return new myerror(
                "Unusable index: one of its columns, $col_str, is erroneous"
            );
        }
        //Execute the select statent for this index; the resulting expressiong
        //takes one of 3 forms:null_, answers or myerror
        $result = $this->select->execute();
        //
        //If no record was retrieved then we need to insert one. 
        if ($result instanceof \null_){
            $ans = $this->artefact->insert->execute();
        }else{
            $ans = $result;
        } 
        //
        return $ans;
    }
}
}