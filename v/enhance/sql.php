<?php
//Merging the sql vo and v partial
//
//This is where the schema structural database is defined with 
//entities and columns 
include_once 'schema.php';
//
//This class models derived foreign key fields.
class field_foreign extends field implements iref{
    //
    //
    //The referenced entity identifier comprising of two components the
    //reference database and table names.
    public stdClass $ref;
    //
    //
    function __construct(
        //
        //The home database name.
        string $dbname,
        //
        //The home entity name.
        string $ename,
        //
        //The fields name.
        string $fname,
        //
        //The field's expression.
        expression $exp,
        //
        //The referenced entity identifier.
        stdClass $ref
    ){
        //
        parent::__construct($dbname, $ename, $fname, $exp);
        //
        $this->ref = $ref;
    }
    //
    //Returns the entity that the foreign key is pointing 
    //at i.e., the referenced entity.
    public function away():entity {
        //
        //Get the referenced dbname.
        $dbname = $this->ref->dbname;
        //
        //Get the referenced database.
        $dbase = $this->open_dbase($dbname);
        //
        //Get the referenced ename.
        $ename = $this->ref->table_name;
        //
        //Get the referenced entity.
        $entity = $dbase->entities[$ename];
        //
        return $entity;
    }
    //
    //The home method returns the entity in which the 
    //foreign key is housed. It is indicated with a chicken foot
    //in our data model.
    public function home():entity {
       //
       //Get the name of the home database
       $dbname = $this->dbname;
       //
       //Get the actual home database.
       $dbase = $this->open_dbase($dbname);
       //
       //Get the home entity name.
       $ename = $this->ename;
       //
       //Get the actual home entity.
       $entity = $dbase->entities[$ename];
       //
       return $entity;
    }
    //
    //
    public function write(/*null|row*/$row): \answer {
    }
}
//The class is used to suport the selection of one record of an entity
//This is a type of select sql that retrieves all the id columns of an entity. 
//with all the foreign columns  resolved to their friendly values.
class selector extends view{
    //
    //The source of the sql that derives the "from clause" for the view and it 
    //also serves as the source of this class's identifier network 
    protected table $source;
    //
    function __construct(
         //
         //The name of the entity that is the base of the selector
        string $ename, 
        //
        //The name of the database where the entity is located
        string $dbname,
        //
        //The friendly column separator
       string $separator='/'    
    ){
        //
        //
        $this->separator=$separator;
        $this->dbname=$dbname;
        $this->ename=$ename;
        //
        //The name of this selector is the same as that of the entity with a 
        //selector sufix 
        $this->name =$ename.'_selector';
        //
        //Get the source entity
        $dbase = $this->open_dbase($dbname);
        $this->source= $dbase->entities[$ename];
        //
        //Prepare the view constructor variables $columns and $join; they are
        //critial for converting a selector to an sql string.
        //
        //1. Start with the join, as the columns can be derived from it.
        //
        //The network of join paths for a selector can be constructed by executing
        //an identifier network.
        //
        //Create an identifier network; its source is the entity associated with
        //this selector.
        $network = $this->get_network();
        //
        //Create a new join 
        $join = new join($network->paths);
        //
        //2. Derive the fields of selector. They are 2: the foreign key and 
        //its friendly name.
        $columns = [
            //
            //This is required for linking the selector view to the relational 
            //network
            $this->get_foreigner(),
            //
            //The friendly name comprising of the identification attributes of 
            //this view's joint
            $this->get_friend()
        ];
        //
        //The where clause is omitted to allow us to modify the resulting
        //sql satement depending on further need, e.g., adding offset, 
        //filterimg, sorting, etc.
        parent::__construct($this->source, $columns, $join, $this->name);
    }
    //
    //Return the identification network used for compiling the 
    //selector joints
    function get_network() {
        return new identifier($this->source);        
    }
    
    //Returns the foreign key column that links the selector to the
    //source entity
    private function get_foreigner():field{
        //
        //Use the source to get the entity and database names of this selector 
        $ename=$this->source->name;
        $dbname=$this->source->dbname;
        //
        //The ref_table name is the source entity while the source dbname is the 
        //this dbname 
        $ref = new \stdClass();
        $ref->table_name= $ename;
        $ref->db_name=$dbname;
        //
        //The name of this foreigner, by convention, should be the same as  that 
        //of the entity it references. 
        $cname = $ename;
        //
        //Create and return the foreign key.   
        $f= new foreign(
            $dbname,
            $ename,
            $cname,
            //
            //The datatype of a foreign key is always an integer
            "INT",
            // 
            //No default value for a foreign key
             null,
            //
            //The selector foreign key is mandatory
            "NO",
            //
            //Idicate that this foreign key is used for identification.
            '{"is_identifier":true}',
            //
            //The length of this foreign key
            11,
            //
            $ref
        );
       return new field($this->dbname, $this->ename, $this->name, $f);
    }
    //
    //Returns an expression which when evaluated gives a 
    //separated list of the friendly columns. 
    function get_friendly_id(entity $entity): expression{
        //
        //Collect all the friendly parts (including the seperator);
        $expressions = 
                iterator_to_array($entity->get_friendly_part());
        //
        //Guard against potential errors 
        if(count($expressions)===0){
            throw new Error("Entity '{$entity}' has no friendly parts. "
            . "Check your index");
        }
        //
        //Define a separator expression
         $sep= new literal($this->separator);
        //
        //Insert the separator between the expressions
        $separated_expressions=[];
        foreach ($expressions as $dirty_exp) {
            // 
            //Ensure there are no white spaces in the expression
            //$exp=new function_("trim", [$dirty_exp]);
            
            // 
            //Ignore the leading separator 
            if(count($separated_expressions)!==0){
                array_push($separated_expressions, $sep);
            }
            //
            array_push($separated_expressions, $dirty_exp);            
        }
        //
        //Define the concat function
        return new function_('concat', $separated_expressions); 
    }


