<?php
//Sql work all happens in the root namespace. Is there any justification for 
//otherwise? The for now is no. Why? because MOST of the work on an a new class
//called view, which is an extension of an entity.  
namespace root;
//
include_once 'schema.php';
//
//select statement that retrieves all the columns of an entity and also retrieves 
//the foreign keys as resolved attributes i.e identifier.
class editor extends view{ 
    //
    //The source entity from where we are to retrieve the sql also used to formulate 
    //the from clause of this sql  
    public entity $entity;
    //
    //To create an editor we require the entity name and the database name 
    //require to retrieve the source entity 
    function __construct($ename=null, $dbname=null){
        //
        //bind the entity name and the database name required to derive the entity 
        $this->bind_arg('ename',$ename );
        $this->bind_arg('dbname', $dbname);
        //
        //Get the source entity
        $dbase = database::open($dbname);
        $this->entity= $dbase->entities[$ename];
        //
        //Compile the arguments for the parent select i.e $fields, $join and $where
        //
        //1.join of this sql 
        //Collect the id paths from the source is the terminal condition is the 
        //last foreign column used as id
        $paths =$this->entity->collect_paths_id_paths();
        //
        //Get the joins of this sql
        $join= $this->entity->join_path($paths);
        //
        //2. The fields required for the selet 
        //The fields of this sql are the column attributes of this entity any any 
        //other referenced entity 
        $fields = $this->get_fields();
        //
        //This sql has no conditions for retrieving its data and hence no wheres are
        //required 
        //
        //The parent select 
        parent::__construct($this->entity, $fields, $join);
    }
    
    //returns the field of this editor sql
    function get_fields(): fields{
        //
        //Begin with an empty collection of the fields
        $fields = new fields();
        //
        //Visit each column of the from entity, resolve it.
        foreach($this->entity->columns as $col){
            //
            //Resolve the current column if attribute return a column while 
            //if foreign return a function
            //
            //1. an attribute push in a column
            if($col instanceof \schema\column\attribute){
                //
                $fields->collection->add(new column($col));
            }
            //push in a function
            //
            //A foreign key needs resolving from e.g., client=4 to
            //client = ["deekos-Deeoks Bakery lt"]. We need to cocaat 5 pieces
            //of data, $ob, $primary, $comma, $dq, $friendly, $dq, $cb
            else{
                //Start with an empty array 
                $args=[];
                //
                //Opening bracket
                $ob= new expressions\literal('[');
                array_push($args, $ob);
                //
                //Double quote
                $dq= new literal('"');
                array_push($args, $dq);
                //
                //Get the friendly name which is the identifier of this entity
                //
                //To obtain the indexed attributes get the identifier
                $identify = new identifier($col->ref_table_name, $col->ref_db_name);
                //
                //The friendly are the fields of the identifier
                $friendly=  $identify->fields;
                //
                //Return an array of the fields 
                $fri_fields= $friendly->toArray();
                //
                //merge with the args array using a loop inorder to put a / separator
                //Loop through the array and pushing every component 
                foreach ($fri_fields as $field){
                    array_push($args, $field);$d= new literal("/");
                    array_push($args, $d);
                }
                array_pop($args);
                //
                //Double quote
                 array_push($args, $dq);
                 //
                $cb= new literal(']'); array_push($args, $cb);
                //
                $fields->collection->add(new function_('concat', $args, $col->name));
            }
        }
        return $fields;
    }
    
