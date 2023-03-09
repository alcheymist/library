<?php
        //
        //A table must have a header
        if (!isset($Itable->header)){
            throw new \Exception("Table name '$tname' has no header");
        }
        //The header of the static milk table is a list of colum names
        $cnames = $Itable->header;
        //
        //The header must be an array of columns
        if (!is_array($cnames)){
            throw new \Exception("The header of table '$tname' must be an array of"
                    . " simple names");
        }
        //
        //A table must have a body
        if (!isset($Itable->body)){
            throw new \Exception("Table name '$tname' has no body");
        }
        //Get the tabular's Ibody
        $Ibody = $Itable->body;
        //
        //The body of a table is either internal or external
        //
        //A table body is internal if it is a simple array
        if (is_array($Itable->body)){
            $this->body = new simple_array($Ibody, $this); 
        }
        //
        //An external body must be an object with a type property
        elseif(is_object($Ibody)){
            //
            //All objects describing a body must have a type property
            if (!isset($Ibody->type)){
                throw new \Exception("The body of tabular layout must have a 'type' property");
            }
            $type = $Ibody->type;
            //
            //All objects describing a body must have a data property
            if (!isset($Ibody->data)){
                throw new \Exception("The body of tabular layout must have a 'type' property");
            }
            $data = $Ibody->data;
            //
            //Categorise the body guided by its type
            switch($type){
                //
                //If the body type is a file, then it must exist on the server
                case "filename":
                    if (!is_file($data)){
                        throw new \Exception("File '$data' not found");
                    }
                    //
                    //Treate Excel and text files separately
                    //
                    $this->body = new filename($data, $this);
                //
                //If a body type is an sql, the database name is optional     
                case "sql":
                    //
                    //The sql text must be set
                    if (!isset($data->text)) 
                        throw new \Exception("The text for the sql statement is missing");
                   //
                   //If the dbname is given, then it must be valid
                   if (isset($data->dbname)){
                       $dbase = $this->open_dbase($data->dbname); 
                   }
                   //
                   //If the dbname is not set, then there must be a current one
                   else {$dbase=\database::current; }
                   //
                   //Crtare an sql statement in the capture namespace
                   $this->body = new sql($data->text, $dbase, $this); 
                default:
                    throw new \Exception("Body type '$type' is not known");
            }        
        }
        //
        //Any other way of specifying a body is erroneous
        else{
            throw new \Exception("Body ".json_encode($Ibody). " is not known");
        }