    //Returns the concat function, as field, used for implemneting the friendly 
    //name colum of this selector's entity, given the name of this selector
    private function get_friend():field{
        //
        //Let  the field name of the freindly column be simply id
        $fname = "id";
        //
        //Get the comma separated friendly columns
        $exp=$this->get_friendly_id($this->source);
        //
        //Define the column of type field
        $field = new field($this->dbname, $this->name, $fname, $exp);
        //
        //Return the field
        return $field;
    }
    
    //Convert the given column to a field used for deriving values of the 
    //following form: [5, "chic/25/2005-10-05"]
    //The general form is defined by the following 7 arguments: 
    //  ob, $primary, comma, dq, $friendly, dq, cb 
    //where  tokens  ob means open bracket, comma is obvious, dq doble quote
    //and cb means closing bracket
    static function befriend(column $primary, expression $friend):expression{
        //
        //Opening bracket
        $ob= new literal('[');
        //
        //The comma token
        $comma= new literal(',');
        //
         //Double quote
        $dq= new literal('"');
        //
        //The closing bracket token
        $cb= new literal(']');
        //
        //Compile all the argumets
        $args = [$ob, $primary, $comma, $dq, $friend, $dq, $cb];
        //
        //We use the concat function to join all the expression parts
        return new function_('concat', $args);
    }
}

//This class models an sql statement extends that selector one. It retrieves 
//all the columns of an entity. In particular the primary and foreign keys 
//values are accompanied by their friendly names that makes it much easier to
//to edit them.
class editor extends selector{ 
    //
    function __construct(
        //
        //This is the entity name from which we are doing the selection
        string $ename,
        //
        //The name of the database in which the entity is defined
        string $dbname
    ){
        //
        parent::__construct($ename, $dbname);
        //
        //Override the selector`s name
        $this->name = $ename."_editor";
        //
        //Extend the parent joints with left ones, derived from the subject`s 
        //cross_members i.e.,optional non-identifier foreign keys(cross-members)
        $this->add_left_joints(); 
        
        //
        //Override the the parent selector fields. Those of an editor are
        //derived from columns of the source entity
        $columns = array_map(
            fn($col)=>$this->get_editor_column($col),
            $this->source->columns    
        );
        //
        $this->columns = $columns;
        
    }
    //
    //Extend the parent joints with left ones, derived from the subject`s 
    //cross_members i.e.,optional non-identifier foreign keys(cross-members)
    private function add_left_joints(): void{
        //
        //Get all the cross members of the subject entity
        $cross_members= array_filter($this->source->columns, fn($col)=>
            $col->is_cross_member()
        );
        //
        //Use the cross members to extend this editor`s joints
        array_walk($cross_members, fn($fk)=> $this->add_left_joint($fk));

    }
    //
    //Add a left joint to the current editor given the foreign key 
    private function add_left_joint1(foreign $fk):void{
        //
        //i) Editor is view that we are currently constructing
        $editor = $this;
        //
        //ii) Create a selector view from the away component of the foreign 
        //key 
        $selector = new selector($fk->away()->name,$fk->away()->dbname);
        //
        //iii) Let $subject be the entity being edited
        $subject = $this->source;
        //
        //The name of the leftie view is the same as that of the subject 
        //prefixed by the term posted 
        $leftie_name = $selector->name;
        //
        //The leftie, select and the subject are all based on the same database 
        //hence share thw same dbname
        $dbname=$subject->dbname;
        //
        //The referenced entity is a std class with two properties. viz,. dbname,
        //table_name
        $ref= new stdClass();
        $ref->dbname=$dbname;
        $ref->table_name=$subject->name;
        //
        //Get the subject primary key expression
        $pk_exp= $subject->columns[$subject->name];
        //
        //
        $subject_pk = new field_foreign($dbname, $leftie_name, $subject->name, $pk_exp, $ref);
        //
        //The friendly component of a selector.
        $sf_exp= $selector->columns['id'];
        $selector_friend= new field($dbname, $leftie_name, "id", $sf_exp->exp);
        //
        //4. The leftie's columns are fields viz,. the selectors' friend 
        //and the subject's primary key.
        $fields=[$subject_pk/*foreign key*/, $selector_friend/*attribute*/];
        //
        //The object is the away entity of the incomming foreign key 
        $object= $fk->away();
        //
        //The anchor is the foreign key that links the selector to 
        //the object entity. 
        $anchor = $selector->columns[$object->name];
        //
        //The join of the leftie is formed by walking from the subject to 
        //the selector though the object 
        $path = [$fk, new pointer($anchor)];
        //
        //Remmebr that a join takes a double array
        $join = $selector->join;
        //
        //3. Create the view that is to be involved in the left join. It is 
        //defined as, all the subjects whose foreign key column, $fk, is not 
        //null, e.g., if the subject was water readings and the foreign key was
        //posted then the leftie would be the posted_wreadings. 
        $leftie= new view($selector->source, $fields, $join, $leftie_name);
        //
        //The hand is the pointer from the leftie to the subject 
        $hand = $leftie->columns[$subject->name];
        //
        //2. Create the left joint using the $hand to link link the $leftie to
        //the $subject.
        $joint=new joint($leftie, "left", [$hand]);
        //
        //1. Extend the joints with this editor 
        $editor->join->joints[$joint->base->partial_name] = $joint;
    } 
    //
    //Add a left joint to the current editor given the foreign key 
    private function add_left_joint(foreign $fk):void{
        // 
        //Create the selector query to be used for formulating the left join 
        $selector = new selector($fk->away()->name, $fk->away()->dbname);
        // 
        //Join this selector to the this editor as a left joint 
        //
        //Define the foreign key that links this selector to the parent entity 
        //
        //The referenced entity is a std class with two properties. viz,. dbname,
        //table_name
        //This ref only has a table name since view are not referenced by their 
        //database names.
        $ref= new stdClass();
        $ref->table_name= $selector->name;
        //
        $exp =$selector->columns[$selector->name];
        $hand = new field_foreign($this->dbname, $this->ename, $fk->name, $exp, $ref);
        //
        //formulate the left joint 
        $joint=new joint($selector, "left", [$hand]);
        //
        //1. Extend the joints with this editor 
        $this->join->joints[$joint->base->partial_name] = $joint;
    } 