    //Present the editor sql to support editing of tabular data
     function show(string $where = null, string $order_by = null, string $layout = null): void {
         //
        $this->bind_arg('dbname', $dbname);
        $this->try_bind_arg("type", $type);
        //
        //Execute this sql 
        $array= $this->get_sql_data($this->dbname);
        //
        //Ouptut a table
        echo "<table id='fields' name='{$this->entity->name}'>";
        echo '<thead>';
        echo $this->show_header();
        echo '</thead>';
        echo '<tbody id="table-body">';
        //
        //Loop through the array and display each row as a tr  element
        foreach ($array as $row) {
        $id= "{$this->entity->name}";
            //
            echo "<tr onclick='record.select(this)' id='$id'>";
            //
            //loop through the column and out puts its td element.
            foreach ($this->entity->columns as $col){
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
    }
}

//The class is used to suport the selectin of one record of an entity

//This is a type of select sql that retrieved all the id columns of an entity. 
//all the foreign columns are also resolved to the id attributes of the referenced 
//entity
class selector extends view{
    //
    //The source of the sql that derives the from for the veiw and it alsso serves 
    //as the source of this identifier network 
    private entity $entity;
    //
    //Requires the entity name as $ename, database name as $dbname from we derive 
    //the entity involved in the identifier select sql.
    function __construct(string $ename=null, string $dbname=null, string $name=null) {
        //
        //bind the entity name and the database name required to derive the entity 
        $this->bind_arg('ename',$ename );
        $this->bind_arg('dbname', $dbname);
        $this->bind_arg('name', $name, null);
        //
        //Get the source entity
        $dbase = $this->open_dbase($dbname);
        $this->entity= $dbase->entities[$ename];
        //
        //Only the from variable is mandatory
        parent::__construct($this->entity, null, null, null, $name);
    }
    
    //Prepare the view constructor variables before calling the parent execute
    //inorder to set the view parameters that were passed as nulls 
    function execute():array/*value[][cname]*/{
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
        //this selector using the this entity as the source of this sql
        $network = new identifier($this->entity);
        //
        //Use the network to create a join 
        $this->join = new join($network);
        //
        //Construct the identifier paths using defaut settings, i.e., exceptions
        //will be thrown immediately rather than be logged)
        $network->execute();
        $this->join->execute();
        //
        //2. Derive the fields of selector. They are 2: the primary key, $x, and 
        //its friendly name, $x__id; Note the double underbar to minimise teh 
        //posibility of fomulating conflicting names
        $this->columns = [$this->get_foreigner(), $this->get_friend()];
        //
        //Now return the values from the parent execute
        return parent::execute();
    }
    
    //Returns the foreign key that links the selecteot to the source entity
    private function get_foreigner():foreign{
        //
        //The ref_table name is the source entity while the source dbname is the 
        //this dbname 
        $ref = new \stdClass();
        $ref->table_name= $this->ename;
        $ref->db_name= $this->dbname;
        //
        //The name of this foreigner by convection should the same name as the 
        //entity that it references 
        $cname = $this->ename;
        //
        //The options of this column 
        $option = new \stdClass();
        $option->is_nullabe="NO";
        //
        return new foreign($this->dbname, $this->name, $cname, $option, $ref);
    }
    
    
    //Retruns the concat function, as field, used for impelemneting the friendly 
    //name  this selector's entity
    private function get_friend():field{
        
        //Compile the name of the selector field, based on the current entity
        $fname = "$this->ename"."__id";
        //
        //Start with an empty list of parts
        $parts = [];
        //
        //Collect all the friendly parts;
        foreach($this->get_friendly_part() as $part){ $parts[]=$part; }
        //
        $exp = new function_('concat', $parts); 
        //
        $field = new field($this->dbname, $this->name, $fname, $exp);
        //
        return $field;
    }
    
    //Collect all the parts of a friendly column. Each part is derved from 
    //an identifier attribute followe by a a separting slash
    private function get_friendly_part():\Generator{
        //
        //Visit each target in the underlying join to access the entity columns
        foreach($this->join->targets as $target){
            //
            //Loop throu all te columns of the target entity
            foreach($target->entity->columns as $col){
                //
                //Only friendly columns are considered
                if ($this->is_friendly($col)){
                    //
                    //Yield a column expression
                    yield new $col;
                    //
                    //Follow this with a separating slash (/) literal
                    yield new literal('/');
                } 
            }
        }
    }
    
    //Define what a friendly column is
    private function is_friendly($col):bool{
        //
        //A column is friendly if...
        return
            //It is an atribute...
            $col instanceof attribute
            && (
                //that is used used for identification purposes
                $col->is_id()
                //
                //or it is descriptive
                || $col->is_descriptive()
            )
            //The is_valid is not a useful column, even if it is an id
            && $col->name!=='is_valid';     
    }
}

//This class formulates an sql given the inputs, e.g., fields, whwre, etc,  which 
//do not reference any join. The join is derived from the inputs to complete the
//sql. Hence the term parial.
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
    function __construct(entity $source,array /*entity[]*/$targets) {
        //
        $strategy=new strategy_structural();
        //
        parent::__construct($source, $strategy);
        //
        //Initialise the targets 
        $this->targets= $targets;
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
 
//Join is a map of targets indexed by partial name of an entity. Why a map?
//Because the order of inserting the keys is important!
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
    //This property is used in the import method see below it is the driving condition
    //on how to use the paths derived from the network 
    public bool $clear;
    //
    //The ordered list of join targets indexed by the partial entity, pename. 
    //This list is constructed when a join is exceuted
    public \Ds\Map /*target[pename]*/$targets;
    //
    //joins are created with on optional parameter of path i.e an array of foreigners
    //though this paths at the constructor level are optional it is important to note that 
    //we cannot have a join without a path so users can define the paths latter 
    //using the import method 
    function __construct( ?array /*foreigner[][]*/ $paths=null) {
        //
        //Save the constructor defined paths 
        $this->paths=$paths;
        //
        parent::__construct();
        //
        //Begin with an empty map of the target that if to be popilated by the path 
        //in the network this ensures that the targets are always set even if the 
        //path is empty.
        $this->targets=new \Ds\Map();
    }
    
    //Execute a join to assimilate the connection patts to the join targets
    function execute($throw_exception=true){
        //
        //Use the paths on the cponstructor and thos imported to compile
        //te paths for this jpoin
        $paths = $this->get_paths($throw_exception);        
        //
        //Visit each path in the network and consider it for assimilation to this
        //join
        foreach($paths as $path){
            //
            //Visit each foreigner in the path and consider it for assimilation
            //to this join
            foreach($path as $foreigner){
                //
                //Add the entity to the join as a target
                $this->add_foreigner($foreigner);
            }
        }
    }
    //
    //Returns the array of path that is required to derive the targets for this 
    //join the paths can either be from the constructor or from the network or both
    //depending on the clear option
    private function get_paths($throw_exception):array /*foreigner[][]*/{
        //
        //1. ensure that either the constructor paths or the import network isset
        //this is because we cannot have a join without the paths from where we 
        //derive the targets see in the constructor above
        //
        //Neither is set throw an exception
        if(is_null($this->paths) &! isset($this->network)){
            throw new \Exception('The join could not be established since there'
                    . 'is no path from which we derive the targets');
        }
        //
        //2. If the paths are null but the network isset 
        //This simply means that the paths are to obtained fromm the network irrespective
        //of the clear parameter 
        if(is_null($this->paths) && isset($this->network)){
            //
            //execute the network 
            $this->network->execute($throw_exception);
            return $this->network->paths;
        }
        //
        //3.If the paths are set but the network is a null
        //return the constructor paths 
        if(!is_null($this->paths) &! isset($this->network)){
            //
            return $this->paths;
        }
        //
        //4. if both are set 
        if(!is_null($this->paths) && isset($this->network)){
            //
            //
            if($this->clear){
                //
                //execute the network 
                $this->network->execute($throw_exception);
                return $this->network->paths+= $this->paths;
            }
            //
            //execute the network 
            $this->network->execute($throw_exception);
            return $this->network->paths;
        }
        
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
    
    //Returns a complete join clause, i.e., 'inner join $target1 on a.b=b.b and ...'
    function stmt() :string/*join clause*/{
        //
        //Get a copy of this array, so that we can use the standard array methods
        $targets = $this->targets->toArray();
        //
        //Test if this array is empty else 
        //If empty the sql since does not require joins
        if(empty($targets)){return "";}
        //
        //Map each field to its sql string version 
        $joins_str=array_map(fn($target)=>$target->stmt(), $targets);
        //
        //Join the fields strings using a new line separator
        return implode("\n", $joins_str);
    }
    
    //Updates this join's targets with the given forein key
    private function add_foreigner(foreign $foreigner): void{
        //
        //Get the away entity of the foreigner which is the required target to
        //be created or updated
        $entity= $foreigner->away();
        //
        //Decide if we need to create a ne target or update an existing one
        //
        //Note the use of partial name, rather than ename, as we may be querying
        //across databases 
        if ($this->targets->hasKey($entity->partial_name)){
            //
            //Update the target with the foreigner
            //
            //Use the partial key to get the affected target from the join 
            $target= $this->targets[$entity->partial_name];
            //
            //Update the 'on' clasuse by adding the foreigner. Assume that is on
            //is a set, so that it will take only one instace of a foreigner
            $target->on->add($foreigner);
        }
        else{
            //The indexing key does not exist. Create a new target and 
            //initailize it with the foreigner
            //
            $target=new target($entity);
            //
            //Initialize it with the foreigner
            $target->on->add($foreigner);
            //
            //Attach the target to the join
            $this->targets[$entity->partial_name] = $target;
        }
    }
}

//Models the targets of a join as an entity that has an ain clause
class target extends entity{
    //
    //Home for all the foreiners than are "ANDED"
    public \Ds\Set /*foreigner[]*/$on;
    //
    //The type of the join for this target 
    public string $jtype;
    //
    //Name of the entity that is the join target.
    function  __construct(entity $entity, string $jtype='inner'){
        //
        $this->entity = $entity;
        $this->jtype = $jtype;
        //
        //prefered to be a set since it does not allow repetition 
        $this->on = new \Ds\Set();
        parent::__construct($entity->name, $entity->dbname);
    }
    
    
    //Returns a complete join phrase, i.e., inner join ename on a.b=b.b
     function stmt() :string{
         //
         //The  type of the join, e.g., inner join, outer join
         $join_str = "$this->jtype join"
            //
            //Add the On clause
            . " \t`{$this->entity->dbname}`.`{$this->entity->name}` ON  {$this->on_str()}";
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
            //Compile the home side of the equation, i.e, a.d
            $home = "`$f->dbname`.`$f->ename`.`$f->name`";
            //
            //Compile reference part of the equation
            $ref = "`{$f->ref->db_name}`.`{$f->ref->table_name}`.`{$f->ref->table_name}`";
            //
            //Comolete and retin teh equation
            return "$home = $ref";
           //
        }, $this->on->toArray());
         //
         //Join the equations with 'and' operator
         return implode(" \n AND ",$on_strs);
     }
}

//The criteria inwhich data affected will be accessed the link to a particular 
//record that returns a boolean value as a true or a false  
class binary implements expression{
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

//This models the sql function which require 
 //1. name e.g concat
 //2. array of whic ar expressions
class function_ implements expression{
    //
    //These are the function arguments
    public array /*expression []*/$args;
    //
    //This is the name of the function e.g., concat 
    public $name;
    //
    function __consruct(string $name, array/*expression[]*/ $args){
        //
        $this->name = $name;
        $this->args = $args;
        parent::__construct();
    }
    
