//Define the structure of a questionnaire, Iquestionnaire
//
//A questionnaire is either:-
type Iquestionnaire = 
    //
    // ...an array of layouts....
    Array<layout>
    //
    //...or a (Mutall) commented Microsoft Excel file name
    |string;
//
//A layout is either labeled or tabular
type layout = label|table;
//
//Definition of a labeled layout
//
//A labeled layout is a tuple of 5 elements
//
//Consider a server.dts file generated from a server to support type 
//checking of a label
type label = [dbname, ename, alias, cname, expression];

//A datababase name must exist on the server
//
//Assign Peter Kamau to derive the databases from a server needed for
//describing this type so that Typescript can check the proper use of
//database, entity, column and index names
type dbname = "mutall_user"|"tracker"|"rentize"|"postek"|"chama"|"real_estate";
//
//The database table, a.k.a., entity, where the data is stored
type ename = string;
//
//The column name where the data is stored in an entity
type cname = string;
//
//A context that uniquely describes the entity
type alias = Array<basic_value>;
//
//The data to be stored, specified as an expression. An expression may be:-
type expression = 
    //
    //...a basic Typescript value...
    basic_value
    //
    //...or a tuple that has a function name ad argumments. The name is a 
    //referecne to a PHP class and the arguents are arrguments to the class 
    //constructor
    |[string, Array<any>]
    
//
//The basic data types in Typescript
type basic_value = number|string|boolean|null;
//-------------------------------------------------------------------
//    
//
//The description of input data laid out in a tabular format
type table = {
    //
    //The table name used in a lookup reference
    tname:string,
    //
    //The optional header is an array of column names for this table that are
    //used as cell incices in a lookup. If not avalable then the cell indices 
    //in a lookup are expected as numbers that match column positions
    header?: Array<string>;
    //
    //The body of a table is defined as a php class name with its constructor
    //arguements. For this version, the arguments are not parametrized. In
    //future, they will be checked against a questionnare.d.ts which will hold
    //all available body classes
    body:{
        class_name:string,
        args:Array<any>
    }    
}
            