    //
    //Map the given column to an editor version. Attributes 
    //do not change; primary and foreign keys become befriended, i.e., 
    //they will be accompanied by friendly ids. These are expressions 
    //designed to support editing of foreign keys
    private function get_editor_column(column $col): column/*col_out*/{
        //
        //An ATTRIBUTE does not "change" 
        if($col instanceof attribute){ $exp=$col;}
        //
        //A PRIMARY key column maps to an expression that evaluates to a
        //tuple of the following form: [1, "chic/25/2005-05-01"]. This is 
        //derived from the 2 columns of the parent selector
        elseif($col instanceof primary){
            //
            //Use the parent selector columns to formulate the concat 
            //expression. Note that the friendly component of a selector
            //is called id.
            $exp= self::befriend($col, $this->columns['id']->exp);
        }
        //
        //A FOREIGN key column maps similarly to a primary key, except that
        //the friendly component is derievd from the away entity of the colum
        elseif($col->is_id() || (!$col->is_cross_member())){
            //
            //Identifier fields should be formulated using existing joints (to 
            //save time)
            //
            //Get the away friendly parts of this foreign key
            $friendly_parts = iterator_to_array(
                $col->away()->get_friendly_part()
            );
            //
            $friend = new function_("concat", $friendly_parts);
            //
            $exp= selector::befriend($col, $friend);            
        }else{ 
            //Non-identifiers should be formulated from scratch using a 
            //fresh selector 
            //
            //Use the column's ref property to formulate a selector query
            //$selector = $this->join->joints->get($col->away()->partial_name);
            $selector= new selector($col->away()->name, $col->away()->dbname);
            //
            //Return the selector as a named expression
            //Create a new field foreign this is because of the special naming this 
            //column has
            $field= new field_primary($selector->dbname, $selector->name, "id", $selector->columns["id"]);
            $exp = self::befriend($col,$field);
        }
        return new field($this->dbname, $this->name, $col->name, $exp);
    }
    //
    //This methods returns metadata necessary to drive the CRUD service.
    function describe():array/*[dbase, cname[], sql, max_records]*/{
        //
        //Get the database
        /*database*/$dbase = $this->open_dbase($this->dbname);
        //
        //Get the SIMPLE (NOT INDXED) array of column names.This is best
        //done by looping over rather than mapping
        $cnames=[];
        //
        foreach($this->columns as $column){
           array_push($cnames, $column->name);
        }
        //
        //
        //Ensure yoyu use the 'from' expresssion, raher than teh statement 
        /*string*/$from = $this->to_str();
        //
        //Modify sql to get a count
        $mysql = "select count(*) as records from $from";
        //
        //Run the query to get the maximum records
        //
        //Get the only record
        $rec1 = $dbase->get_sql_data($mysql)[0];
        //
        //Retrieve the count
        $max_records = $rec1['records'];
        //
        //Return the metadata.
        return [$dbase, $cnames, $this->stmt(), $max_records];
    } 
    //
    //Return the dependency network used for compiling the 
    //both the identification and the mandatory joints
    function get_network() {
        return new dependency($this->source);
        
    }
    //Present the editor sql to support editing of tabular data
     function show(string $where="", string $order_by=""):string {
        //
        //Execute this sql 
        $array= $this->get_sql_data($this->dbname);
        //
        //Ouptut a table
        echo "<table id='fields' name='{$this->source->name}'>";
        echo '<thead>';
        echo $this->show_header();
        echo '</thead>';
        echo '<tbody id="table-body">';
        //
        //Loop through the array and display each row as a tr  element
        foreach ($array as $row) {
        $id= "{$this->source->name}";
            //
            echo "<tr onclick='record.select(this)' id='$id'>";
            //
            //loop through the column and out puts its td element.
            foreach ($this->source->columns as $col){
                //
                //Get the value from the row, remebering that the row is indexed
                //by column name
                $value = $row[$col->name];
                //
                //
                echo $col->show($value);
            }
            //
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        return "";
    }
}

//This class formulates an sql given the inputs, e.g., fields, where, etc..,
//which do not reference any join. 
//The join is derived from the inputs to complete the sql. Hence the term parial.
class partial_select extends view{
   // 
   //Construct  a full sql, i.e., one with joins, from partial specifications 
   //of the from the conditions and the fields(i.e, without joins)
   function __construct(
        //
        //The base of the sql   
        entity $from, 
        //
        //Selected columns. Null means all columns from the source.    
        array $columns=null, 
        //   
        //The where clause expression   
        expression $where=null,
        //
        //Name of this partial sql   
        string $name=null   
    ){
       //
       //take care of the name since it must not be a null
       if(is_null($name)){$name="noname";}
       //
       //Construct the parent using the all the partial variables and a null 
       //join.
       parent::__construct($from, $columns, null, $where, $name);
    }
    