    //Convert a function to a valid sql string
    function to_str():string{
        //
        //Map every argument to its sql string equivalent
        $args = array_map(fn($exp)=>$exp->to_str(), $this->args);
        //
        //All function arguments are separated with a comma
        $args_str = implode(', ', $args);
        //
        //Return the properly syntaxed function expression
        return "$this->name($args_str)";
    }
    
    //Yields all the entity names referenced in this function
    function yield_entity():\Generator{
        //
        //The aarguments of a functin are the potential sources of the entity
        //to yield
        foreach($this->args as $exp){
            //
            yield from $exp->yield_entity();
        }
    }
    //Yields all the atrributes referenced in this function
    function yield_attribute():\Generator{
        //
        //The aarguments of a functin are the potential sources of the entity
        //to yield
        foreach($this->args as $exp){
            //
            yield from $exp->yield_attributes();
        }
    }
    
    //
    //Displays the query result of this expression
    function show($value){
        return "<td>"
                    . "$value"
              . "</td>";
    }
   
}

////This represent a simple field in sql or an attribute in the from for its construction 
////we require the from column
//namespace sql {
//    
//    class column  extends \root\expression{
//        //
//        //The construction includes a column and a volumntary allias that can be null
//       function __construct(\root\column $col){
//           //
//           $this->column=$col;
//           //
//           parent:: __construct($col->dbname, $col->ename, $col->name, $col->options);
//       }
//
//       //
//       function get_ename(){
//           yield $this->column->ename;
//       }
//
//       //Stringfy the column to a valid sql string for this column in the 
//       //full description of a field e.g.,  
//       //`mutall_login`.`client`.`name`
//       function __toString() : string{
//           //
//           //Let $c be thr root column
//           $c = $this->column;
//           //
//           //compile the complete string version of the  
//           return "`$c->dbname`.`$c->ename`.`$c->name`";
//       }
//    }
//    
//    
//    //A field is an sql column
//    class field extends column{
//        //
//        
//        public \root\expression $value;
//        //
//        function __construct(string $dbname, string $ename, string $name, \root\expression $value){
//            //
//            $dbase = $this->open_dbase($dbname);
//            //
//            $col = $dbase->entiies[$ename]->columns[$name];
//            //
//            $this->value = $value;
//            //
//            parent::__construct($col);
//        }
//    }
//} 
  