    //Execute this query by 
    //A. Evalauting and setting the join
    //B. Executing the parent to retrieve the data as an array
    function execute() {
        //
       //If the fields are null set them fields to the fields of the from
       //entity
       if(is_null($this->columns)){
           $this->columns= $this->get_default_fields();
       }
        //
        //A. Set the join that is required for the parent view that is derived from
        // the fit network
        //
        //compile the parameters of the fit network 
        //
        //Identitify target entities using the fields and where expressions 
        //(including other clauses that can potentially be associated with 
        //group_by, order_by, having.
        //
        //Start with an empty set
        $targets = new \Ds\Set();
        //
        //Yield all the targer entiteis of this view
        foreach($this->identify_targets() as $target){
            $targets->add($target);
        }
        //
        //Create a fit network; its source is this from using the target
        $network = new fit($this->from, $targets->toArray());
        //
        //Use the network to create a join 
        $this->join = new join();
        $this->join->import($network);
        //
        //Construct the fit paths using defaut settings, i.e., exceptions
        //will be thrown immediately rather than be logged)
        $this->join->execute();
        //
        //B. Now return the values from the parent execute
        return parent::execute();
    }
    
    //Compiles an array of the entities that are used in the fit network. 
    //These entities are retrieved from the fields and where clauses 
    private function identify_targets():\Generator/*$entity*/{
       //
       // 
       //Generate entities from the where clause
        if(!is_null($this->where)){
            yield from $this->wheres->yield_entity();
        }
       //
       //Loop through all the columns of this view, to generate entities from
       //each one of them
       foreach($this->columns as $col){
           //
           //only yield from the attributes and the foreign keys but not from the
           //the derived foreign of this view since it was established for 
           //linking only and hence cannot yield
           if($col instanceof foreign && $col->ename= $this->name){continue;} 
           yield from $col->yield_entity();
       }  
    }    
}

//
//Models a network of paths that are important for identifying an entity using
//attributes only, i.e., without referefnce to foreign keys. This network is 
//supports the formulaion of editor and selector views
class identifier extends network{
    //
    //
    function __construct(entity $source) {
        $strategy=new strategy_foreigner();
        parent::__construct($source, $strategy);
    }
    
    //We only utilise those foreign keys that are ids 
    function is_included(foreign $key): bool {
        //
        //return all id columns
        if($key->is_id()) return true;
        //
        return false;
    }
    
    //Returns true if the given entity does not have any foreign keys that are 
    //not cross members i.e structural foreign keys 
    function is_terminal(entity $from): bool {
        //
        //Filter the columns of the entity to remain with the foreign keys
        //that are ids
        $id_foreigners = array_filter($from->columns, fn($col)=>
             $col instanceof foreign && $col->is_id()
        );
        //
        //We are at the end of the path if the given entity has no foreign column 
        //that are id
        return count($id_foreigners)===0;
    }
}

//
//Models a network from a collection of known target entities. since it is not known 
//how the entities are related we utilise both the foreigners and the pointers 
//ie(a strategy called both see in strategy in the schema).
class fit extends network{
    //
    //The known collection of targets from which we are to get the undelying network 
    public array $targets;
    //
    //save all the visited targets in an array this is to prevent mutiple 
    //paths that are terminated by one terminal entity 
    public array $visited=[];
    //
    //To create a network we need an entity that acts as the source or origin of
    //the network see in network.
    function __construct(view $source,array /*entity[]*/$targets) {
        //
        $strategy=new strategy_both();
        //
        parent::__construct($source, $strategy);
        //
        //Initialise the targets 
        $this->targets= $targets;
    }
    
    //
    //terminate the looping if all the targets have been obtained
    function terminate(): bool {
        //
        return count(array_diff($this->targets, $this->visited))===0;
    }
//    //
//    //Yields all the paths that start from the given entity. 
//    function path_from_entity(entity $from, array/*foreigner[]*/$path):\Generator{
//        //
//        //Check if we are at the end of the path. We are if the
//        //termnal condition is satisfied
//        if ($this->is_terminal($from)){
//             //
//            //Yield teh indexed and the target name
//            yield $from->partial_name=>$path;
//        }
//        //
//        //Use the foreigner returned by executing each of the search function --
//        //depending on the current strategy
//        foreach($this->strategy->search($from) as $foreigner){
//           //
//            //For debugging, count the foreigners
//           $this->considered++;
//           //
//           //sort the foreigner to controll their order of preference  i.e 
//           //1. for the id foreigner 
//           //2. for the madatory foreigner 
//           //3.for the id pointers 
//           //4. mandatory pointers
//           //5. cross memebers 
//           //
//           //Begin with an array that has the 5 orders
//           $foreigners=[];
//           $foreigners[1]=[]; $foreigners[2]=[]; $foreigners[3]=[]; $foreigners[4]=[]; $foreigners[5]=[];
//           //
//           $this->sort_foreigners($foreigner,$foreigners);
//           //
//        }
//        //
//        //loop through the foreigners in their order of preference begigning from 
//        //the id columns as the most prefered 
//        for ($x=1;$x<=count($foreigners);$x++){
//            foreach ($foreigners[$x] as $foreigner){
//               //
//               // Consider the foreigner for the path being searched.
//               yield from $this->path_thru_foreigner($foreigner, $path); 
//            }
//        }     
//    }
//    
    //sort the foreigner to controll their order of preference  i.e 
    //1. for the id foreigner 
    //2. for the madatory foreigner 
    //3.for the id pointers 
    //4. mandatory pointers
    //5. cross memebers 
    private function sort_foreigners(foreign $col, array &$foreigners):array/*[order][foreigner]*/{
        //
        //1. for the id foreigner 
        //save the id column foreigns
        if($col->is_id() &! $col instanceof pointer){
           array_push($foreigners[1], $col);
           return $foreigners;
        }
        //2. for the madatory foreigner 
        //save the mandatory foreigners
        if(!$col->is_cross_member() &! $col instanceof pointer){
            array_push($foreigners[2], $col);
           return $foreigners;
        }
        //
        //3.for the id pointers 
        //save the mandatory pointers
        if($col->is_id() && $col instanceof pointer){
           array_push($foreigners[3], $col);
           return $foreigners;
        }
        //
        //4. mandatory pointers
        if(!$col->is_cross_member() && $col instanceof pointer){
           array_push($foreigners[4], $col);
           return $foreigners;
        }
        //
        //return the optional order 
        $foreigners[5][]=$col;
        return $foreigners;
    }

    //A path in the fit network comes to an end when the given entity is among the 
    //targets
    function is_terminal(entity $entity): bool {
        //
        if(in_array($entity, $this->targets)){
           //
           //return a false this entity was visited to prevent mutiple paths of 
           //a similar destination
           if(in_array($entity, $this->visited)){
               //
               return false;
           }
           //
           //save the visited
           array_push($this->visited, $entity);
           return true; 
        }
        //
        //return a false  if this etity is not among the targets
        return false;
    }
    //
    //exclude all the heirachial relationships
    function is_excluded(foreign $key): bool {
        //
        //exclude the heirachy 
        $status= $key->is_hierarchical();
        return $status;
    }


    //In a target fitting network, it is an error if a path was not found to a 
    //required target
    function verify_integrity(bool $throw_exception=true){
        //
        //Loop throu every target and report those that are not set
        foreach($this->targets as $target){
            //
            //The partial name of an entity should include the database (to take
            //care of multi-dataase situations)
            if (!isset($this->path[$target->partial_name])){
                //
                //Formulate teh error message
                $msg = "No path was found for target $target->partial_name";
                //
                if (!$throw_exception){
                    throw new \Exception($msg);
                }else{
                    $this->errors[]=$msg;
                }
            }
        }
    }
}

//The save network is needed to support indirect saving of foreign keys to a
//database during data capture. Its behaviour is similar to that of a fit
//The difference is :-
//a) in the constructor 
//b) the way we define interigity. In a fit the network has integrity when all 
//the targets are met; which is not the case with a fit.
//c) exclusion of the subject forein key fo which saving is required
class save extends network{
    //
    //The foreign key for which indirect saving support is needed
    public foreigner $subject;
    //
    //The pot is the 4 dimensional array of expressions used for capturing
    //data to a databse
    public array /*expression[dbname][ename][alias][cname]*/$pot;
    //
    //The alias to be asociated with the save process (of the foreigner)
    public \Ds\Map $alias;
    //
    //The target of a save path is a entity/pairmarykey pair that is indexd by by
    //the entties partial name. The paimary key is used for formulating where 
    //clause of a selection query.
    public array /*[entity, primarykey][partial_name]*/ $target;
    //
    function __construct(foreigner $subject, \Ds\map $alias, array /*exp[dbname]..[cname]*/$pot){
        //
        $this->subjcet = $subject;
        $this->pot = $pot;
        $this->alias = $alias;
        //
        //The starting entity for the network is the away version of the subject
        $from = $subject->away();
        //
        //Use the pot to collect entities for initializing teh parent fit
        //
        //Search the network paths using the bth the foreigners and pointers strategy.
        parent::__construct($from, network::both);
    }
    
    //A foreign key save network path comes to an end when the given entity 
    //(partial name) matches that of a target
    function is_terminal(entity $entity):bool{
        //
        return array_key_exists($entity->partial_name, $this->targets);
    }
    
    //Exclude the subject foreigner from all the save paths. Also do no 
    //include hose foreigners that pouint to referenced entoties that are for 
    //reportng puprpses
    function is_excluded(foreign $key):bool{
        //
        if ($key===$this->target) {return true;}
        //
        //Exclude foreign key fields whose away entities are used for reporting
        if ($key->away()->reporting()){ return true;} 
        //
        //Return the gerenaralized exclude
        return $this->is_exclude($key);
    }
    
    //Execute the save networtwork, first by using the pot to set the targets;
    //then excecuting the generalized version
    function execute(bool $throw_exception=true){
        //
        //Set the path targets if necessary.
        if (!isset($this->targets)) {
            //
            //Use the pot to collect the target entities of this network
            $this->targets =[];
            //
            foreach($this->collect_targets($this->pot) as $partial_name=>$target){
                $this->targets[$partial_name]= $target;
            }
        }    
        //
        //Now set the paths;
        parent::execute($throw_exception());
    }
    
    //Collect all the entities from the given pot, accompanied by their primary 
    //key values.
    protected function collect_targets(array $pot):\Generator{
        //
        //Visit all the dataases refereced by the pot
        foreach($pot as $dbname=>$entities){
            //
            //Open the database
             $dbase = $this->open_dbase($dbname);
            //
            //Loop through the entity names in the pot
            foreach(aray_keys($entities) as $ename){
                //
                //Get the namd entity from teh dtaase
                $entity = $dbase->entities[$ename];
                //
                //Check if the primary key of this aliased entity is set
                //
                //Only tose cases for which we have a primry key is considered
                if (isset($pot[$dbname][$ename][$this->alias][$ename])){
                    //
                    //Get teh primary key value
                    $primarykey = $pot[$dbname][$ename][$this->alias][$ename];
                    //
                    //Return a pair indexed by the entities partial name.
                    yield $entity->partial_name =>[$entity, $primarykey];
                }
            } 
        }
    }
}
 
//This class models the join clause of an sql staement. It has the following 
//shape:-
//  inner join $ename1 on <onclause1>
//  inner join $ename2 on <onclause2>
//  ...
//  inner join $enameI on <onclaseI>
//  ...
//  inner join $enameN on <onclauseN>
//The i'th part of a join is modeleld as a joint. 
//You must exceute a join in order for the joints to be constructed.
class join extends mutall{
    //
    //These are a double array of the foreigners that are required to formulate the 
    //targets of this join.
    public ?array /*foreigners[][]*/ $paths;
    //
    //The network from which the join targets are derived this path is optional 
    //and it can only be supplied at the import method 
    public network $network;
    //
    //The ordered list of join targets indexed by the partial entity, pename. 
    //This list is constructed when a join is executed.
    public \Ds\Map /*<pename, joint>*/$joints;
    //
    //This property is used in the import method see below it is the driving condition
    //on how to use the paths derived from the network 
    public bool $clear;
    //
    //Joins are created with on optional parameter of paths, i.e., an array of 
    //of an array of foreigners. If not provided, it is assumed that they will 
    //be derived from an imported network.  
    function __construct(?array /*Array<Array<foreigner>>*/ $paths=null) {
        //
        parent::__construct();
        //
        //Initialize the joins
        $this->joints = new \Ds\Map();
        //
        //Use the paths to populate the joints
        $this->paths = $paths;
    }
    
    //Initailize this join's paths using the given network
    //The clear parameter determines if the network paths are to be merged with 
    //the constructor paths or empty the constructor paths and establish a new 
    //set of paths 
    function import(network $network, bool $clear=false){
        //
        //Save the parameters for a latter use durring the get paths 
        $this->clear=$clear;
        $this->network = $network;
    }
    
    //Execute a join to assimilate the connection paths to this join's targets
    function execute($throw_exception=true){
        //
        //Use the paths on the cponstructor and those imported to compile
        //the paths for this join
        $paths = $this->get_paths($throw_exception);
        //        
        //Use the double array of paths to build the join clause
        $this->build_joints($paths);
    }
    //
    //Returns the array of paths that is required to derive the targets for this 
    //join.The paths can either be from the constructor or from the network or both
    //depending on the clear option
    private function get_paths($throw_exception):array /*foreigner[][]*/{
        //
        //1. Ensure that either the constructor paths or the import network is 
        //set. This is because we cannot have a join without the paths from 
        //where we  derive the targets dwrived.
        //
        //It is an error if neither is set throw an exception
        if(is_null($this->paths) &! isset($this->network)){
            throw new \Exception('The join could not be established since there'
                    . 'is no path from which we derive the targets');
        }
        //
        //2. If the paths are null but the network isset 
        //This simply means that the paths are to obtained frmm the network 
        //irrespective of the clear parameter 
        if(is_null($this->paths) && isset($this->network)){
            //
            //Execute the network to build paths 
            $this->network->build_paths($throw_exception);
            //
            //Return the paths resulting from transversing teh network
            return $this->network->paths;
        }
        //
        //3.If the paths are set but the network is a null then
        //return the constructor paths 
        if(!is_null($this->paths) &! isset($this->network)){
            //
            return $this->paths;
        }
        //
        //4. If both are set, then execute the network and decide
        //which paths to retirn based on the clear flag
        if(!is_null($this->paths) && isset($this->network)){
            //
            //Execute the network 
            $this->network->execute($throw_exception);
            //
            //If we need to clear the initial paths.. 
            if($this->clear){
                //
                //...then ignore them and return teh netwok paths only
                return $this->network->paths;
            }
            //...otherwise return both the nrtwork and initial paths
            return $this->network->paths+= $this->paths;
        }
    }
    
    //Use the given double array of paths to build the join clause
    private function build_joints(array /*<Array<foreigner>*/ $paths):void{     
        //
        //Visit each input path and use it to build the joints
        foreach($paths as $path){
            //
            //Visit each foreigner in the path and use it to build the joints
            //Ensure that its index is the ordinal position of the foreigner in 
            //the path
            foreach(array_values($path) as $position =>$foreigner){
                //
                //Add the join descendants, i.e., links and positions
                $this->add_descendants($path, $foreigner, $position);  
            }
        }
    }
    
    //Add the join's descendants following the relational join model. They
    //are:-joint, link, and position
    private function add_descendants(
        //
        //The actual path being considered for the addition
        array $path, 
        //
        //The foreign key    
        foreign $link, 
        //
        //The ordinal position of the foreigner in the path    
        int $position
    ): void{
        //
        //Get the base entity
        $entity = $link->away();
        //
        //Get the partial name of the away entity of the foreigner which is 
        //the required as the base of a joint
        $pename = $entity->partial_name;
        //
        //Add the JOINT descendant, if it does not exist
        if (!$this->joints->hasKey($pename)){
            //
            //The default join is an inner join
            $joint = new joint($entity);
            //
            //Attach the joint to this joins
            $this->joints[$pename] = $joint;
        }else{
            //The joint exists. Uee the partiaal ename key to get it
            $joint= $this->joints[$pename];
        }    
        //
        //Add the LINK descendant unconditionally. These set domain will remove
        //duplicates
        $joint->links->add($link);
        //
        //Add the POSITION descendant, unconditionally. A path cannot be added more 
        //than once without causing cyclic loops -- which should have pointed
        //way before this time.
        $joint->positions[$path] = $position;
    }
    
    //Returns a complete join clause, i.e., 'inner join $target1 on a.b=b.b and ...'
    function stmt() :string/*join clause*/{
        //
        //Convert joints map to an array, so that we can use the standard 
        //array methods for procssing it.
        $joints = $this->joints->toArray();
        //
        //Test if this array is empty; If it is then the sql does not 
        //require joins
        if(empty($joints)){return "";}
        //
        //Order the targets by ascending dependency
        $targets = usort($joints, fn($a, $b)=>$a->compare($b));
        //
        //Map each field to its sql string version 
        $joins_str=array_map(fn($target)=>$target->stmt(), $targets);
        //
        //Join the fields strings using a new line separator
        return implode("\n", $joins_str);
    }
}

//A joint is a mutall object characterised by a target entity, a.k.a., base, 
//participating in a join clause. It has the following shape:-
//...inner join $base on <Link Clause>
class joint extends mutall{
    //
    //The target entity of this joint
    public entity $base;
    //
    //The foreign keys associated with this joint, independent of the path. They
    //are modelled as set to avoid duplicates
    public \Ds\Set /*<foreigner>*/ $links;
    //
    //The positions of this joint in a path, indexed by the path. They
    //are used for re-ordering this joint before deriving the sql
    //statement
    public \Ds\Map /*<path, int:position>*/$positions;
    //
    //The type of the join for this target, e.g., inner or left or right join
    public string $jtype;
    //
    function  __construct(
        //    
        //The target entity
        entity $base,
        //
        //The default type of a join is the inner version    
        string $jtype='inner'
    ){
        parent::__construct();
        //
        $this->base = $base;
        $this->jtype = $jtype;
        //
        //Initialize the foreign key links and ordinal positions
        $this->links = new \Ds\Set();
        $this->positions = new \Ds\Map();
    }
    
    //Use depnedency to compare this and the given joint. They are equal 
    //(or independent) if they have no shared path; otherwise this one is 
    //greater the, i.e., more dependent, than the given one if it occures later, on a shared
    //path
    function compare(joint $joint):int/*0|1|-1*/{
        //
        //Get any shared paths
        //
        //Collect all the paths of this and the given joints. Remember that
        //joint postions are idxed by paths
        $As = $this->positions->keys();
        $Bs = $joint->positions->keys();
        //
        //Their intersections are the shared paths
        $paths = array_intersect($As, $Bs);
        //
        //If there is none, the two are equal
        if (count($paths)==0) return 0;
        //
        //Get any of the paths; it does ot mtater which, since they start 
        //from the same source. 
        $path = $paths[0];
        //
        //Get the comparisons, based on the pisitions of this joint on
        //a path shared with the given joint.
        $comparison =  $this->positions[$path]>$joint->positions[$path]?1:-1;
        //
        return $comparison; 
    }
    
    //Returns a complete join phrase, i.e., inner join ename on a.b=b.b
     function stmt() :string{
         //
         //The  type of the join, e.g., inner join, outer join
         $join_str = "$this->jtype join"
            //
            //Add the On clause
            . " \t {$this->base->to_str()} ON  {$this->on_str()}";
        //    
        return $join_str;
     }
     
     //Compile part of the on clause, i.e.,  x.a.d = y.d.d and c.d=d.d and ....
     private function on_str(): string{
        //
        //Map each foreigner to an equation string, taking care of multi-database
         //scenarios
        $on_strs = array_map(function ($f){
            // 
            //Take care of the views that are not referenced by a dbname.
            $ref_db= isset($f->ref->db_name) ? "`{$f->ref->db_name}`." : "";
            //
            //Compile the home side of the equation, i.e, a.d
            $home = "$f";
            //
            //Compile reference part of the equation
            $ref = "{$ref_db}`{$f->ref->table_name}`.`{$f->ref->table_name}`";
            //
            //Complete and return the equation
            return "$home = $ref";
           //
        }, $this->links->values());
         //
         //Join the equations with 'and' operator
         return implode(" \n AND ",$on_strs);
     }
}

//The criteria inwhich data affected will be accessed the link to a particular 
//record that returns a boolean value as a true or a false  
class binary extends mutall implements expression{
    //
    //The column involved in the where 
    public $operand1;
    //
    //The va
    public $operand2;
    //
    //the operator eg =, +_ \
    public $operator;
            
    function __construct(expression $operand1, $operator , expression $operand2) {
        //
        //Set the two fields as the properties of the class 
        $this->operand1= $operand1;
        $this->operand2=$operand2;
        $this->operator=$operator;
        //
        parent::__construct();
    }
    //
    //This method stringfies a binary expression
    function to_str() : string{
        //
        $op1 = $this->operand1->to_str();
        $op2 = $this->operand2->to_str();
        //
        //Note opending and closing brackets to bind the two operands very closly
        return "($op1 $this->operator $op2)";
    }
    
    //Yields the entities that are involed in this binary expression.
    function yield_entity(): \Generator{
        yield from $this->operand1->yield_entity()();
        yield from $this->operand2->yield_entity();
    }
    //
    //Yields the attributes that are involed in this binary expression.
    function yield_attribute(): \Generator{
        yield from $this->operand1->yield_attribute()();
        yield from $this->operand2->yield_attribute();
    }
}





