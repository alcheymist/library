//A record is the next largest data structure after field and before sql.
//The reference table (which is shared with an edit sql) s required for 
//carrying the table indices needed for saving a record
function record(fields, dbase=null, tname=null, reftable=null, stmt=null, values=null){
    //
    //The fields and values of a record
    this.dbase = dbase;
    this.fields=fields;
    this.tname = tname;
    this.values = values;
    this.reftable = reftable;
    this.stmt = stmt;
    //
    //Until you know how to set the class name automatically...
    this.classname = "record";
    //
    //Call the parent mutall prototype (I dont see much gain in having the parent
    //data object as js does not support abstract methods)
    mutall.call(this);
    //   
    //Copy data from the given dom record on this page this js record
    //(or vice versa) depending on the direction of the desired movement.
    //The destination is either this js record or the given container. The page 
    //argument is important because different layouts tag fields and records 
    //differently. If the optional container argument is supplied, it is 
    //the one that is involved in the data transfer; otherwise it is this 
    //record's values property that serves as the container, i.e., data source 
    //or sink
    this.copy = function (direction, page, dom_record, container=null){//record
        //
        //The copy process is driven by the fields of this record; retrieve 
        //them.
        var fields = this.fields;
        //
        //If the container is not valid...
        if (container===null){
            //
            //...then create a new object for it... 
            container = new Object();
            //
            //... and let it be the values property of this record.
            this.values=container;
        }
        //
        //Step trough the fields of this record and use them to move the data 
        //in the desired direction
        for(var i in fields){
            //
            //Get the i'th field
            var field = fields[i];
            //
            //From the layout get field element's name, e.g., td or field
            var fEname = page.layout.field_tag_name;
            //
            //Use the element name to formulate a css field selector, e.g.,
            //td[name='age']
            var fselector = fEname + "[name='" + field.name + "']";
            //
            //Get the named dom field element from the dom record.
            var dom_field = dom_record.querySelector(fselector);
            //
            //Move the data between the given dom field (on the indicated page) 
            //and the identified source (or sink) in the desired direction.
            field.copy(direction, page, dom_field, container);
        }
    };

    
    //Add the foreign key value to this record. This is important for pre-filling
    //a new descendant record with the data that matches its parent page_record
    this.add_fkvalue = function(page_record=null){
        //
        //This process is valid only if the parent page_record is known
        if (page_record===null){
            return;
        }
        //
        //Prepare to get the primary key field of the foreign key table
        //
        //Get the foreign key table name; it has the same name a the foreign 
        //key table
        var fktname = page_record.tname;
        //
        //Let fkrecord be the foreign key driver record; it has the values we are 
        //interested in.
        var fkrecord = page_record.driver;
        //
        //Now get the primary field of the foreign table
        var primary = fkrecord.fields[fktname];
        //
        //Get the foreign key field of this record; it has the same name as the
        //foreign key table name
        var fkfield = this.fields[fktname];
        //
        //Create the values object of this record; its empty
        this.values = {};
        //
        //Step through th subfields of the primary and copy theier matching values from
        //transferadd the 
        for(var i in primary.subfields){
            //
            //Retrieve the primary key field sql field name
            var primary_fname = primary.subfields[i].fname;
            //
            //retrieve the foreign key sql field name
            var foreign_fname = fkfield.subfields[i].fname;
            //
            //Retrieve the value from the foreign record
            var value = fkrecord.values[primary_fname];
            //
            //Copy the value to this record
            this.values[foreign_fname]=value;
        }
    };
}

//
//A dom record extends a php record with a view -- a html element that represents
//the visible part of a record. The $ prefix is added to avoid references to page
//the class constructor which raises confusion
function dom_record(view, $page){
    //
    //Define the empty values of this dom record
    this.values;
    //
    //The visible part part of a record
    this.view = view;
    //
    //We need to access the page to be able to implement page-specifc methods
    this.page=$page;
     //
    //Get the driver (sql) that drives the given page
    var driver = $page.driver;
    //
    //Retrieve the reference table; it is valid only for page_table derivatives
    //(This is currently not done using OO style!!)
    var reftable = typeof $page.sql_edit==="undefined" ? null: $page.sql_edit.reftable;
    //
    //Initialize the parent record (without any values) in order to implement
    //the PHP version o a dom record. Note that the table name comes from the
    //page (not the driver)
    record.call(this, driver.fields, driver.dbase, $page.tname, reftable, driver.stmt);
    
    //Return the values of this dom record from its view. Note how this method
    //is declared before using it below
    this.get_values = function (){
        //
        //Initialize the values with nothin
        var values = {};
        //
        //The copy process is driven by the fields of this dom record; retrieve 
        //them.
        var fields = this.fields;
        //
        //Step trough the fields of this dom record and use them to move the data 
        //from the vieq to the container
        for(var i in fields){
            //
            //Get the i'th field
            var field = fields[i];
            //
            //From the layout get field element's name, e.g., td or field
            var fEname = this.page.layout.field_tag_name;
            //
            //Use the element name to formulate a css field selector, e.g.,
            //td[name='age']
            var fselector = fEname + "[name='" + field.name + "']";
            //
            //Get the named dom field element from this dom record's view.
            var dom_field_view = this.view.querySelector(fselector);
            //
            //Copy the data from the dom field view to this dom record's values
            field.copy(true, this.page, dom_field_view, values);
        }
        //
        //Return the values
        return values;
    };
    //
    //Retrieve the view values to initialize the this record's values property
    this.values = this.get_values();
    //
    //Switch this dom record to (or cancel the) edit mode. 
    this.switch_record_to_edit = function(to_edit){
        //
        //Retrieve the (js) fields of this record
        var fields = this.fields;
        //
        //Run through each js field and put its corresponding dom version
        //into (or out of -- depending on the boolean to_edit argument) edit mode
        for(var i in fields){
            //
            //Get the i'th js field
            var field = fields[i];
            //
            //Get the name of the field
            var fname = field.name;
            //
            //Define a css selector for the only dom field in this dom record
            //with this field name
            var css = this.page.layout.field_tag_name+"[name='" + fname +"']";
            //
            //Locate the required dom field using the selector on this record's
            //view
            var df = this.view.querySelector(css);
            //
            //Switch to the requested edit mode
            field.switch_field_to_edit(to_edit,df);
        }
    };
    
    //Removve this dom record from its parent
    this.delete = function(){
        //
        //Get the parent of this dom record's view
        var parent = this.view.parentElement;
        //
        //Remove this view
        parent.removeChild(this.view);
    };
    
    //Returns all the empty blank fields for the given identification fields
    //The check is done before saving the data to a database. It proceeds by:-
    //(1) verifying that no identification field is blank 
    //(2) reporting the field (by highlighting it in red) if there is 
        //one that is blank
    this.get_blank_idfields = function(){
        //
        //Get the identification indices of this record from the reference 
        //table object; 
        var indices = this.reftable.indices;
        //
        //Get the first index name; it is as good as  any other for
        //our current purpose.
        //
        //How do you to get the first property of a any structure? 
        //Object.keys(indices) returns an array of indices. Then you access 
        //the first one as [0]. For now, use this method; it works
        var fnames ;
        for(var i in indices){
            //
            fnames = indices[i];
            //
            //Get out of the for loop after the first index
            break;
        }
        //
        //Collect the blank fields
        //
        //Start with an empty list of fields
        var fields="";
        //
        //Loop through all the given identification field names and collect the 
        //emptyones 
        for(var i in  fnames)
        {
            //Get the i'th field name
            var fname = fnames[i];
            //
            //Get the js field; we assume that the page's data is an sql. What 
            //if it is not?
            var field = this.fields[fname];
            //
            //Retrieve the name of teh primary subfield of the js field. For a normal 
            //js field this is the same as the field's name. In contrast, that 
            //of a relation field is the id subfield.
            var basic_fname = field.get_fname("primary");
            //
            //Get the named basic field value. 
            var value = this.values[basic_fname];
            //
            //Verify that this value is not empty; otherwise highlight the 
            //empty field and indicate a failure
            if ((value === '') || (typeof value==="undefined")) 
            {
                //
                //Mark (i.e., show) the empty values in red
                //
                //Formulate a css selector for input elemet of the named field 
                var fselector = this.page.layout.field_tag_name + "[name='"+fname+"']";
                //
                //Get the input element of the named dom field
                var input = this.view.querySelector(fselector);
                //
                //Set the backround as red.
                input.setAttribute("style", "background:red");
                //
                //Add the empty field to some collection, separated by comma
                fields = fields + (fields==="" ? "" :", ") + fname ;
            }
        }
        //
        return fields;
    };
    
    //   
    //Update the dom record, including its view, with the given values. The view
    //supplies its fieldsa, as well as the primary key attribute of the dom record.
    //This is important, otherwise the newly added record is not equal to the 
    //older records -- which means that it may not immediately support such 
    //operations as delete, which rely on the primary key of a record
    this.update_view = function (values){
        //
        //Set the values property
        this.values = values;
        //
        //Update the view. The process is driven by the fields of this record; 
        //retrieve them.
        var fields = this.fields;
        //
        //Step trough the fields of this record and use them to move the data 
        //in the desired direction
        for(var i in fields){
            //
            //Get the i'th field
            var field = fields[i];
            //
            //From the layout get field element's name, e.g., td or field
            var fEname = this.page.layout.field_tag_name;
            //
            //Use the element name to formulate a css field selector, e.g.,
            //td[fname='age']
            var fselector = fEname + "[name='" + field.name + "']";
            //
            //Get the named dom field element from the dom record.
            var dom_field_view = this.view.querySelector(fselector);
            //
            //Copy data from the given values to this dom record's view
            field.copy(false, this.page, dom_field_view, values);
        }
        //
        //Update the primary key attribute of this record's view
        //
        //Get the primary key field name; it has the same as the table of
        //this record
        var pkfname = this.tname;
        //
        //Get the (composite) primary key field from this record's fields
        var field = fields[pkfname];
        //
        //Get the primary key subfield of the composite field; other subfields
        //are indexed as input and id
        var subfield = field.subfields["primary"];
        //
        //Get the basic field name of the primary key; its teh one used for \
        //indexing the data
        var bpkfname = subfield.name;
        //
        //Use the basic field name to retrieve the primary key value from the
        //input values
        var primarykey = values[bpkfname];
        //
        //Set the primary key attribute of this record's view.
        this.view.setAttribute("primarykey", primarykey);
    };
}

//Modelling the general field
function field(name){
    //
    this.fname = name;
    //
    //Initialize the inherited mutall system. (I dont see much gain in having 
    //the parent data object as js does not seem support abstract methods)
    mutall.call(this);
    
    //Editing this ordinary field simply switches on the edit mode on the given
    //dom field and transfers focus to the field. We assume that the input and 
    //output elements are already synchronized. 
    this.edit = function(df){//field
        //
        //Switch this field to edit mode
        this.switch_field_to_edit(true, df);
        //
        //Transfer focus to the input element
        var input = df.querySelector("input");
        input.focus();
    }; 
    
    //The new version of field edit simply transfers data from from the given
    //input element to the fields's value
    this.edit = function(input){
        //
        this.value = input.value;
    };
    
    //The basic field name of a normal js field is the same as the field
    //name. In contrast, that of a relation field is the indexed subfield
    this.get_fname = function(index){
        return this.name;
    };
    
    //
    //Switch this relation column to (or cancel the) edit mode -- given 
    //its (dom field) view
    this.switch_field_to_edit = function(to_edit, view){
        //
        //Get the output element from the viewd. Remember that every view 
        //element has an output tag; also that not all views have an input tag,  
        //e.g., the primary key field. 
        var output = view.querySelector("output");
        //
        //Get the input element; it may be null (e.g., for the case of primary 
        //key described above)
        var input = view.querySelector("input");
        //
        //Now do the requested switch
        switch(to_edit){
            //
            //Switch to edit mode; that means show the input and hide the output 
            //tag
            case true:
                //
                //Hide the output tag
                output.setAttribute("hidden", true);
                //
                //Show the input tag with the same value as the output
                input.removeAttribute("hidden");
                input.value = output.textContent;
                break;
            //
            //Switch to display mode; that means hide the input tag and show 
            //the output one
            case false:
                //
                //Hide the input tag
                input.setAttribute("hidden", true);
                //
                //Hide the output tag
                output.removeAttribute("hidden");
                //
                //Reset the input value to the output??????
                //input.value = output.textContent;
            break
        }
    };

    //
    //Copy THE INPUT DATA (what about the id and _output child elements? data 
    //from the given dom field to the given values object (or vice versa -- 
    //depending on the direction of the specified movement). 
    //(Why is page needed? Its only valid for record copying where we use it to
    //identify the propery field tag, td ot field -- depending on page layout retrieve)
    this.copy = function(from_dom, page, dom_field,values){//field
        //Identify the input child element of a normal field
        //
        //The input element allows data editing; it has the more updated data 
        //than the output element -- but not every field has it.
        var input = dom_field.querySelector("input");
        //
        //Every normal field has an output tag
        var output = dom_field.querySelector("output");
        //
        //Get the name of the field
        var fname = this.name;
        //
        //Depending on the direction of the copy, move the data.
        switch(from_dom){
            //
            //Copy the data from dom field to the container. Ths is teh case
            //of a normal field where the output=input tag elemens. Unlike the
            //relation fiels, there is no id field
            case true:
                //Valid only if the input is valid, as it has the latest value
                if (input!==null){
                    values[fname] = input.value;
                } else{
                    //
                    //Valid in all cases
                    values[fname] = output.textContent;
                }
                break;
            //
            //Copy data from the container to the dom field
            case false:
                //
                //Valid only if input is valid; the parimary key has no input
                if (input!==null){
                    input.value = values[fname];
                }
                //
                //Valid in all cases
                output.textContent = values[fname];
                break;
        }
    };
}

//A column is a field that can be saved to a database, so it not only has a name
//it alao has a table name to be associated with
function column(name){
    //
    //The name of this column should as required by a database table. What is 
    //the justification for having 2 names: name and column_name? Historical 
    this.column_name=name;
    //
    //Inherit the field object 
    field.call(this, name);
    
    //Transfer it from the value from the (html) input element to the
    //this (js) column and sybchronize all associated fields
    this.change_field = function(input, page, selector_type, has_sibbling=false, has_other=false) {//colum
        //
        this.page=page;
        this.selector_type=selector_type;
        this.has_sibbling = has_sibbling;
        this.has_other = has_other;
        //
        //Transfer value from the input element to the js column object
        this.value = input.value;
        //
        //Ensure that fields of types other than radio or checkbox have 
        //synchronized values, i.e, the value is the samme even if the field
        //occurs multiple times in a page
        if (input.type==="radio" || input.type==="checkbox"){
            //Sychronization is not needed
        }
        else{
            //
            //Synchronize values
            //
            //Get the name of the fiels to synchronize
            var fname = input.name;
            //
            //Formulate a css selector for inputs elements named fname
            //
            var css = 'input[name=' + fname + ']';
            //
            //Retrieve the inputs
            var inputs = document.querySelectorAll(css);
            //
            for(var i=0; i<inputs.length; i++){
                //
                //Get the i'th input
                var input2 = inputs[i];
                //
                if(input2!==input){
                    input2.value = input.value;
                }
            }
        }
        //
        //Invite the user/caller to execute further user defined function
        //dependent on the change.
        this.change_extra(input);
    };
    
    //
    //Invite the user/caller to execute further user defined function
    //dependent on the change. For ordinary columns, this does noting
    this.change_extra = function(input){
        //
    };
}

//A primary key field is a special field
function column_primary(name){
    //
    //Fields that are special to a primary key column
    //
    //The friendly field used for represeting a record
    this.criteria; 
    //
    //The id field used for hreferecing purposes
    this.id;
    //
    //The primary key field used for effectng updates
    this.primarykey;
    //
    //
    //Inherit the field properties using  the static source
    column_relation.call(this, name);
    //
    //The primary key need not be attached for editing purposes
    this.attatch=function(edit_window, article){return;};
    
    //
    //The primary key feld cannot be edited
    this.edit = function(){//column_primary
        //
        alert("The primary key field cannot be edited");
        return false;
    };
    
    //The primary column is not affected by switching its edit mode to on or off
    this.switch_field_to_edit = function(){};
    
}

//A forein key field is an extension of column_relation
function column_foreign(name){
    //
    //properties that are special to a foreign key column
    //
    //The friendly (visible) part of the field 
    this.output; 
    //
    //The id field used for hreferecing purposes
    this.id;
    //
    //The primary key field used for writing to the database
    this.primarykey;
    
    //Inherit the field properties of column relation
    column_relation.call(this, name);
  
    //Edit this foreign key field, given its dom field view and page. The view is
    //an element, td or field, depending on the page layout, that represents
    //the visible part of this foreign key record. The page also supplies the
    //needed dbname to select records from.
    this.edit = function(dom_field_view, page){//comlumn_foreign
        //
        //Let (old) values be an empty object for receiving the data to be 
        //transfered from the dom field. The page selector to be called later
        //will return new values that we shall put back to the dom_field_view
        this.values = {};
        //
        //Copy the data, i.e, primary, id and output values, from the dom field 
        //to the values object, guided by this field.
        this.copy(true, page, dom_field_view, this.values);
        //
        //The table name required for driving the record selector is the 
        //foreign key table associated with this field
        var tname = dom_field_view.querySelector("fk_table_name").textContent;
        //
        //Define the dimension specifications of the login window and place it 
        //relative to the dom field
        var top = 0;
        var left = dom_field_view.offsetLeft;
        var specs = "top="+top+",left="+left+",height=400,width=400";
        //
        //Prepare the query string (requirements) of the selector page
        var qstring = {
            //
            //Parameters for defining a page of records
            tname:tname,
            dbname:page.dbname,
            //
            //Set the values from the subfields of this foreign key. This is
            //the data that extends a page_records to a page_selector
            output:this.get_value("output"),
            id:this.get_value("id"),
            primarykey:this.get_value("primary")
        };
        //
        ///Request the server to open a new selector page, with the given input
        //and wait for user to interact with it; on return (when the window is closed)
        //extract the returned values to refresh the dom field version (on this 
        //page) being modified. The values is an object comprising of properties 
        //and values that match the primary, id and output subfields of the 
        //foreign key field. We use field.copy() to effect the transfers. The
        //in_field parammeter is the primary key field (the parent of this 
        //foreing key with values attached to it)
        this.open_window("page_selector.php", qstring, function(in_values){//edit_fkfield
            //
            //Retrieve the values from the incoming field. Remember that 
            //the new values are indexed by the subfields of the primary key 
            //fields that is the parent of this foreign key; they need to be
            //re-indexed by the names of the (this) foreign key's subfields....1
            //
            //Define an empty list of the new values, to be transffered from ...1
            //and re-indexed by the subfields of this foreign key field.
            var re_values = {};
            
            //Define the in_field that maps primary key field names to their
            //subfield indices". The structure is defined in PHP field::map .
            //(in futue, access this structiure as a property of field, so that
            //field_map = this.map
            var field_map = {
                id:"_id",
                output:"_output",
                primary:"_primary"
            };
            //
            //Transfer and re-index the incoming values
            for(var i in field_map){
                //
                //Get the field name that is the source of the data
                var srcfname = field_map[i];
                //
                //Get the field name that is the destination of the data; it has 
                //the same subfield index as the source
                var destfname = this.subfields[i].fname;
                //
                //Copy teh date from teh sourec to the destination, indexed
                //by teh destination
                re_values[destfname] = in_values[srcfname];
            }
            //
            //Copy the re-indexed values, NOT from the dom_field_view to the 
            //new re-indexed values BUT vive versa. Its the opposite of
            //what happedned earlier.
            this.copy(false, page, dom_field_view, re_values);
            //
            //Switch the dom field_view into edit mode
            this.switch_field_to_edit(true, dom_field_view);
        }, specs);
    };
 
    //Edit the water meter
    this.change_field = function(input,page, selector_type, has_sibbling=false, has_other=false) {//column_foreign
        //
        this.selector_type=selector_type;
        this.has_sibbling = has_sibbling;
        this.has_other = has_other;
        //
        //Define the dimension specifications of the selection window and place 
        //it relative to the input field
        var top = 0;
        var left = input.offsetLeft;
        var specs = "top=" + top + ",left=" + left + ",height=400,width=400";
        //
        //Lrt fname be the cutrrent field name
        var fname = input.name;
        //
        //Prepare the (subfield) data to send to the selector: current output, 
        //id, and primarykey values
        var qstring = {
            //
            //The foreign key table name is the name of the input element
            tname: fname,
            //
            //Use the same dbname as that of this page
            dbname: page.dbname,
            //
            //Any other (filtering condition)
            //
            //Set the values from the subfields of this foreign key. This is
            //the data that extends a page_records to a page_selector
            output: page.output,
            id: page.id,
            primarykey: page.primarykey
        };
        //
        ///Request the server to open a new selector page, with the given input
        //and wait for user to interact with it; on return (when the window is closed)
        //extract the subfield values. Note how the url is formulated to ensure
        //that we can can this page from projects other than Buis; the relative
        //location of the project is important
        this.open_window("../library/page_selector.php", qstring, function (subfield) {
            //
            //The value of a foreign key field is the primary subfield
            //Note: the fields in teh subfield are indxed by the field names of the 
            //primary, id and input columns; hence the leading underbar
            this.value = subfield._primary;
            //
            //Set the input's value to the output; this is what the user sees
            input.value = subfield._output;
            //
            //Fire the onchange event, because we have made the change 
            //programmaticaly; otherwise it wont fire automatically
            //input.onchange();
        }, specs);
    };
    
}

//This class represents columns used for establishing relationships between
//tables. The two examples are primary and foreign key columns
function column_relation(name){
    //
    //Call the parent column system
    column.call(this, name);
    //
    //Copy data from a dom field to the given values structure (or vise versa)
    //depending on the copy direction specified by the from_dom argument. The
    //page arguement is not important for field-to-field data copying
    this.copy = function(from_dom, page, dom_field_view, values){//column_relation
        //
        //Step through all the subfields of a relation field. The index of the 
        //subfield is one of the following: primary, output or id.
        for(var index in this.subfields){
            //
            //Get the (basic) field name of the i'th subfield
            var fname = this.subfields[index].name;
            //
            //Get from the dom field the child element that matches the index.
            //Now the child is either primary, id or output
            var child = dom_field_view.querySelector(index);
            //
            //Now copy the data from values structure to the dom field texy 
            //content (or vice versa depending desired direction)
            switch(from_dom){
                //
                //Copy data from the dom field to the given data structure. 
                case true:
                    values[fname] = child.textContent;
                    break;
                //
                //Copy data from data structure to the text content of the dom 
                //field.
                case false:
                    //
                    child.textContent = values[fname];
                    break
            }
        }
    };
    
    //The basic field name of a relation js field is the name of the
    //id subfield. In contrast, that of a normal field is the same as the
    //field's name
    this.get_fname = function(index){
        //
        return this.subfields[index].name;
    };
    
    //Retirns the field value of the indexed subfield
    this.get_value = function(index){
        //
        //Get the subfield's name
        var fname = this.get_fname(index);
        //
        //Retrieve the named value from this field's values
        return this.values[fname];
    };
    
}
//The mutall page models an ordinary web page. 
function page_home(input_){
    //Declare a global variable position for top-1, right=2, bottom=3, and left=4
    var position;
    //
    //Calling the parent page. We inherite from page to access
    // all functionality but mostly ajax.
    page.call(this, input_);
    //
    //
    //
    //the menu function to support mobile layout in case the media query is triggered
    //this function adds a class responsive on the menu items to enable responsiveness
        this.menuFunction = function()  {
        var x = document.getElementById("definer-list");
        if (x.className === "definer") {
            x.className += " responsive";
        } else {
            x.className = "definer";
        }
    };
    //When the menu is clicked we want to lose the responsive classname
    //this function loops over the whole definer menu, selects one of which when clicked
    //on the class responsive is removed.
        var list = document.querySelector("#definer-list");
            var list_items = list.getElementsByTagName("li");
            for (var i = 0; i < list_items.length; i++) {
                list_items[i].onclick=function(){
                    list.classList.remove("responsive");
                };
            };
    //
    //Start the websupport services for all websites
    //
    //Make the page editable
    this.editmode = function(input, website){
        //Get all the record elements
        var records = document.querySelectorAll("record");
        //set the onlick select attribute on the record
        records.forEach(function(record){
                record.setAttribute("onclick", `${website}.select_page(this)`);
                var contents = record.querySelectorAll("content");
                    contents.forEach (function(content){
                    
                        
                    content.setAttribute("onclick", `${website}.select_content(this)`);
                });
            
            //
            //
        });
        alert("Page ready for Editing... Double click to edit record... Single click to edit content");

    };
    //
    //Create the list of records being edited
    this.record ={};
    //
    //Select record to be edited
    this.select_page = function(record) {
        
        //
        //Get the record's id
        var id = record.id;
        //
        //Store the original text of the record to support discard
        var original_text=record.innerHTML;
        //
        //Compile the data to save in the record array, indexed by the id
        this.record[id] = {
            original_text:original_text,
            record:record
        };
        //
        //Prepare to retrieve the xml of the identified record from the server
        //
        //Pass the record id to the server
        var querystring = {
            id:id,
            //
            //Pass the children of the selected record to the server. It is the 
            //innerHTML of the record
            children:record.innerHTML
        };
        //
        //Execute the select_page method of the mutall_data class on the
        //server and expect a result that has 3 components:
        //result.extra = id, result.html is the xml of interest if the
        //process was successful
       this.ajax("select_page", querystring, "json", this.select_page_ex);
    };
    
    //Continue select method page on returning from the server with given result
    this.select_page_ex = function (select) {
        //
        //Get the record id, after the server call; remeber we expect 
        //it from the extra data
        var id2 = select.extra;
        //
        //Retrieve the identified document record
        var record2 = document.getElementById(id2);
        //
        //Make the record editable 
        record2.innerHTML = select.html;
        //
        //Stopping the new record from receiving click events untill it 
        //is saved.
        record2.removeAttribute("onclick");
        //
        //Scroll the element into the visible area of the browser
        record2.scrollIntoView();
    };
    //
    //Modify the given record; it will be saved to the server by the save function
    this.edit_page = function(div) {
        //
        //Get the parent of this div
        var parent = div.parentNode;
        //Get the record's id
        var id   = parent.id;
        //
        //Prepare to retrieve the xml of the identified record from the server
        //
        //Pass the record id to the server
        var querystring = {
            id:id
        };
        //
        //Execute the edit-page method of the mutall_data class on the
        //server and expect a result that has 3 components:
        //result.extra = id, result.html is the xml of interest if the
        //process was successful
       this.ajax("edit_page", querystring, "json", this.edit_page_ex);
    };
    
    //Continue editing the page on returning from the server with given result
    this.edit_page_ex = function (result) {
        //
        //Continue editing subject to the returned (error) status
        switch(result.status){
            
            //
            //An xml text for the required record was returned succesfully 
            case "ok":
                //
                //Get the div to be edited
                var div = document.querySelector("record>children");
                //
                //Make the outerHTML of the div editable 
                div.outerHTML = result.html;

                //Stopping the new record from receiving click events untill it 
                //is saved.
                div.removeAttribute("onclick");
               break;
            //
            //There was an execution error. Error report not working because 
            //domdocument is not throwing exceptions.
            //Research on why domdocument is not throwing exceptions
            case "error":
                //
                //Get the error element to be reported
                var error = document.getElementById("error");
                //
                //Display the reported error
                error.innerHTML = result.html;
            break;
        //
        //(Check your status or something is wron with the ajax method) 
        default:
            alert("Ajax return status "+result.status+" is not known ");
        }

    }; 
     
    //Save changes (in the textarea) made to the XML document on the server
    this.save = function(mydiv){
        //
        //Get the parent of the div
        var parent = mydiv.parentNode;
        //Get the id of the saved record
        var id = parent.id;
        //
        //Saving the changes
        //
        //Retrieve the modified record's chilren to be saved; its the value of 
        //the items in the textarea. To do this:-
        //Find the textarea from the div
        var textarea = parent.getElementsByTagName("textarea")[0];
        //
        //Find the children of the textarea i.e the value of the items in textarea
        var children_xml = textarea.value;
        //
        //Prepare for the ajax function
        //In the querrystring pass the record id and the xml associated with the 
        //element to be saved to the server
        var querystring = {
            id:id,
            xml:children_xml
        };
        
        //In the ajax function the method=save, the querystring holds the extra 
        //information sent to the server, the expected_output=json and the function
        //to be executed is the save function
        this.ajax("save", querystring, "json", this.save_ex);
    };
    this.save_ex = function (xout) {
        //
        //If expected output is a json we expect three results, 
        //xout.status, xout.html, and xout.extra
        if (xout.status==="ok"){
            //
            //Get the id and the xml from the server of the rceord that has just
            //been modified
            var id2 = xout.extra.id;
            var xml= xout.extra.xml;
            //
            //Locate the identified record from current document
            var record2=document.getElementById(id2);
            //
            //Replace whater it has with the xml
            record2.innerHTML = xml;
            //
            //Restore the loss of focus event 
            record2.setAttribute("onclick", "mutall_data.select_page(this)");
            //
            //Load any images that may have been affected -- if necessary
        }
        return;
     };
     //
     //Discard the changes in the textarea
    this.discard = function(mydiv){
        //
        //Get the id of the saved record
        var id = mydiv.id;
        //
        //Get the record element matching the textarea
        record = this.record[id].record;
        //Discard the xml and resort to the original
        //Replace its inner html with the original one
        record.innerHTML = this.record[id].original_text;
        //
        //Restore the onclick even listener
        record.setAttribute("onclick", "mutall_data.select_page(this)");
        //
        return;
    };
    this.delete = function(record){
        //
        //Get the parents of this div
        var parent = record.parentNode;
        
        //Get the id of the saved record
        var id = parent.id;
        //
        //Delete the record
        //
        //Prepare the ajax function
        //Pass the id of the record to the server and all its children
        var querystring = {
            id:id
        };
        this.ajax("delete", querystring, "json", this.delete);
       
        return;
    };
    
    
    //Insert a new node, nw,  above the existing one, ex. 
    this.insertrecord = function(ref, position){
        //Using the declared global variable, assign it the incoming argument
        this.position = position;
        
        
       //The existin one is the perent of the reference button
       var ex = ref.parentNode;
       //
        //Get the id of the record from the reference node
        var id = ex.id;
        //
        //Insert a record above
        //
        //Prepare for the ajax function
        //In the querrystring pass the record id associated with the element 
        //where a new record will be inserted
        var querystring = {
            id,
            position
        };
        
        //In the ajax function the method=insertbefore, the querystring holds the extra 
        //information sent to the server, the expected_output=json and the function
        //to be executed is the insertbefore function
        this.ajax("insertrecord", querystring, "json", this.insertrecord_ex);
    };
    this.insertrecord_ex = function (result) {
        //
        //If expected output is a json we expect three results, 
        //xout.status, xout.html, and xout.extra
        if (result.status==="ok"){
            //
            //Get the id from the server of the rceord to be discarded and the 
            //new xml to be inserted above it
            var id2 = result.extra.id;
            var new_xml= result.extra.new_xml;
            //
            //Get the record element matching the record being discarded
            var record = this.record[id2].record;
            //
            //Replace whatever it has with the original text stored in the selection
            record.innerHTML = this.record[id2].original_text;
            //
            //Restore the onclick function to enable selection
            record.setAttribute("onclick", "mutall_data.select_page(this)");
            
            //Create a new record and insert its children from the server
            var new_record = document.createElement("record");
            //
            //Insert the children in its innerHTML
            new_record.innerHTML= new_xml;
            //
            //Generate a new id to be used in identifying the record being inserted
            //Creates a string that can be used for dynamic id attributes
            //Example: "id-so7567s1pcpojemi" @returns {string}
            var new_id = function(){
            return Math.random().toString(36).substr(2, 9);

            };
            //Add the id to the new record created
            new_record.id = new_id();
            
            //Add the old id as an attribute to the newly created record
            var old_id = document.createAttribute("old_id");
            old_id.value=id2;
            new_record.setAttributeNode(old_id);
            console.log(result);
            
            if(this.position === 1){
                record.insertAdjacentElement('beforebegin', new_record);
            }else if(this.position === 3){
                record.insertAdjacentElement('afterend', new_record);
            }
                    
        }
     };
     
     //
     //Save values in the textarea (in the new record created) and write to 
     //the xml document
    this.save_record = function(mydiv){
        //
        //Get the parent of the div
        var parent = mydiv.parentNode;
        //Get the id of the saved record
        var id = parent.id;
        //
        //Get the old id, it is the attribute set in the new record inserted
        var old_id= document.getElementById(id);
        var id3= old_id.getAttribute('old_id');
        //
        //Saving the changes
        //
        //Retrieve the modified record's chilren to be saved; its the value of 
        //the items in the textarea. To do this:-
        //Find the textarea from the parent
        var textarea = parent.getElementsByTagName("textarea")[0];
        //
        //Find the children of the textarea i.e the value of the items in textarea
        var children_xml = textarea.value;
        //
        //Prepare for the ajax function
        //In the querrystring pass the record id (including the old id)and 
        //the xml associated with the element to be saved to the server
        var querystring = {
            id:id,
            id3:id3,
            xml:children_xml,
            position:this.position
        };
        
        //In the ajax function the method=save, the querystring holds the extra 
        //information sent to the server, the expected_output=json and the function
        //to be executed is the save function
        this.ajax("save_record", querystring, "json", this.save_record_ex);
    };
    this.save_record_ex = function (xout) {
        //
        //If expected output is a json we expect three results, 
        //xout.status, xout.html, and xout.extra
        if (xout.status==="ok"){
            //
            //Get the id and the xml from the server of the rceord that has just
            //been modified
            var id2 = xout.extra.id;
            var xml= xout.extra.xml;
            //
            //Locate the identified record from current document
            var record2=document.getElementById(id2);
            //
            //Replace whater it has with the xml
            record2.innerHTML = xml;
            //
            //Restore the loss of focus event 
            record2.setAttribute("onclick", "mutall_data.select_page(this)");
        }
     };
     //
     //Discard the changes in the textarea
    this.discard_insert = function(mydiv){
        //
        //Get the parent of the record
        var parent = mydiv.parentNode;
        //Get the id of the record to discard
        var id = parent.id;
        //
        //Get the parent of the parent
        var par = parent.parentNode;
        //
        //Get the element associated with this id
        var i = document.getElementById(id);
        //Get the record element matching the textarea
        par.removeChild(i);
        
    };
    //Start editing of the content
    //Store a list of all contents being edited
    this.content ={};
    //Select the content for editing
    this.select_content = function(content) {
        //
        //Get the content's id
        var id = content.id;
        //
        //Store the original text of the content to support discard
        var original_text=content.innerHTML;
        //
        //Compile the data to save in the content array, indexed by the id
        this.content[id] = {
            original_text:original_text,
            content:content
        };
        //
        //Prepare to retrieve the xml of the identified content from the server
        //
        //Pass the content's id to the server
        var querystring = {
            id:id,
            //
            //Pass the children of the selected content to the server. It is the 
            //innerHTML of the content
            children:content.innerHTML
        };
        //
        //Execute the select_content method of the mutall_data class on the
        //server and expect a result that has 3 components:
        //result.extra = id, result.html is the xml of interest if the
        //process was successful
       this.ajax("select_content", querystring, "json", this.select_content_ex);
    };
    
    //Continue select method page on returning from the server with given result
    this.select_content_ex = function (select) {
        //
        //Get the content id, after the server call; remeber we expect 
        //it from the extra data coming from the server
        var id2 = select.extra;
        //
        //Retrieve the identified document content
        var record2 = document.getElementById(id2);
        //
        //Make the content editable 
        record2.innerHTML = select.html;
        //
        //Stopping the new content area from receiving click events untill it 
        //is saved.
        record2.removeAttribute("onclick");
        //
        //Scroll the element into the visible area of the browser
        record2.scrollIntoView();
    };
    //
    //Modify the given content; it will be saved to the server by the save function
    this.edit_content = function(content) {
        //
        //Get the parent of this div
        var parent = content.parentNode;
        //Get the content's id
        var id   = parent.id;
        //
        //Prepare to retrieve the xml of the identified content from the server
        //
        //Pass the content id to the server
        var querystring = {
            id:id
        };
        //
        //Execute the edit-content method of the mutall_data class on the
        //server and expect a result that has 3 components:
        //result.extra = id, result.html is the xml of interest if the
        //process was successful
       this.ajax("edit_content", querystring, "json", this.edit_content_ex);
    };
    
    //Continue editing the content on returning from the server with given result
    this.edit_content_ex = function (result) {
        //
        //Continue editing subject to the returned (error) status
        switch(result.status){
            
            //
            //An xml text for the required content was returned succesfully 
            case "ok":
                //
                //Get the content to be edited
                var div = document.querySelector("content>children");
                //
                //Make the outerHTML of the div editable 
                div.outerHTML = result.html;

                //Stopping the new record from receiving click events untill it 
                //is saved.
                div.removeAttribute("onclick");
               break;
            //
            //There was an execution error. Error report not working because 
            //domdocument is not throwing exceptions.
            case "error":
                //
                //Get the error element to be reported
                var error = document.getElementById("error");
                //
                //Display the reported error
                error.innerHTML = result.html;
            break;
        //
        //(Check your status or something is wron with the ajax method) 
        default:
            alert("Ajax return status "+result.status+" is not known ");
        }

    }; 
    //
    //Save changes (in the textarea) made to the XML document on the server
    this.save_content = function(mydiv){
        //
        //Get the parent of the div
        var parent = mydiv.parentNode;
        //Get the id of the saved record
        var id = parent.id;
        //
        //Saving the changes
        //
        //Retrieve the modified content's chilren to be saved; its the value of 
        //the items in the textarea. To do this:-
        //Find the textarea from the div
        var textarea = parent.getElementsByTagName("textarea")[0];
        //
        //Find the children of the textarea i.e the value of the items in textarea
        var children_xml = textarea.value;
        //
        //Prepare for the ajax function
        //In the querrystring pass the record id and the xml associated with the 
        //element to be saved to the server
        var querystring = {
            id:id,
            xml:children_xml
        };
        
        //In the ajax function the method=save_content, the querystring holds the extra 
        //information sent to the server, the expected_output=json and the function
        //to be executed is the save_content function
        this.ajax("save_content", querystring, "json", this.save_content_ex);
    };
    this.save_content_ex = function (xout) {
        //
        //If expected output is a json we expect three results, 
        //xout.status, xout.html, and xout.extra
        if (xout.status==="ok"){
            //
            //Get the id and the xml from the server of the content that has just
            //been modified
            var id2 = xout.extra.id;
            var xml= xout.extra.xml;
            //
            //Locate the identified content from current document
            var record2=document.getElementById(id2);
            //
            //Replace whater it has with the xml
            record2.innerHTML = xml;
            //
            //Restore the loss of focus event 
            record2.setAttribute("onclick", "mutall_data.select_content(this)");
            //
            //Load any images that may have been affected -- if necessary
        }
        return;
     };
     this.discard_content = function(mydiv){
        //
        //Get the parent of this div
        var parent = mydiv.parentNode;
        //
        //Get the id of the saved content
        var id = parent.id;
        //
        //Get the content element matching the textarea
        var content = this.content[id].content;
        var original_text = this.content[id].original_text;
    
        //Discard the xml and resort to the original
        //Replace its inner html of the content with the original one
        content.innerHTML = this.content[id].original_text;
        //
        //Restore the onclick even listener
        content.setAttribute("onclick", "mutall_data.select_content(this)");
        //
        return;
    };
}
//The mutall page models an ordinary web page. 
function page(input_){
    //
    //Initialize the parent mutall system. This is done here so that 
    //properties that match the ones in input_ can be overriden by the 
    //constructor
     mutall.call(this, input_);
     //
    //Activate the driver (data) component, so that we can treat it as a js data object
    //rather than just an ordinary object. Note, if there was a data property
    //in the static input_, it will be overriden at this point.
    this.driver = this.activate(this.driver);
    //
    //Activate the layout component
    this.layout = this.activate(this.layout);
    //
    //Activate the mode component
    this.mode = this.activate(this.mode);
    //
    //Save this page to the global session variable under the mutall id, so that
    //it can be re-opened on coming back
    this.save_to_session = function(){
        //
        //Collect the data for re-starting this session; this is the complete
        //query string that comprises of:-
        //- the class name of the page object
        //- the method, i.e., module to execute
        //- all the data necessary to exceute the method
        //
        //Compile the this page's query strin array into a "standard
        //query string", e.g., name=peter&age=20
        std_str = this.compile_std_querystring(this.querystring);
        //
        //Add this pages filename to complete the query string
        complete_str = this.filename + "? ", std_str;
        //
        //Make the query string to send to the server
        var qstring = {
            //Encode the entire query string that was used to evoke this page
            //Note: the querystring variable was passed on from PHP environment
            //when during this page's construction, e.g., 
            //page_reccords = new page_records(<?php $echo page_records;?>
            querystring:complete_str
        };
        //Set the desired module for saving of a sesion
        //The expected output is json string whose data property is set to the
        //ajax result -- if its type property is not set to "error". The result
        //is an ok string if there was  no error
        this.ajax("save_to_session", qstring, "json", function(result){
            //
            //If ok, report ok...
            if (result.extra ==="ok"){
                alert("ok");
            }
            //
            //...otherwise show the error message
            else{
                this.show_error_msg(result);
            };
        });
    };    
     
    //The ajax method sends requests to the server to execute a specific method
    //on desired objects of some class.
    //
    //It has the following arguments:-
    //(1) the method to execute on an object of some given class name
    //    By default the class name is the same as that of this ajax caller, but
    //    it can be overriden using the 5th argument -- the optional classname
    //(2) the querystring is the actual data to be posted to the server and must
    //    be sufficient to (a) create the object of the named class and (b) execute 
    //    the desired method on the object
    //(3) the expected data type of the response, i.e., how to interpret the
    //    response text returned from the server. This parameter is used by the
    //    next argument - exec.
    //(4) exec is the call back method to run when the server is ready with a 
    //    result. It has the signature, exec(result), where result depends on the
    //    expectation specified in argument 3. If the expected result is 
    //    "html", then the result is interpreted as such; if it is "json", then 
    //    it is interpreted as an object with 3 properties, viz., 
    //      i: the status of the returned data, either, "ok" or "error"
    //      ii: the html code returned by the server and
    //      iii: any extra data retuned by executing the requested method on the
    //      requested object.
    //(5) as mentioned in (1), this argument is used for overriding the default
    //    the class name of the requested method
    this.ajax = function(method, querystring, expected_output, exec, classname=null){
        //
        //Expand the given querystring with following standard ajax parameters
        //
        //The class name of the method to execute is either explicitly given by
        //the user or it is derived from this class. The classname is paricularly
        //important if it is unrelated to this one. In the case when a desired
        //class is an ancestor of this one, our ajax method will use inheritance
        //to locate the nearest ansector that implements the desired module
        querystring.classname = classname===null ? this.classname: classname;
        //
        //Add the method to execute
        querystring.method = method;
        //
        //All functions called via the ajax method should anounce this fact. 
        //Do we still need this data, now  that open_windows calles an actual 
        //file? Perhaps in futue, even teh window calls will be via this function
        //with a re-direction to appropriate file. Lets see.
        querystring.type = "ajax";
        //
        //How will the results be interpreted when they come back to the client?
        //as a json string or html?
        querystring.expected_output = expected_output;
        //
        //Create a new xml http request object to allow communication with 
        //the server
        var xhttp = new XMLHttpRequest();
        //
        //Freeze this object to allow reference to it with functions whose
        //object is windows
        var this_ = this;
        //
        //The expected data to be returned is is text; this is more flexible
        //than other data types, especially if errors are a possibility
        xhttp.responseType = "text";
        //
        //On return, execute the exec function; it has the following signature
        //(mutall this_, string data)
        xhttp.onreadystatechange = function (){
            //
            //Check if the request is ready with no errors
            if (this.readyState === 4 && this.status === 200){
                //
                //
                //Set the expected output; the default is html
                var xout = typeof expected_output==="undefined"
                           ? "??" 
                           : expected_output;
                //            
                switch (xout){
                    case "json":
                        //
                        //The response text needs to be converted to a json 
                        //object before we can continue the processing
                        this_.handle_json_result(exec, this.responseText);
                        break;
                    //    
                    case "html":
                        //
                        //Execute the requested function with the response text 
                        //without processing the response text.Do away with one of
                        //this_
                        exec.call(this_, this.responseText);
                        break;
                    //    
                    default:
                        alert("Requested ajax output " + xout + " is not known");
                };
            }
        };
        //
        //The filename to execute is the entry point for all mutall ajax calls.
        //Mind the version of the buis -- set from PHP side
        filename = this.version + "/ajax.php";
        //
        //Use the post method to save the record. Save means transferring the
        //data from teh volatile $_POST global variable to the more stable
        //$_SESSION variable. Posting is needed as (a) it can handle large data
        //and (2) the passwords will not be vsible, i.e, more secure
        xhttp.open("post", filename);
        //
        //Send a request header that tells the post method that we are sending it
        //content of the json string type (not just any string)
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        //
        //Convert the querystring structre to teh standard query string format
        //e.g., name=peter&age=25&location=kiserian
        var qstring = this.compile_std_querystring(querystring);
        //
        //Send the standard querystring text to the server
        xhttp.send(qstring);
    };
    
    
    //Handle the result when we expect it to be a json string
    this.handle_json_result = function(exec, js){
        //
        //See if we can json parse the result in order to separate errors 
        //from a successful case;
        try {
            var result = JSON.parse(js);
        }
        //
        //No we cannot json parse the result. An error must have occured.
        //Report it
        catch(e){
            //
            //Parsing could not be done. The actual json error is not important.
            //Report the json text as an error
            this.show_error_msg(js);
            //
            return false;
        }    
        //
        //Yes we can parse the response; check the status
        switch(result.status){
            //
            //Report the data as the error message
            case "ok":
                //
                //Return teh entire result to the caller.
                exec.call(this, result);break;
            case "error":
                //
                //The error message is in the html property of the result
                this.show_error_msg(result.html);
                break;
            //
            //Unknown status
            default :
                //
                //The error message is in the html property of the result
                this.show_error_msg("The status " + result.status + " is not known");
        }

    };
    
 
     
    //
    //Save this pages index to the windows local storage
    this.save_index = function(){
        //
        //Get the name of the index
        var name = this.index;
        //
        //Discontinue if the index name is not efined
        if (name==="undefined"){
            return false;
        }
        //
        //Get the index valus
        var value = this[name];
        //
        //Discontinue if the value is not defined
        if (value==="undefined"){
            return false;
        }
        //
        //Check if the value node is available in the windows local storage
        if (typeof window.localStorage.id === "undefined"){
            //
            //It is not available; initialize it with no data
            window.localStorage.id = "{}";
        }
        //
        //Convert the storage into an object
        var obj = JSON.parse(window.localStorage.id);
        //
        //Add the value, using the given name
        obj[name]=value;
        //
        //Re-set the local storage
        window.localStorage.id=JSON.stringify(obj);
    };
    
  //load elements from the server using the onload method  
  this.onload = function () {
      
    
    //Retrieve all the load elements
    var loads = window.document.querySelectorAll("load");
    
    //Define variables to use
    var load=null;
    var id=null;
    var method=null;
    //
    //
    //Step thru each one of them and load it
    for(var i=0; i<loads.length; i++){
        //
        load=loads[i];
        method=load.getAttribute("method");
        //
        //Load the method and place the resulting data in the id 
         //
         //Use the ajax to transform the elements from php into html
         //the var query string passes the extra information we want
         //to pass to the server. In this case, we are letting the server
         //know that for every loaded element, it should assign the id and 
         //load before the for loop is continued.
        var querystring = {
            i:i
        };
        //this is where we declare the expected output for the ajax method
        var expected_output = "json";
        
        //if the expected output is a json, the result expected contains
        //three elements including the status, the html to be displayed and
        //any additional information we want from the server
        var exec = function (result) {
            
            //result.status, .html, .extra
            if (result.status==="ok"){
                //
                //Get the i'th load tag
                load = loads[result.extra];
                //
                //Retrieve the id and method attributes
                id = load.getAttribute("id");    
                
                //this is the ajax execute method. this is where the declared 
                //functions are executed by ajax and displayed depending on the
                //expected output.
                var element = window.document.getElementById(id);
                element.innerHTML = result.html;
            }
        };

        this.ajax(method, querystring, expected_output, exec);
    }
};
    
     

    
    
    //The open window method extends the javascript window.open() method with
    //an ability to return the data needed to update the caller page. The windows 
    //open method has the signature:-
    //
    //win open(url, ...)
    //
    //where the returned win is the newly opened window and the url is a  
    //string made of the filename to serve plus the its requirent as a querystring.
    //In contrast, the mutall version has the following signature:-
    //
    //win open_window($filename, jsquerystring, onfinish, ...)
    //
    //You note that rather than pass the a url, we pass only the filename,
    //complete with its protoco and host componetsto the server. The main 
    //differences are that (a) the querystring is passed as a js object and (b)
    //more importantly, extra parameter, onfinish. It is a call back function
    //which is executed when win is exited properly; that means saving new data
    //to property $mutall.id in the returned window (win) befoer exiting it. 
    //The function has the following signature:-
    //
    //onfinish(data)
    //
    //where data is the object comprising of data constructed by teh function that
    //closes this window.
    //
    //A new page can be initiated from any mutall object. This mechanism allows 
    //us to move from one page to another; becuse it relies on the javascript's 
    //window.open method, it can pass to the server ony a limited amount of 
    //data, so the earlier practice of sending a whole page was stopped. That 
    //was the down side, because it meant that we have to be careful 
    //to pass only the critical amount of data to the sever -- the requirement. 
    //The positive aspect is that the method allows the called page to evoke 
    //the onload method which we relied on to build a complex page.
    //
    //This method of talking to the server assumes that the user needs to 
    //interact with the page. This is in contrast to the ajax method which
    //talks to the server without any user intervention and has no data 
    //limitation because we talk to the server via the post command - rather
    //than get.
    //
    //This version uses a query string to pass data to the server
    this.open_window = function(filename, jsquerystring, onfinish=null, specs=""){
        //
        //The system window.open command uses the get method.
        //Convert the input data to a json string; how do you tell if the 
        //conversion was successful?
        var qstring = this.compile_std_querystring(jsquerystring);
        //
        //Compile the required url; mutall_id is the name of index in the 
        //PHP global $_GET variable that is used for accessing this data
        var href = filename + "?"+ qstring;
        //
        //Now use the window open method to finish the job. Use the _blank 
        //window name to force a new window, so that win and window are 
        //different
        var win = window.open(href, "_blank", specs);
        //
        //Set a timer so that we can check periodically when the new window 
        //is closed by the user
        //
        //Freeze "this" object in order to reference it within the anonymous 
        //function below
        var this_ = this;
        //
        //Wait for the window to be closed, checking after 100 milliseconds
        var timer = setInterval(function(){
            //
            //If the opened window is closed, clear the timer first, then 
            //check to see if the closing was "proper" or not
            if (win.closed)
            {
                //
                //Clear the timer
                clearInterval(timer);
                //
                //Check if the window was propely closed, i.e., if it was closed
                //via the mutall close window menu or via the general window close
                //option. We assume that when "win" was properly closed, the 
                //property "this_.mutall_id" of window "win" is set to some
                //data. The call back function heeds to be able to interpret
                //the data -- depending on  the expectation
                var data = win[this_.mutall_id];
                //
                //Use the data with the call back only if both are valid
                if (onfinish!==null && typeof data !=="undefined"){
                    //
                    //Execute the onfinish function. This function ensures 
                    //that this page is updated, depending on who opened
                    //the window 
                    onfinish.call(this_, data);
                }
            }
        }, 100);
        //
        //Return the opened window -- just in case the caller wants to interrogate 
        //it for furtjer details.
        return win;
    };
    
    //Show the last record that was selected
    this.show_selection=function(){
        //
        //If this tabular layout does not define id, enforce it and set it to 
        //false. Note: the id is provided during table construction. The argument
        //may be ommited
        if (typeof this.id==="undefined") {this.id = false; }
        //
        //Use this default layout's id if it is valid, otherwise read if off the
        //windows local storage
        if (!this.id){this.id=this.get_id(this.index); }
        //
        //If the id is not valid discontinue the show
        if (!this.id) {return;}
        //
        //Now do the hreferencing.
        //
        //1. Mark the requested row
        //
        //Get the dom record with the given id
        var dom_record  = document.getElementById(this.id);
        //
        //If there is no row that matches the given id, then probably it does 
        //not exist. Perhaps it was deleted. Do not continue
        if (dom_record===null) {return;}
        //
        //Make the requested dom record as the only current
        this.select_dom_record(dom_record);
        //
        //2. Scroll the requested row to view -- thus completing the 
        //hreferencing
        window.location.href="#" + this.id;
    };
    
    //
    //Show the current selections after refreshing this page. This means a 
    //number of things. 1) mark a record as current. 2) mark a field in that 
    //record ascurrent. 3) scroll the record into view
    this.show_current = function(){
      //
      //Get the index value of the current record
      //
      //If the index name is not set, then there is no index value; otherwise 
      //return use it
      if (typeof this.index ==="undefined"){ return;}
      //
      //If the index value is not defined, return; otherwise use it
      if (typeof this[this.index]==="undefned"){ return; }
      //
      var index_value = this[this.index];
      //
      //Search for the dom record with this id attriute
      //
      //Limit the search to the dom page of this js page
      var dom_page = this.get_dom_page();
      //
      //Formulate the css selector for the current record
      var cssr = this.layout.record_tag_name + "[id='" + index_value + "']";
      //
      //Get the dom record
      var dom_record = dom_page.querySelector(cssr);
      //
      //Continue only if the recrod is found
      if (dom_record===null){ return; }
      //
      //Mark this record as current
      dom_record.setAttribute("current", "record");
      //
      //Select the curent field
      //
      //This proceeds only if the current field name is valid
      if (typeof this.current_fname!=="undefined"){
        //
        //Retrieve the current dom field from the dom record
        //
        //Compile te css field selector
        var cssf = this.layout.field_tag_name+"[name='" + this.current_fname + "']";
        //
        //Get the domfield
        var dom_field = dom_record.querySelector(cssf); 
        //
        //If found, set its current attribute
        if (dom_field!==null){
            dom_field.setAttribute("current", "field");    
        }
      }
      //
      //Scroll the dom record into view, placing the recrod at the bottom of the
      //view area
      dom_record.scrollIntoView(false);
    };
    
    //Mark the given dom field, df, as the only current field. Why are not setting 
    //this page's field to the id of df??
    this.select_dom_field = function(df){
        //  
        //Select all currently marked dom fields
        var dfs  = document.querySelectorAll("[current='field']");
        //
        //Remove the current attribute from them
        for(var i=0; i<dfs.length; i++)
        {
            dfs[i].removeAttribute("current");
        }
        //  
        //Make the given dom field as current
        df.setAttribute("current", "field");
        //
        //Transfer the dom field's name to this javsacript page. The field name 
        //is used for re-highlingting the field after a page refresh.
        this.current_fname = df.getAttribute("name");
        
    };
    
    // 
    //View the records of the currently selected list of tables or records. 
    //Tables are accessed from page_database and records from any of 
    //page_crud's descendants; this is the justification of this view_records 
    //function being implemented at the page level. That means that this option
    //can be evoked from any page
    this.view_records = function(){
        //
        //Ensure that a table has been selected before opening a page of the 
        //table's records
        this.wait(
            //
            //Show this message as a wizzard to guide the user. Waiting for... 
            "select a table to view its records",
            //
            //This is is called when we need to test if a table name has been 
            //selected
            "tname_is_selected",
            //
            //This function, view the page of the tname's records, is executed on 
            //selecting a table name, 
            //The page of tables has nothing to report after viewing 
            //records; so the "next" action after that is left out
            function(){
                //
                //The best server file to deliver the needed servive is the page_records.
                //Compile its data (seed) requirements
                var qstring = {
                    //
                    //The table name
                    tname: this.tname,
                    //
                    //The underlying database as selected by teh user
                    dbname: this.dbname
                };
                //
                //Request the server to show the records with default styling.
                //Don't bother with the returned results.
                this.open_window("page_records.php", qstring);
            }
        );
    };


    
    //
    //Set the table name; this function is called by page.view_records
    //to signal the end of a wait to select a table whose records we want
    //to view. By default, no waiting is needed: the table name is
    //already set
    this.tname_is_selected = function(){
      //
      return true;
    };
    
    //
    //Make the given dom descendant as the only current of all descendants and
    //set the decesnatts table neme
    this.select_dom_descendant = function(descendant){
        //
        //Set this page's descendant table name; its the id of the 
        //dom descendant
        this.descendant = descendant.getAttribute('id');
        //
        //Select all dom descendants marked current
        //
        //Formulate the css selector  for a dom descendant
        var dselector = "[current='descendant']";
        //
        //Now do the selection, searching from the entire document
        var descendants  = document.querySelectorAll(dselector);
        //
        //Remove the current attribute from them
        for(var i=0; i<descendants.length; i++)
        {
            descendants[i].removeAttribute("current");
        }
        //  
        //Mark the given descendant as the current one
        descendant.setAttribute("current", "descendant");
    };
    
    //Make the given dom record as only record one marked current and update
    //the page's index (name amd value(
    this.select_dom_record = function(dr){
        //
        //Set this page's property that matches its index to the id value in 
        //dr. This is how Mutall moves data from the dom record to this page --
        //a proces that is important because it is the page data that gets 
        //moved around.
        //For the page_databases, its the index "dbname"; for the page_database 
        //it is "tname"; for page_records its the field::id  
        var id = dr.getAttribute('id');
        //
        //Setting of the id is valid only if it is exists; for new records doed 
        //not.
        if (id!==null){
            this[this.index]= id;
        };
        //  
        //Select all dom records marked current
        //
        //Formulate the appropriate dom record selector
        var rselector = "[current='record']";
        //
        //Select all the records from the entire document. Only one record is 
        //expected to be current -- so we dont need to limit ourselves to this
        //js page's dom page.
        var trs  = document.querySelectorAll(rselector);
        //
        //Remove the current attribute from them
        for(var i=0; i<trs.length; i++)
        {
            trs[i].removeAttribute("current");
        }
        //  
        //Mark the given dom record as current
        dr.setAttribute("current", "record");
    };
    
    //On changing an simple (text) input, update this page's querystring directly
    this.onchange = function(input){
        //
        //Get teh type of input
        var type = input.getAttribute('type');
         //
        //Get the id of the desired property from the input element
        var id = input.getAttribute('id');
        //
        //Get this page's  querystring (array)
        var qstring = this.arr;
        //
        //The qstring pro[erty setting depends on the type input element
        switch(type) {
            //
            //A user interacts with a checkbox through the checked property
            case "checkbox":
                //
                //Get the check status
                var value = input.checked;
                //
                //Set the property value
                qstring[id]=value;
                //
                //Rebuild the page immediately after the input -- as we know we 
                //are done with the input
                this.refresh();
                break;
            //
            //A user intercats with other inputs through teh value property
            default:
                //
                //get teh value of the input
                var value = input.value;
                //
                //Set the property value
                qstring[id]=value;
        }
  };
    
   //
   //Close this page properly. This means saving requested data to the window
   //and closing it. If no data is provided, this page object is returned to
   //teh caller; otherwise the requested data is returned
   this.close_window = function(data=null){
       //
       //Save this page's index, if valid, to the local wndow storage
       //this.save_index();
       //
       if (data === null){
         //Save this page to the current windows object
         window[this.mutall_id]=this;
       }
       else{
         window[this.mutall_id]=data;  
       }
       //
       window.close();
   };
   
    //The anhor method sends requests to the server to perform using a complete
    //url. It has the following arguments:-
    //(a) The url request to the server 
    //(b) function to execute on completing the request
    //
    //This (ajax) method of talking to the server does not expect the user to 
    //interact with the mutall object (unlike the open_window method).The 
    //signature of exec is void exec(result) where result has 3 properties:
    //i: the stataus of the retsurned data, viz, ok or error
    //ii: the html from teh server and
    //iii: any extra data depending on the client's request module
    this.anchor = function(url, exec){
        //
        //Create a new xml http request object to allow communication with 
        //the server
        var xhttp = new XMLHttpRequest();
        //
        //Freeze this object to allow reference to it with functions whose
        //object is windows
        var this_ = this;
        //
        //The expected data to be returned is is text; this is more flexible
        //than other data types, especially if errors are a possibility
        //xhttp.responseType = "text";
        //
        //On return, execute the exec function; it has the following signature
        //(mutall this_, string data)
        xhttp.onreadystatechange = function (){
            //
            //Check if the request is ready with no errors
            if (this.readyState === 4 && this.status === 200){
                //
                //Execute the requested function with the response text 
                //without processing the response text.
                exec.call(this_, this.responseText);
                
            }     
        };
        //
        //Use the post method to save the record. Save means transferring the
        //data from teh volatile $_POST global variable to the more stable
        //$_SESSION variable. Posting is needed as (a) it can handle large data
        //and (2) the passwords will not be vsible, i.e, more secure
        xhttp.open("get", url);
        
        //Send a request header that tells the post method that we are sending it
        //content of the json string type (not just any string)
        //xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        //
        //Send the url
        
        xhttp.send("");
    };
    
   
    //Wait for the given test to be successful, then execute next task.
    this.wait = function(
            //
            //The message to show as a wizzard guide in the error element
            msg,
            //
            //A boolean returning function for testing when we should stop
            //waiting, i.e., clear the timer. It has the signature 
            //boolean test()
            test,
            //
            //The function to execute on the above test being true; its signature is
            //void next()
            next
            ){
        //
        //Test if the waiting is necessary or not
        if (this[test]()){
            //
            //The waiting is not necessary; execute the next task
            next.call(this); 
        }
        //
        //The waiting is necessary, i.e, the condition for executing the
        //next() task is not yet met. Start the wait
        else{
            //
            //Update the error message element to show what we are waiting for
            this.show_error_msg("Waiting for you to " + msg + "....");
            //
            //Freeze "this", to avoid confusion of window.this and page.this This 
            //issu is also present in annymous functions in php.
            var this_ = this;
            //
            //Set the timer interval to 100 milliseconds and test for completion
            var timer = setInterval(function(){
                //
                //Test if we should execute the next function or not
                if (test.call(this_)){
                    //
                    //Yes we should. Stop waiting, i.e., clear the timer.
                    clearInterval(timer);
                    //
                    //Clear the wizzard error
                    this_.clear_error_msg();
                    //
                    //Execute the next requested task
                    next.call(this_);
                }
            }, 100);
        }
    };
    
    
    //Returns the dom element that is visually associated with this js page.
    //It is used to:- 
    //(a) confine a query search, partcularly in compound pages.
    //(b) determine where results of an ajax operation should be "written" to
        //refresh a complex page
    // This function establishes the link between the js and dom pages. 
    //By default, it is the articles node of a page -- if it is found. 
    //For special pages, e.g., the descendant, it is the node with the following 
    //css descendant[id=$tname]
    this.get_dom_page = function(){
        //
        //Get the page css expression for this page
        var cssxp = this.cssxp;
        //
        //Use the css expression to locate the page from the entire document
        var dom_page = window.document.querySelector(cssxp);
        //
        //Its  an error if the page is not found
        if (dom_page ===null){
            alert("No dom page found for css page '" + cssxp+"'");    
            return false;
        }
        return dom_page;
    };
    
    
     //
    //Edit a dom field on this page. If the input element is specified, then 
    //we must have launched this function from an input button, for instance, in
    //editing a foreign key field. In that case the relevant field is the parent 
    //to the input; otherwise the search was launched from the input menu in 
    //which case the relevant field is the "current" one
    this.edit_field = function(input=null){//page
        //
        //Lef df be the dom field to edit
        var df;
        // 
        //If we called this function from the some input element, then the dom field
        //should be the parent of the input element
        if (input!==null){
            //
            //Set the dom field to the input's parent node; otherwise search 
            //for it
            df = input.parentNode;
        }
        //
        //..Otherwise it is the current dom field. There has to be one.
        else{
            //Get the "current" dom field
            df = this.get_current_dom_field();
        }
        //
        //Get the field name; it is an attribute of the dom field
        var name = df.getAttribute("name");
        //
        //Retrieve the js field correspnding to the dom field's name. We
        //use the fact that the data property of this page should be an 
        //Sql -- which has js fields. Retrieve the named one
        var field = this.driver.fields[name];
        //
        //Edit the field, passing both the dom field and this page as parameters. 
        //Why is the page needed? Answer: to supply extra data (e.g., login 
        //credentials) that is required for supporting editing operations in 
        //foreign key fields
        field.edit(df, this);
    };
    
    //Returns the current dom field of this page based on the "current" 
    //attribute. An alert is provided if there is no current selection
    this.get_current_dom_field = function (){
        //
        //Try to get the current dom field
        var df = this.try_current_dom_field();
        //
        if (!df){
            alert ("There is no current field selection");
            return false;
        }
        //
        //Return the dom field
        return df;
    };
    
    //Returns the current dom field of this page based on the "current=field" 
    //attribute. No alert is provided if there is no current selection.
    this.try_current_dom_field = function (){
        //
        //Formulate the css selector current field
        var fselector = "[current='field']";
        //
        //Search in the entire document for the current field
        var df = document.querySelector(fselector);
        //
        if (df ===null)
        {
            //Return alse quietly
            return false;
        }
        //
        //Return the dom field
        return df;
    };

}

//The mutall object is the root of all BUIS objects
function mutall(input=null){
    //
    //The constant for accessing the mutall data in the global variables in
    //javascript is imlemented via a global propety, data_id, sinc the keyword
    //Const is not well spported by some browsers, notably IE
    this.mutall_id = "mutall";
    //
    //How do you register the class name (the same way we did in php)? This is 
    //important for mutall classes that are created from Js
    //
    //Offload (without activating)the properties in the input to this object, 
    //if valid. The inheriter of this class will activate the properties of
    //input that it requires
    if (input!==null){
        //
        //Complete the rest of the object
        for(var prop in input){
            //
            //Pass any property to the object -- regardless of its 
            //source
            this[prop] = input[prop];
        }
    };
    //
    //mutallify is the activation of any static structure so that its
    //methods can be accesssed from javascript. Typicaly the static structure will
    //have come from a php class
    this.activate = function (input_){
        //
        //Classify input
        var type = this.classify(input_);
        //
        //Define the active input
        var input;
        // 
        switch(type){
            //
            //mutall objects have a classname; use the class to activate the object
            case "mutall":
                input = this.activate_class(input_.classname, input_);
                //
                //Append the remaining properties of the static input to the 
                //active object
                for(var prop in input_){
                    //
                    //Pass every property of the input to newly created active
                    //object. Remember to activate it unconditionally
                    input[prop] = this.activate(input_[prop]);
                }
                break;
            //
            //For an ordinary object offload all the propepries defined by the 
            case "object":
                //
                input = new Object;
                //
                //Complete the rest of teh object
                for(var prop in input_){
                    //
                    //Pass any property to the object -- regardless of its 
                    //source and remember to activate it
                    input[prop] = this.activate(input_[prop]);
                }       
                break;
            //
            //For the array activate all the components
            case "array":
                //
                //Initialize the collection of active inputs
                var input = [];
                //
                //This is an array. Step through it
                for(var i=0; i<input_.length; i++){
                    //
                    //Let a be the i'th active input
                    var a = this.activate(input_[i]);
                    //
                    input[i] = a;
                }
                break;
            //
            //Any other structure is returned as it is
            default:
                input = input_;
        }
        //
        //Return the active input
        return input;
    };
    
    //Offload the properies of the given data to this record
    this.offload = function(data){
        //        
        //Offload the properties of the equivalent field in PHP -- only if they 
        //are defined in this class
        for(var prop in data){
            //
            this[prop] = data[prop];
        }
    };    
    
   
     
    //Classify the input as either mutall, object, array or other
    this.classify = function(input){
        //
        //Let t be the type of input
        var t = typeof input;
        //
        //Test whether t is an object
        if (t!=="object"){
            //t is not an object. It must be other
            return "other";
        } 
        //
        //If the input is null return other
        if (input===null){
            return "other";
        }
        //
        //This is an object; Test if it is an array or not
        if( Object.prototype.toString.call(t) === '[object Array]' ) {
            //
            //It san array
            return "array";
        }
        //
        //This is an ordinary object. Test it if it is a mutall objct
        //Get the classname
        if (typeof input.classname==="undefined"){
            //
            //This is an ordinary object
            return "object"; 
        }
        //
        //This is a mutall object.
        return "mutall";
    };

    //Activate the given classname using the given static input
    this.activate_class=function (classname, input){//mutall
        //
        //Let a be the active class to be returned
        var a;
        //
        //Consider only class names whose active forms are used in js. Those
        //that are thought not to be useful are not registered
                   
        switch (classname) {
            //
            case "column":
                a = new column(input);
                break;

            case "column_primary":
                a = new column_primary(input);
                break;

            case "column_foreign":
                a = new column_foreign(input);
                break;
            //
            //Activate the record
            case "record":
                //
                //Get the record's (static) fields and activate them
                var fields = this.activate(input.fields);
                //
                //Create the record; the table name  is optional. If input 
                //defines it, the offloading process ensures that it will be 
                //part of the record.
                a = new record(fields, input.dbase,  input.tname, input.reftable, input.stmt, input.values);
                break;
            //
            //Activate the field
            case "field":
                var name = input.name;
                a = new field(name);
                break;
            //    
            case "tabular":
                a = new tabular();
                break;
            //    
            case "label":
                a = new label();
                break;
           //     
            case "mode_input":
                a = new mode_input();
                break;
            //    
            case "mode_output":
                a = new mode_output();
                break;
            //    
            case "column_choices":
                //
                a = new column_choices(input);
                break;
            //
            //No other field type is expected in this version
            default:
                //Log the message
                //console.log("Class " +classname + " is not registered");
                a = input;
        }
        //
        //Return the active class
        return a;
    };
    
    //Returns the standard query string compiled from the the given query 
    //string object which is presented as a set of name/value pairs
    this.compile_std_querystring = function(obj){
      //
      //Collect the url parts, starting with noting
      var parts=[];
      //
      //For each property of the object concatentate it with its value
      for(var prop  in obj){
          //
          //Encode the value and its property for safety sake
          var part = encodeURIComponent(prop) + '=' +  encodeURIComponent(obj[prop]);
          //
          //Save the part
          parts.push(part);  
      }
      //
      //Return the joined parts -- upersand concatenated
      return parts.join('&');
    };
    
    //gravitate moves the row that contains the given checkbox input to the
    //top of ths list just after the header
    this.gravitate = function(input)
    {
        //If we have just unchecked an row, return immediately
        if (input.checked===false) {return;}
        //
        //Retrieve the tr in which the input is found. Its 2 parents up 
        //because of the interevening td
        var tr = input.parentNode.parentNode;
        //
        //Get the parent of tr; its the tbody I suppose
        var tbody = tr.parentElement;
        //
        //Remove this row's tr from tbody
        tbody.removeChild(tr);
        //
        //Add this rows tr to the top of tbody just after the heading
        tbody.insertBefore(tr, tbody.childNodes[1]);
    };
    
    //
    //Retrieve the row id from the local storage
    this.get_id = function(index){
        //
        //If the index is not set, return a false
        if (typeof index==="undefined") {return false;}
        //
        //Check if the id node is available in the windows local storage
        if (typeof window.localStorage.id === "undefined"){return false;}
        //
        //Convert the storage into an object
        var obj = JSON.parse(window.localStorage.id);
        //
        //Test if there is any entry by under the index of interest
        if (typeof obj[index]==="undefined") {return false;}
        //
        //Return the id (of the index)
        return obj[index];
    };
    
    
       
   //The open window method extends the jasvscript window.open() method with
    //an ability to return the mutall page that was used to drive the opened
    //window. The windows open method has the signature:-
    //
    //win open(url, ...)
    //
    //where the returned win is the newly opened window and the url is a query 
    //string made of the filename to serve plus the its requirent. In contrast, 
    //the mutall version has the following signature:-
    //
    //win open_window($classname, requirement, onfinish, ...)
    //
    //You note that rather than pass the file name, we pass a classname to the 
    //function; the file name is derievd simply by adding the php extension. The
    //main difference is the extra parameter, onfinish. It is a function which is 
    //executed when win is exited properly; that means saving the underyling 
    //page object before the window is closed. It has the following signature:-
    //
    //onfinish(classname)
    //
    //where classname is the object named $classname that
    //was used to drive the display for win. This allows us to interrogate it 
    //for data that we require to update the parent window.
    //
    //A new page can be initiated from any mutall object. This mechanism allows 
    //us to move from one page to another; becuse it relies on the javascript's 
    //window.open method, it can pass to the server ony a limited amount of 
    //data, so the earlier practice of sending a whole page was stopped. That 
    //was the down side, because it meant that we have to be careful 
    //to pass only the critical amount of data to the sever -- the requirement. 
    //The positive aspect is that the method allows the called page to evoke 
    //the onload method which we relied on to build a complex page.
    //
    //This method of talking to the server assumes that the user needs to 
    //interact with the page. This is in contrast to the ajax method which
    //talks to the server without any user intervention and has no data 
    //limitation because we talk to the server via the post command - rather
    //than get.
    //
    //This version uses a query string to pass data to the server
    this.open_window = function($partial_filename, requirement, onfinish=null, specs=""){
        //
        //Add the PHP extension to the classname to formulate the file name to 
        //request from the server
        
        //
        //The filename to execute is the entry point for all mutall ajax calls.
        //Mind theh version of the buis -- set from PHP side
        var filename = this.version + "/"+$partial_filename;
        //
        //The system window.open command uses the get method.
        //Convert the input data to a json string; how do you tell if the 
        //conversion was successful?
        var qstring = this.compile_std_querystring(requirement);
        //
        //Compile the required url; mutall_id is the name of index in the 
        //PHP global $_GET variable that is used for accessing this data
        var href = filename + "?"+ qstring;
        //
        //Now use the window open method to finish the job. Use the _blank 
        //window name to force a new window, so that win and window are 
        //different
        var win = window.open(href, "_blank", specs);
        //
        //Set a timer so that we can check periodically when the new window 
        //is closed by the user
        //
        //Freeze "this" object in order to reference it within the anonymous 
        //function below
        var this_ = this;
        //
        //Wait for the window to be closed, checking after 100 milliseconds
        var timer = setInterval(function(){
            //
            //If the opened window is closed, clear the timer first, then 
            //check to see if the closing was "proper" or not
            if (win.closed)
            {
                //
                //Clear the timer
                clearInterval(timer);
                //
                //Check if the window was propely closed, i.e., if it was closed
                //via the mutall close window menu or via the general window close
                //option. We assume that when "win" was properly closed, the 
                //property "this_.mutall_id" of window "win" is set to some
                //mutall object of class $classname that was used to drive the
                //opened window
                var classname = win[this_.mutall_id];
                //
                //Use the page with the onfinish call back function if it is 
                //valid to do so.
                if (onfinish!==null && typeof classname !=="undefined"){
                    //
                    //Execute the onfinish function. This function ensures 
                    //that this page is updated, depending on who opened
                    //the window 
                    onfinish.call(this_, classname);
                }
            }
        }, 100);
        //
        //Return the opened window -- just in case the caller wants to interogate 
        //it for furtjer details.
        return win;
    };
    
    //
    //Set this mutall object with the login credentials of the given page. An
    //error will be thrown if such credentuals are missing
    this.set_login = function(page){
        this.username = page.username;
        this.password = page.password;
        this.dbname = page.dbname;
    };
  
    //Show the error message on this mutall page
    this.show_error_msg = function(msg){
      //
      //Get the error element on the page
      var error = document.getElementById("error");
      //
      //It is an error if you dont have the error element defined on this page
      //Show both the error and the need for an error element on this page
      if (error===null){
          //
          alert(msg + "\nNo element found with id='error' on page " + this.classname); 
      }
      else{
        error.innerHTML=msg + "<br/>(Click to clear this message)";    
      }
    };
    
    //
    //Clear the error message
    this.clear_error_msg = function(){
      //
      //Get the error element on the page
      var error = document.getElementById("error");
      //
      //It is an error if you dont have the error element defined on this page
      //Show both the error and the need for an error element on this page
      if (error===null){
          //
          alert("Element 'error' is not defined on page " + this.classname); 
      }
      else{
        error.innerHTML="";    
      }
    };
    
    
    
    //Classify the input as either mutall, object, array or other
    this.classify = function(input){
        //
        //Let t be the type of input
        var t = typeof input;
        //
        //Test whether t is an object
        if (t!=="object"){
            //t is not an object. It must be other
            return "other";
        } 
        //
        //If the input is null return other
        if (input===null){
            return "other";
        }
        //
        //This is an object; Test if it is an array or not
        if( Object.prototype.toString.call(t) === '[object Array]' ) {
            //
            //It san array
            return "array";
        }
        //
        //This is an ordinary object. Test it if it is a mutall objct
        //Get the classname
        if (typeof input.classname==="undefined"){
            //
            //This is an ordinary object
            return "object"; 
        }
        //
        //This is a mutall object.
        return "mutall";
    };

    //Returns the standard query string compiled from the the given query 
    //string object which is presented as a set of name/value pairs
    this.compile_std_querystring = function(obj){
      //
      //Collect the url parts, starting with noting
      var parts=[];
      //
      //For each property of the object concatentate it with its value
      for(var prop  in obj){
          //
          //Encode the value and its property for safety sake
          var part = encodeURIComponent(prop) + '=' +  encodeURIComponent(obj[prop]);
          //
          //Save the part
          parts.push(part);  
      }
      //
      //Return the joined parts -- upersand concatenated
      return parts.join('&');
    };
    
    //gravitate moves the row that contains the given checkbox input to the
    //top of ths list just after the header
    this.gravitate = function(input)
    {
        //If we have just unchecked an row, return immediately
        if (input.checked===false) {return;}
        //
        //Retrieve the tr in which the input is found. Its 2 parents up 
        //because of the interevening td
        var tr = input.parentNode.parentNode;
        //
        //Get the parent of tr; its the tbody I suppose
        var tbody = tr.parentElement;
        //
        //Remove this row's tr from tbody
        tbody.removeChild(tr);
        //
        //Add this rows tr to the top of tbody just after the heading
        tbody.insertBefore(tr, tbody.childNodes[1]);
    };
    
    //
    //Retrieve the row id from the local storage
    this.get_id = function(index){
        //
        //If the index is not set, return a false
        if (typeof index==="undefined") {return false;}
        //
        //Check if the id node is available in the windows local storage
        if (typeof window.localStorage.id === "undefined"){return false;}
        //
        //Convert the storage into an object
        var obj = JSON.parse(window.localStorage.id);
        //
        //Test if there is any entry by under the index of interest
        if (typeof obj[index]==="undefined") {return false;}
        //
        //Return the id (of the index)
        return obj[index];
    };
    
    
       
   
    //
    //Set this mutall object with the login credentials of the given page. An
    //error will be thrown if such credentuals are missing
    this.set_login = function(page){
        this.username = page.username;
        this.password = page.password;
        this.dbname = page.dbname;
    };
  
    //Show the error message on this mutall page
    this.show_error_msg = function(msg){
      //
      //Get the error element on the page
      var error = document.getElementById("error");
      //
      //It is an error if you dont have the error element defined on this page
      //Show both the error and the need for an error element on this page
      if (error===null){
          //
          alert(msg + "\nNo element found with id='error' on page " + this.classname); 
      }
      else{
        error.innerHTML=msg + "<br/>(Click to clear this message)";    
      }
    };
    //
    //This empty function is build to match the php empty function for javascript
    //we decided to develop one to solve empty cases in javascript
    this.empty = function(value){
        if (typeof value === "undefined") return true;
        if (value==="" )  return true;
        if(value=== null) return true;
        
        return false;
    };
    
    //
    //Clear the error message
    this.clear_error_msg = function(){
      //
      //Get the error element on the page
      var error = document.getElementById("error");
      //
      //It is an error if you dont have the error element defined on this page
      //Show both the error and the need for an error element on this page
      if (error===null){
          //
          alert("Element 'error' is not defined on page " + this.classname); 
      }
      else{
        error.innerHTML="";    
      }
    };
   
   
    
 }
 
 //The label layout of data supports interaction with data that is laid out in
//the label format.
function label(input=null){
    //
    //Set the classname manually. (Infuture this shouls be automated)
    this.classname="label";
    //
    //The label inherits from the layout object.
    layout.call(this, input);
}

//The tabular layout
function tabular(input=null){
    //
    //Set the classname manually. (Infuture this shouls be automated)
    this.classname="tabular";
    //
    //The tabular layout inherits from the layout object.
    layout.call(this, input);
}

//
//The input mode for displaying the data. (This is a dummy class)
function mode_input(input=null){
    this.classname = "mode_input";
}

//The output mode of displaying data. This is a dummy class)
function mode_output(input=null){
    this.classname = "mode_output";
}


//layout is inherted by all pages -- both labels and tables. The layout is 
//designed to be initialized from a json string derived in a php environment, so
//all the necessary initialization data is expected to be held in the static 
//input_.
function layout(input_=null){
    //
    //A layout must be associated with field and record tag names
    this.field_tag_name;
    this.record_tag_name;
    this.body_tag_name;
    //
    //Initialize the parent mutall object; the properties in input will be 
    //added to this object.
    mutall.call(this, input_);
    
}


//
////The records page is used for representing (and interacting with) multiple
//records of (sql) data. It is placed here as separate file, rather than being 
//part of page_records.php as is the normal case, because this page is 
//referenced in another page -- page_descendant. A descendant inherits from this 
//records page
function page_records(input_){
    //
    //The id of the record to select on load 
    this.id;
    //
    //The following records layout properties are set during user interaction
    //
    //Current criteria is used for populating the articles section
    this.criteria;
    //
    //The ordering of the selected items
    this.order_by;
    //
    //Primary key and focus field name of the current selected row
    this.primarykey;
    this.focus_name;
    //
    //Set the db and table names from the input
    this.dbname=input_.dbname;
    this.tname = input_.tname;
    //
    //Initialize the parent page table
    page_table.call(this, input_);
  
    //Returns a query string object that is fit for supporting CRUD operations 
    //on this page. If dom_record is missing, then the values property
    //is an empty object
    this.get_querystring = function(dom_record=null){//page_records
        //
        //Initialize the values from the dom record
        var values = dom_record===null ? {} : dom_record.values;
        //
        //Save the record's data to the server using the ajax method
        var qstring = {
            dbname:this.dbname,
            tname:this.tname,
            //
            //Remember to json encode the name/value pairs
            values:JSON.stringify(values),
            //
            //Why do we need to display the record body without the header?
            body_only:true,
            //
            //The extra parameters
            layout_type:this.layout_type
        };
        //
        return qstring;
    };    
   
    //Set driver type; this is particularly important for user defined sql
    //statements
    this.set_driver = function(qstring){
        //
        //The driver type must be defined
        if (typeof this.arr.driver_type==="undefined")return;
        //
        //The driver type must be an sql
        if (this.arr.driver_type!=="sql")return;
        //
        //The sql must be defined
        if (typeof this.arr.sql==="undefined"){
            alert("Sql not found");
            return;
        }
        //
        //Set the sql driver type
        qstring.driver_type="sql";
        qstring.sql=this.arr.sql;
    };
        
    //Extend the querystring with the arguments of a this page's constructor;
    //that depends on the caller. For instance, page_record extends the query 
    //string using the primary key value.
    //  
    //Consider re-doing this so that every object knows how to extend the query
    //string. If this propagates through the object hierarchy, we can greatly
    //simplify the construction of a querystring
    this.extend_querystring = function(qstring){
        //
        //A page records need no extension
    };
   
    //Returns the current dom record of this page based on the "current" 
    //attribute. If there is no current this function alerts the user, then 
    //fails.
    this.get_current_dom_record = function (){
        //
        //Try to get a current dom record
        var dom_record = this.try_current_dom_record();
        //
        if(dom_record){
           return dom_record; 
        } 
        else{
            alert("No dom record is selected");
            return false;
        }
    };
    
    //Returns the current dom record of this page based on the "current" 
    //attribute. If there is no current this function fails quietly. 
    this.try_current_dom_record = function (){
        //
        //Formulate the current record css selector. Note that the curent 
        //selector is designed to be independent of the records layout
        var rselector = "[current='record']";
        //
        //Retrieve the current dom record element by searching in the entire 
        //document to represent the viewable part of a record
        var view = window.document.querySelector(rselector);
        //
        //Test if the search returned a valid element
        if (view===null)
        {
            //Return false (quietly)
            return false;
        }
        //
        //Create a dom record that links the visible part of of a record, view, 
        //and the PHP representation of the same based on this page.
        //
        //Note the adopted PHP variable naming style to avoid confucion between
        //the window-level variables, $dom_record the variable and dom_record the
        //class function
        var $dom_record = new dom_record(view, this);
        //
        //Return the dom record
        return $dom_record;
    };
   
        
    //Add a new record to this page. The strategy is to instruct the server to 
    //construct a record then append it to the 
    //beginning of the current table, just after the header, i.e., as the first
    //child of node tbody. This is designed to work for both types of record 
    //layout, viz., tabular and label. If the pre-filling of a table depends on
    //the page class from which this function was called. The default behaviour
    //is none
    this.add_record=function (){
        //
        //Get teh query string fit for supporting CRUD opertaions on records 
        //based on this page. The dom_record component is missng for ned records
        var qstring = this.get_querystring();
        //
        //The expected output from adding a record is the html to be appended 
        //to this page's records just after the table's  heading. Note that we
        //have overriden the default class to be passed to the server with the
        //appropriate one, this.class, ratjer than hanrd wire "page_records". 
        //That ensures that the correct class will be requested to execute this
        //function by teh server in PHP.
        this.ajax("add_record", qstring, "html", function(result){
            //
            //Get the tbody element as we will need to access her children. 
            //There is no assumption that the current layout is a table, only 
            //that the tbody element is available, even when there are 
            //no records. Note how we confine the search to the correct dom page;
            //this is important because a descendant page (which inherits this 
            //one) is not associated with the entire document but a section of it
            var tbody = this.get_dom_page().querySelector(this.layout.body_tag_name);
            //
            //Retrieve the header record; its the first child of tbody
            var header = tbody.firstElementChild;
            //
            //Let dview be a a dummy dom record. (CreateElement is a property of
            //document note -- not just any element)
            var dview = window.document.createElement(this.layout.record_tag_name);
            //
            //Insert the dummy tr after the header
            header.after(dview);
            //
            //Replace the dummy view record with our correct version after correct 
            //placement. NB: This does not affect the structure of view, contrary
            //to expectation. Explain this a bit more!! ....1
            dview.outerHTML = result;
            //
            //Return the just inserted dummy view. IT IS DIFFERENT INSTANCE FROM 
            //dview. It is the record view we need; lets just call it view
            var view = header.nextElementSibling;
            //
            //Ensure that the dom record, tr,  is the current one on this page
            this.select_dom_record(view);
            //
            //Transfer focus to the first input of type text of the inserted
            //tr
            var txt = view.querySelector("input[type='text']");
            if (txt!==null){
                txt.focus();
            }
        });
        
    };
    
     //Put the current dom record into edit mode
    this.edit_record = function(){
        //
        //Get the current dom record, alerting the user if none is selected
        var dom_record = this.get_current_dom_record();
        //
        //Switch the dom record to edit mode
        dom_record.switch_record_to_edit(true);
        
    };
    
     //Cancel the record/edit operation by setting the input fields of the 
    //current table row (not to edit mode) and resetting their values to what
    //was they were in the output text 
    this.cancel_edit = function(){
        //
        //Get the current dom record on this page
        var dom_record = this.get_current_dom_record();
        //
        //Switch off the edit mode
        dom_record.switch_record_to_edit(false);
    };
    
    //Use the given criteria to influence the search results
    this.search_criteria = function(){
        //
        //Get this page's query string; it is already initilaized with the 
        //search criteria;
        var qstring = this.arr;
        //
        //Set the following extra properties for supporting search report:-
        //
        //Start display from the first record
        qstring.offset=0;
        //
        //Show the headers in the initial search
        qstring.body_only=false;
        //
        //Show as many records as are needed for the scroll bars to appear; 
        //otherwise they wont. Too high a number is also bad, because that 
        //would reduce the responsiveness when the search results are 
        //initially reported.
        qstring.limit= this.full_limit;
        //
        //Populate the dom node that corresponds to this page with html 
        //resulting from executing the search on the server (using the ajax method)
        this.refresh();
    };
       
    //Refresh this page by re-displaying it at this page's $node using the given
    //querystring. If the method is not specified, we assume its the display page.
    //Can it be anything else? Yes, as in the case of scroll!!
    //The query string is specified to reflect the desired page
    //view; if not,then it is built from the this page properties. This
    //typically happens when the user requests for a refresh
    this.refresh = function(method="display_page"){
        //
        //Get this page's querystrinhg
        var qstring =this.arr;
        //
        //Set driver type; this is particularly inportant for user defined sql
        //statements. Address this issue from its roots
        //this.set_driver(qstring);
        //
        //Use the query string to request for the html of the specified
        //page display and use it to rebuild the node for this page
        this.ajax(method, qstring, "html", function(html){//refresh, search_criteria
            //
            //Get the dom page $node to write the results to.
            var page = this.get_dom_page();
            //
            //Write the html data to the page. Use the innerHTML, so you must 
            //make sure that the returned page does not have the node, e.g.,
            //article for this page. This ensures that attributes we have 
            //specified for article in the page's html will not be overwritten.
            page.innerHTML = html;
        });
        
    };         
    
    //View the current record's data plus that of all her dependants. The
    //selected record may be in page_records or page_descendant. page_descendant
    //inherits from page_records. That is the justfication for the view record
    //to be defined at the page_records level.
    //A record is an exampele of a complex page; it has more than one logical
    //page in one physical page. Put in another way, it is a single (page) file 
    //that displays data from more than one mutall page. The pages are styled
    //independetly
    this.view_record = function(){//page_records
        //
        //Get the current dom record. If none is selected, no alert will be 
        //given and the function will return a false quietly.
        var dr = this.try_current_dom_record();
        //
        //Check the returned dom field; there has to be one!
        if (!dr) {
           alert("No valid record is found. Please select one");
           //
           //Discontinue processing
           return false;
        }
        //
        //Prepare to open a new page_record starting normally with the parent
        //page and proceding to open the descendants via multiple requests to
        //the server via jax -- another justification for the complex page label.
        //These requests are starterd by the page.onload() function
        //which can only be called by opening the the page window.
        //
        //Collect the minimum data required to open a recod
        var qstring = {
            //
            //Set the dbase (login credentials)
            dbname: this.dbname,
            //
            //Set the parent table name -- the one that drives the page
            tname: this.tname,
            //
            //Set the primary key field from the dom record; this helps to formulate
            //the sql condition for retrieving the field values.
            primarykey: dr.view.getAttribute('primarykey')
            
        };
        //
        //Debug
        //console.log(qstring);
        //
        //Now open the record as a full window (hence no specs). This is a 
        //complex page, so start small and be ready to build other page 
        //components via ajax. Do nothing on terminating interactions with the 
        //page_record; in future we would update current record of caller 
        //page_records (just in case there were changes that need to take effect).
        this.open_window("page_record.php", qstring);//view_record
    }; 
    
    //Define the function for infinite vertical scrolling on the given element 
    //in page_records
    this.vscroll = function(element){
        //
        //Set the condition for being at the botton of the page. 
        //
        //Let $t be the total, i.e, entire, scrollable height of the given 
        //element
        var $t = element.scrollHeight;
        //
        //Let $c be the current top, i.e, y, position of the scroll button
        //relative to $t
        var $c = element.scrollTop;
        //
        //Let $h be the height of the actual visible window part that represents
        //the element.
        var $h = element.clientHeight;
        //
        //When at the botton, the total scroll height of the element is the same as 
        //its current scroll position plus its ownn height
        var at_bottom = $t === ($c + $h);
        //
        //Do not scroll when there is no more data; this is not necessary as the 
        //scroll event will not be fired if there is no more data to scroll
        //var more_data = true;
        //
        //Check if we need to fetch more records for the page; we do when we 
        //are are at the bottom of the scrollable range
        if (!at_bottom){ return;} 
        //
        //We do need to verticall scroll
        //
        //Take the last queystring array
        qstring = this.arr;
        //
        //The page sise should be the scrolling limit
        qstring.limit = this.scroll_limit; 
        //
        //Set the last offset to 0 if it is not known
        var last_offset = typeof qstring.offset==="undefined" ? 0: qstring.offset;
        //
        //Update the scroll offset; it is the last value plus the scroll_limit.
        qstring.offset = last_offset + qstring.limit; 
        //
        //The returned html should not be headed
        qstring.body_only=true;
        //
        //Fetch the next page and append the resulting headerless records to be 
        //children of the tbody node
        this.ajax("display_page", qstring, "html", function(html){//vscroll
            //
            //Update the page only if html is not empty
            if (html!=="" || html!==null){
                //
                //Get the dom page to write to:
                var page = this.get_dom_page();
                //
                //Retrieve the table body
                var tbody = page.querySelector("tbody");
                //
                //Create a dummy dom element assuming that the layout is 
                //tabular. You can assume anything for the dummy. The outerHTML
                //command that follows will replace it with the correct tag
                var dummy = window.document.createElement("tr");
                //
                //Attach it to the page
                tbody.appendChild(dummy);
                //
                //Change the outer html of the dumy to the incoming data
                dummy.outerHTML = html;
            }
        });
    };
           
    //Delete the selected record from the database, then and refresh the page
    this.delete_record=function(){
        //
        //Get get the current dom record of this page
        var dom_record = this.get_current_dom_record();
        //
        //Skip this process if the row is not valid
        if (!dom_record) {return;}
        //
        //Confirm the delete and continue if necessary.
        var yes = window.confirm("Do you really want to delete this row?");
        if (!yes) return;
        //
        var qstring = {
            //
            dbname:this.dbname,
            tname:this.tname,
            //
            //Ensure the primary key is posted. Example of querying teh view 
            //part of a dom record
            primarykey: this.primarykey = dom_record.view.getAttribute("primarykey")
        
        };
        //
        //Execute the delete record method on the page_record object. 
        this.ajax("delete_record", qstring, "json", function(result){
            //
            if (result.status==="ok"){
                //
                //Rather than refreshing an entire page, simply remove/detacch 
                //the view of the dom_record from its parent. do some 
                //investigation around dom_record.view.getParent().remove(dom_record.view);
                dom_record.delete();
            }
            //
            //Otherwise show the error message
            else{
                this.show_error_msg(result);
            }
        });
    };
}



//A descendant is a special page of the page_record. It inherits 
//properties from the page_records; thst is why it shares the same js file as
//the page_records. 
//A descendant page has no phsical file equivalent
function page_descendant(input_){
    //
    //
    //Call the parent page_records (which is also defined in in this same file)
    page_records.call(this, input_);
    //
    //Let x be the querystring of the parent; I will ovrride it in the the
    //implementation of this version's querystring
    this.x = this.get_querystring;
    
    //Returns a query string for supporting CRUD operations on ercros of this 
    //page. This extends the page records version by adding a parent table name an dforeign key properties to the
    //parents querysring
    this.get_querystring = function(dom_record=null){//page_descendant
        //
        //Get the querystring of the parent page of records; x is the parent
        //query string before overring it.
        var qstring = this.x(dom_record);
        //
        //Add the parent table ame
        qstring.parent_tname = this.parent_tname;
        qstring.parent_primarykey = this.parent_primarykey;
        //
        //Return the richer query string
        return qstring;
    };    
    
}

//Representation of a login page class in JS. The justification of this page as 
//a standaone file is the fact that it is referenced in more than one place, e.g.,
//in page_login as well as in page_buis
function page_login(input_) {
    //        
    //Login data is laid out in a label format. Initialize the page system.
    page.call(this, input_);
    
    //Save the login data by copying it from the dom record to the js 
    //record (structure) and saving it in the windows object ready for the 
    //caller to pick it up from there
    this.ok = function(){
        //
        //Get the record tagname of this page's layout
        var rec_tagname= this.layout.record_tag_name;
        //
        //Get the dom record view using the correct tag name. (For tabular layout
        //the tag name is "tr"; for labels, it is "field")
        var dom_record_view = window.document.querySelector(rec_tagname);
        //
        //Create a dom record from this view and page; this process also 
        //transfers the values from the view to the record's values
        var $dom_record = new dom_record(dom_record_view, this);
        //
        //Compile the querystring from the dom_record values; it comprises of 
        //the user name and password
        var qstring = {
            username: $dom_record.values.username,
            password: $dom_record.values.password
        };
        //
        //Request the server to check the login credentials against registered
        //clients. If registerd, return the clientid; if report user not found
        this.ajax("check_login", qstring, "json", function(result){
            //
            //Pass on the populated record to the caller js function if login 
            //credentials were succesfully saved to the server
            if (result.status==="ok"){
                //
                //The login proceeded without any errors. Now investigate the 
                //result by looking at the extra data
                var user = result.extra;
                //
                if (user.found){
                    //
                    //Compile the data to retirn to the window
                    //
                    var data = {
                        username:qstring.username,
                        clientid:user.clientid
                    }
                    //Close this window properly; this means saving the compiled
                    //querystring data to the windows object first, then closing it. 
                    //That way, caller will have access to the data in the query 
                    //string. When the window is improperly closed, the querystring
                    //data is not saved, so that the caller cannot access it.
                    this.close_window(data);
                }
                //
                else{
                    this.show_error_msg("User is not found");
                }
            }
            //...otherwise show the error message. The page remains open
            else{
                this.show_error_msg(result);
            }
        });
      };
      
    //Logout simply destroys the session variables
    this.logout = function(){
        //
        //Request for logout function; no data needs to besent to the server to 
        //logout
        this.ajax("logout", {}, "json", function(result){
            //
              if (result.extra==="ok"){
                //
                //Close the window. This is the event that signals to the caller 
                //that we are done with login
                window.close();
            }
            //...otherwise show the error message
            else{
                this.show_error_msg(result);
            }
        });
    };
    
    //The login page needs no initialization
    this.onload = function(){};
    
    //Log into or out of the mutall database system and show the status on the 
    //appropriate buttons of this page. This allows access to all databases by
    //mutall_data staff
    this.log = function(is_login){
        //
        //Get the log in/out buttons
        var buttons = this.get_log_buttons();
        //
        //Do either login or logout
        if (is_login){
            this.login(buttons);
        }
        //
        else{
            this.logout(buttons);
        }

    };
    
    //Log into the mutall system to to prevent access to mutall databases
    //to unauthorised uers
    this.login = function(buttons){
        //
        //Define the dimension specs of the login window in pixels
        var specs = "top=100, left=100, height=400, width=600";
        //
        //Open the login page with no requirements. If the login was /successful,
        //we expect the data to be written to a session variable and an object 
        //with the login credentials is returned to allow us update the login 
        //status
        this.open_window("page_login.php", {}, function(login){
            //
            //
            //Show the login status
            this.set_log_buttons(true, buttons, login.username);
            //
            //Update this page's username and registration id, i.e, the primary
            //key value of the mutall_data clients's entry        .
            this.username = login.username;
            //
            //The userid is used for supporting transactions, e.g., the case of
            //showing interest on real estate
            this.clientid = login.clientid;
            
        },specs);
    };
   
   //Get the log in/out buttons on the current page
    this.get_log_buttons = function(){
        //
        //Get the login button; 
        var login = window.document.getElementById("login");
        //
        //It must be found!.
        if (login===null){
            alert("Log in button not found on page "+ this.name);
        }
        //
        //Get the logout button
        var logout = window.document.getElementById("logout");
        //
        //It must be found!.
        if (logout===null){
            alert("Log out button not found on page "+ this.name);
        }
        //
        //Define and set the buttons structure
        var buttons = {
            login: login,
            logout:logout
        };
        //
        //Return the buttons
        return buttons;
    };
    
    //Register a new user
    this.register = function(){
        //
        //Open the client registration window
        //
        //The file to open is in teh services folder, shich is a simbling
        //of the current one:-
        var filename = "../services/registration.php";
        //
        //The server needs the following info:-
        querystring = {
            //
            //The database to regfer to
            dbname:"mutallco_data",
            //
            //The table to open
            tname:"client",
            //
            //A criteria that retuens no data when teh page is viewed
            criteria:false
        };
        //
        //Now open the requested window and use the user data to log in
        //the the new user
        this.open_window(filename, querystring, function(user){
            //
            //Sow the username of teh logged in user
            alert(user.username);
        });
        
    };
    
    //Set the log in/out buttons, depending on the login status
    this.set_log_buttons = function(is_login, buttons, username){
      //
      ///Show the log in status
      if (is_login){
        //
        //Hide the login button
        buttons.login.setAttribute("hidden", true);
        //
        //Show the logout button with username
        buttons.logout.removeAttribute("hidden");
        //
        //Attach the user name to the logout butom
        buttons.logout.value = "Logout " + username;  
      }
      //
      //Show the log out status
      else{
         //Show the login button
        buttons.login.removeAttribute("hidden");
        //
        //Hide the logout button
        buttons.logout.setAttribute("hidden", true); 
      }
    };
    
    
    //Log out of a mutall system; this :-h
    //-destroys the session variables 
    //-resets the username and clientid properties of this page
    //-updates the login status
    this.logout = function(buttons){
        //
        //Request for logout function from the server; the querystring is empty
        this.ajax("logout", {}, "json", function(result){
            //
            //Save the record if login credentials are ok...
            if (result.status==="ok"){
                //
                //Clear the username and clietid properties
                this.username=null;
                this.clientid=null;
                //
                //Set the status
                this.set_log_buttons(false, buttons);
            }
            //
            //...otherwise show the error message
            else{
                this.show_error_msg(result);
            }
        });
    };
    
    
}

//
//Page buis is a home page it extens the login page 
function page_buis(page_buis_){
    //
    //The mutall pag inherites the login page in order to access
    //the login functionality
    page_login.call(this, page_buis_);
    //
    this.onload = function(){
        //
        //Get the login buttons
        var buttons = this.get_log_buttons();
        //
        //Get the login status from teh server
        var status = this.log_status;
        //
        //Set the login credentials, if they are known
        if (status.is_login){
            this.username = status.username;
            this.password = status.password;
        }
        //
        //Set the log in/out buttons
        this.set_log_buttons(status.is_login, buttons, status.username);
        //
        //Do the synchronization
        //
        //Get the type of synchronization, partial or deep from th
        //querystring array
        var psync = typeof this.psync!=="undefined" && this.psync ? true: false;
        //
        //console.log(this);
        //console.log(psync);
        this.synchronize(psync);
    };
    
     //View the available databases; you must login to access the databases
    this.view_databases = function(){
        //
        //Check whether the user needs to login or not. 
        if (!this.user_has_logged_in()){
            //
            //He needs to log in
            //
            //Remember that this page inherits the login page.
            this.log(true);
        }
        // 
        //Wait for the user to complete the log in process
        this.wait(
            //
            //waiting for you to...
            "complete the log in to view the available databases",
            //
            //Define a test that succeeds when the user has logged in
            "user_has_logged_in",
            //
            //Open the databases window after the login
            function(){
                //
                //Open the list of databases window unconditionally, i.e., with
                //an empty query string. On finish update the visited databases 
                //in the windows local storage. Why? What for?
                this.open_window("page_databases.php", {}, function(page_databases){
                    //
                    //Save the dbname index
                    //
                    //Get the index name
                    var name = page_databases.index;
                    //
                    //Get the index value
                    var value = page_databases[name];
                    //
                    //Transfer the index to this page
                    this[name]=value;
                });
            }
        );
    };
    
    //Succeeds if the user has logged in
    this.user_has_logged_in = function(){
        //
        //A user has logged in if the username property is set
        if (typeof this.username==="undefined"){
            return  false;
        }
        //
        //Cannot get the or symbol on my laptop!!!!
        if (this.username===null){
            return  false;
        }
        //
        //Clear the wait message
        this.clear_error_msg();
        //
        return true;
    };

    //Synchronize databases registed in mutall_data with information schema on
    //the server; aditionally, serialize sql_edit for all the tables
    //
    //Serialization helps to keep server specific data to the server,
    //thus reducing the data traffic between it and the client.
    //This has 2 advantatages:-
    //1-It improves reponsiveness and hence the user experience
    //2-It simplifies the way the user requests for services, only
    //having to sepecify the minimimal data. This is useful when 
    //the users need to incorporate pages, derived via Buis, to 
    //their websites
    //3-Following on from 2, it helps to bookmark pages for 
    //revisiting later.
    //Partial synchronization is carried out whenever the buis page is opened
    //froma browesr
    this.synchronize = function(psync){
        //
        //Confirm the synchronization and continue if necessary -- if the 
        //synchronization is not partial.
        if (!psync){
            var yes = window.confirm(
               "Do you want to DELETE synchronization tables and re-synchronize mutall_data with the information_schema?"
            );
            if (!yes) return;
        }
        //
        //Use the ajax method to evoke x.synchronization() where x is an object
        //of class dbase_mutall_data. The method returns a (json derived) object 
        //with the following properties:-
        //a) status: to tell us if the process was successfull and if 
        //not what went wrong
        //(b) html: is a description of the error if the status was not ok
        //
        //Set the partial synchronization to the incoming value. Consider using 
        //formdata.append method
        var qstring = {
            psync:psync
        };
        //
        //Show synchronization in progress
        this.show_error_msg("Synchronizing...");
        //
        //This call assumes, thus passing all her data to the server
        this.ajax("synchronize", qstring, "json", function(result){
            //
            switch (result.status){
                case "ok":
                    //
                    //Reooiro ok
                    this.show_error_msg("Synchronization successful");
                    break;
                case "error":
                    //
                    //Show the error from html property
                    this.show_error_msg(result.html);
                    break
                default:
                   this.show_error_msg("Unknown ajax result status '"+result.status+"'"); 
            }
        }, "page_buis");
    };
    
    
}      

//Define the js version of a database page
function page_databases(page_databases_) {
    //
    page.call(this, page_databases_);
    //
    //View the tables of the current (selected) database; if none is selected 
    //then wait for the user to do so; then view the related tables.
    this.view_tables = function (){
        //
        //Wait for the user to select a database and only proceed on success
        this.wait(
            //
            //Show this error message to prompt the user to select a database
            "select a database", 
            //
            //This is the function to test if a bname has been selected on this 
            //page
            "dbname_is_selected",
            //
            //Open the tables window based the on current on database 
            //selection. If the table page is closed properly successful, 
            //we need to save save the tabl'e index to this page and to
            //the windows local storage
            function(){
                //
                //On;y the dbname component is required for opening a list
                //of tables
                var requirements = {
                    dbname: this.dbname
                };
                //
                //Open the tables page with dbase as the only parameter, then  
                //update the property 'tname' on finish
                this.open_window("page_database.php", requirements, function(page_database){
                    //
                    //Save the dbname index
                    //
                    //Get the index name
                    var name = page_database.index;
                    //
                    //Get the index value
                    var value = page_database[name];
                    //
                    //Do the saving to teh local windows storage
                    this[name]=value;
                });
            }
        );
    };

    
    //Returns true if a dbname is selected; otherwise its false.
    this.dbname_is_selected = function(){
        //
        //Get this pages index
        var index = this.index;
        //
        //Use the index name to define condition for a selected record 
        var selected = this[index]!=="undefined" && this[index]!==null;
        //
        return  selected ? true: false;
    };
}

//Define the constructor for page of tables. Note that this function is 
//misaligned deliberately to so that the with of its text is less than the
//left edge marker
function page_database(page_database_){
    //
    //Initialize the page with no specific onload id for the tname
    //index
    page.call(this, page_database_);
    //
    //Set the table name; this function is called by page.view_records
    //to signal the end of a wait to select a table whose records we want
    //to view.
    this.set_table_name = function(){
        //
        //Try to get the current dom record; this fails quietly if 
        //none is selected
        var dr = this.try_current_dom_record();
        //
        //If a record selection is found, then set this page's table name 
        //and return true
        if (dr){
            //
            //Set this tname by transferring it from dom to this page
            this.tname = dr.view.getAttribute('id');
            //
            //Return the true status
            return true;
        }
        //
        //Otherwise return false, i.e., when no dom recrod is selected
        return false;
    };

}

 //Representation of a selector page in js; note the delibarate misalignment
function page_selector(page_selector_) {
    //        
    //Call the inherited page of records
    page_records.call(this, page_selector_);

    //Extend the querystring with the arguments of a page selector constructor
    this.extend_querystring = function(qstring){
        //
        //The properties that extends a page of records to a slector page
        qstring.id = this.id;
        qstring.primarykey = this.primary;
        qstring.output = this.output;
    };
    
        
    //Use the given hint to search the primary key field (in the output subfield)
    //for the hinted records of this page's driver table. 
    this.search_hint = function(hint){
        //
        //Formulate the query string requirements for evoking the display page 
        //using the search view
        var qstring = this.arr;
        //
        //The arguments of this method in the search view.
        //
        //Set the hint value to search for.
        qstring.hint = hint;
        //
        //Start display from the fisrt record
        qstring.offset=0;
        //
        //Show the headers in the initial search
        qstring.body_only=false;
        //
        //Show as many records a are needed for the scroll bars to appear; 
        //otherwise they wont. Too high a number is also bad, because ot 
        //would reduce the responsiveness when the search results are 
        //initially reported.
        qstring.limit=this.full_limit;
        //
        //Add to the query string the fields that extends page_records to 
        this.extend_querystring(qstring);
        //
        //Populate the dom node that corresponds to this page with html 
        //resulting from executing the search on the server (using the ajax 
        //method)
        this.refresh("search_hint");
    };
    
   //Return the values, i.e., the id, primary and output subfields of the 
    //current dom record to the caller. This effectively saves a copy of
    //the values to the current windows object and closes it. The caller will 
    //access the values from the window's object. 
    //NB. Closing the window object does not destroy it -- that's why we can use
    //it to share pages on same client.
    this.return_field = function (){//edit_fkfield
        //
        //Get the currently selected dom record
        var dom_record = this.get_current_dom_record();
        //
        //
        //Retrieve the primary key field from this dom record
        //
        //Get the name of the primary key field of the dom record; it is the 
        //same as that of the dom record's table name
        var pkfname = dom_record.tname;
        //
        //Now get the primary key field
        var pkfield = dom_record.fields[pkfname];
        //
        //Let values be an empty object for collecting the dom field's vlues
        var values = {};
        //
        //Copy the values from the (true) dom_field to the values collector
        pkfield.copy(true, this, dom_record.view, values);
        //
        //Close the window, returning the field with the attached values
        this.close_window(values);
    };
}

//The javascript "class" that models the functionality of a
//page of a single record. The input_ is non-object data passed
//on from the php environment and used to compile the page
function page_record(input_) {
    //
    //Set the db and table names from the input
    this.dbname=input_.dbname;
    this.tname = input_.tname;
    //
    //Call the parent page_table class
    page_table.call(this, input_);
    //
    //Let x be the page activation class, before it is modified. Why do we
    //need to override it? This does not look correct?? See the reason for
    //overriding below.
    var x = this.activate_class;

    //Override the activate class defined in the grand parent 
    //mutall so that this record_page can activate the descendants
    //This is important because, to be useful in the js environment, 
    //and noting that a descendant is constructed in the php environmet, 
    //we need to activate it. We aim to achieve this without 
    //modifying the base page class by overriding the activate 
    //class method
    this.activate_class=function(classname, input){//page_record
        //
        //The targeted class name to be treated specially is 
        //descendant. Put this activation code in its proper class!!!
        if (classname==="page_descendant"){
            return new page_descendant(input);
        }
        //
        //Call original version of the activate class
        var aclass = x.call(this, classname, input);
        //
        //Return the active result
        return aclass;
    };
    //
    //Activate the descendants
    this.descendants = this.activate(input_.descendants);
    
    //
    //Edit the current field of the current record of the current
    //descendant. Note the repetition of the word "current"
    this.edit_field = function(df=null){//page_record
        //
        //Get the current dom field
        if (df===null){
            df = this.get_current_dom_field();
        }
        //
        //Check if the dom field has the dom descendant ancestor
        var dom_descendant = df.closest("descendant");
        //
        //This is not a descendant; use the parent's edit field 
        //method
        if (dom_descendant===null){
            //
            //Invoke the receord_page parent's edit function 
            var p = new page();
            //
            //Call the normal page edit field using the context
            //of the record page
            p.edit_field.call(page_record, df);
        }
        //This is a descendant
        else{
            //Retrieve the table name
            var tname = dom_descendant.getAttribute("id");
            //
            //Use the globally available record page variable to 
            //access the required descendant
            var js_descendant = page_record.descendants[tname];
            //
            //Use this descendant to guide the editing
            js_descendant.edit_field(df);
        
        };
      
    };
    
    //Change a field on this page; the name id in reference input element
    this.page_change_field = function(ref){//page
        //
        var fname = ref.name;
        //
        var field = this.driver.fields[fname];
        //
        field.change_field(ref, this);
    };
    
    //Quality control the input values and save them to the database
    this.save_current_record = function () {//page_record
        //
        //Ccollect the values to save
        var values = this.collect_values();
        //
        //Do not continue if they fail the quality control check
        if (!this.qc(values))
            return;
        //
        //By default the input values should be inserted, rather than updated
        var update = typeof this.update==="undefined" ? false : this.update;
        //
        var qstring = {
            //
            //Identify the database and table name to save record to
            dbname: this.dbname,
            tname: this.tname,
            update:update,
            //  
            //Remember to json encode the name/value pairs
            values: JSON.stringify(values)
        };
        //
        //Add a primary key if this is an existing record
        if (typeof this.primarykey !== "undefined" && this.primarykey !== null) {
            qstring.primarykey = this.primarykey;
        }
        //
        //Use the ajax method to save the values
        this.ajax("save_values", qstring, "json", function (result) {
            //
            switch (result.status) {
                case "ok":
                    //
                    //Refresh the page
                    window.location.reload();
                    //
                    //Invite the user to perform furter actiions after after a 
                    //successful saving of a record.
                    this.after_save(values); 
                    break;
                case "error":
                    //
                    //Show the error from html property
                    this.show_error_msg(result.html);
                    break
                default:
                    this.show_error_msg("Unknown ajax result status '" + result.status + "'");
            }
        });

    };
    
    //What do do after a successful saving of a record. By default, we do
    //nothing
    this.after_save = function(values){
        //
    }
    //
    //Collect all the values to save
    this.collect_values = function(){
      
        var values={};
        //
        //Let 'fields' be all the fields of the current driver
        var fields = this.driver.fields;
        //
        //Loop through the fields structure
        for(var i in fields){
            //
            //This assignmment should be conditial on field value not being 
            //empty. Be careful what you mean by being empty baseuse zero
            //lenth string is not a ull
            if (fields[i].value!==null || fields[i].value!==''){
                values[i]=fields[i].value;
            }
        }
        //
        return values;
    };
    
    //The default quclity control (qc) check does nothing
    this.qc = function(values){
        return true;
    };
    
    //
    //Let x be the querystring of the parent; I will ovrride it in the the
    //implementation of this version's querystring
    this.old_get_querystring = this.get_querystring;
    
    //Returns a query string for supporting CRUD operations on this 
    //page. This extends the page records version by adding a primary key of
    //the table
    this.get_querystring = function(dom_record=null){//page_descendant
        //
        //Get the querystring of the parent page of records; x is the parent
        //query string before overring it.
        var qstring = this.old_get_querystring(dom_record);
        //
        //Add the primary key of this record
        qstring.primarykey = this.primarykey;
        //
        //Return the richer query string
        return qstring;
    };    
    

    //Override the default page initialize function by including code that 
    //populates the descendants of this page record with data
    this.onload = function(){//show_descendant
        //
        //Get the descendants node by searching from the entire document
        var des_node = window.document.querySelector("descendants");
        //
        //Step through every descendant and paint its page in this window
        for(var tname in this.descendants){
            //
            //Set the data required to show descendant
            var qstring = {
                //
                //Set the descendant's table name; it is the extra data 
                //required over and above that of this parent record
                tname:tname,
                //
                //Set the name of the underlying database; all teh following data
                //comes from this page_record
                dbname:this.dbname,
                //
                //Set the name of the parent table name
                parent_tname:this.tname,
                //
                //Set the primary key of the parent table
                parent_primarykey:this.primarykey
            };
            
            //
            //Create the descendant page and display it. Ajax is used because we
            //want 2 outputs, viz., (1) the html for dislaying the page and (2) 
            //the js data structure for enriching this page in order to support 
            //further interactions. Note how we override the current classname
            this.ajax("show_descendant", qstring, "json", function(result){
                //
                //Save the descendant data to its correct place. NOTE THAT THIS
                //IS AN ASYNCHRONOUS OPERATION, SO THE tname ABOVE MAY NOT BE 
                //NECESSARILY THE ONE WE WANT FOR THIS FUNCTION. Hence put it in
                //the result. Remember to activate it
                this.descendants[result.extra.tname] = this.activate(result.extra.data);
                //
                //Add the html to the children of descendants node
                //
                //Create a dummy element; you can call it anything bacsuse we 
                //will overwrite it using the outerHTML property below
                var a = window.document.createElement("descendant");
                //
                //Attach the page to the descendant node in preparation for 
                //changing ints out html (which you canot do if a has no parent)
                des_node.appendChild(a);
                //
                //Now set the outer html of the page to that of the incoming 
                //result; see previous comment on outerHTML
                a.outerHTML = result.html;
            }, "page_descendant");
        }
    };

    //
    //On clicking some field on this page, execute the requested method. This 
    //operation determines if the clicking was done on a parent record object 
    //or on one of her descendants. 
    this.onclick_field = function(method){
        //
        //Let page be the object for which we need to execute the method. By 
        //defaut, no page is selected
        var page=false;
        //
        //Get the current dom field by searching the entire document for the 
        //element with class field because by design, there should be only one 
        //such element in a page.
        var df = this.try_current_dom_field();
        //
        if (!df){
            //
            //There is no dom record found. It may be that 
            //- no record is actually selected
            //- a dependant is selectec but not any of its records; perhaps it 
            //  has none. Determine if it is the latter case.
           page = this.try_current_js_descendant();
        }
        else{
            //
            //Dermine if the selected dom record is a page record or one of her 
            //descendants
            //
            //Check if the dom field has a dom descendant ancestor
            var dom_descendant = df.closest("descendant");
            //
            if (dom_descendant===null){
                //
                //This is not a descendant, so we assume that the dom record is on 
                //this page that is associated with the global variable, page_rcord,
                //
                //Invoke the page_record' with the rwquested function 
                page = page_record;
            }
            //This is a descendant; perform the action on a descendant
            //page. Which one?
            else{
                page = this.try_current_js_descendant();
            }
        }
        //
        //If the page is valid, execute the requested method on teh correct 
        //object
        if (page){
            //
            page[method]();
        }
    };


    //Returns the current dom descendant of this page based on the "current" 
    //attribute. An alert is provided if there is no current selection
    this.get_current_dom_descendant = function (){
        //
        //Try to get the current dom descendant
        var dd = this.try_current_dom_descendant();
        //
        if (!dd){
            alert ("There is no current descendant selection");
            return false;
        }
        //
        //Return the dom descendant
        return dd;
    };

    //Returns the current dom descendant of this page based on the "current" 
    //attribute. No alert is provided if there is no current selection.
    this.try_current_dom_descendant = function (){
        //
        //Formulate the css selector for the current descendant
        var dselector = "[current='descendant']";
        //
        //Retrieve the current dom descendant, searching from the entire 
        //document.
        var dd = window.document.querySelector(dselector);
        //
        if (dd ===null)
        {
            return false;
        }
        //
        //Return the descendant
        return dd;
    };



    //Returns the current js descendant, alerting the user if there
    //is none. Related to this is the get current dom descendant
    //and the php page_descendant
    this.get_current_js_descendant = function (){
        //
        //Try to get the current js descendant
        var jd = this.try_current_js_descendant();
        //
        if (!jd){
            alert ("There is no current descendant selection");
            return false;
        }
        //
        //Return the dom descendant
        return jd;
    };

     //Returns the current js descendant of this page based on the "current" 
    //attribute. No alert is provided if there is no current selection.
    this.try_current_js_descendant = function (){
        //
        //Formulate the css selector for the current descendant
        var dd = this.try_current_dom_descendant();
        //
        if (!dd)
        {
            return false;
        }
        //
        //Retrieve the js decsendant from the dom version
        //
        //Get the descendant's table name
        var tname = dd.getAttribute('id');
        //
        //Rerieve the descendant indexd by the table name
        var jd = this.descendants[tname];
        //
        //Retur the js descendant
        return jd;
    };
    
    
}

function page_table(input_){
    //
    //Initialize teh parent page table
    page.call(this, input_);
    //
    this.save_current_record = function(){//page_table
        //
        //Get the current dom record from this page; it has the values we need. 
        //An alert will be raised if none is found.
        var dom_record = this.get_current_dom_record();
        //
        //Save the record, but first disallow empty identification fields
        //
        //Handle the identification data, i.e., collect it, check for missing
        //values (reporting if any). Abort this process if any of the 
        //identification fields is empty. The index valiadation process will 
        //have highlighted the ones that are empty and an appropriate error 
        //message dislayed on this page's error node.
        //
        //Get the empty identification fields
        var fields = dom_record.get_blank_idfields();
        //
        //Abort this process at least one empty identification field is found
        if (fields!==""){
            //
            //Compilelist of empty fields message
            var msg = "The following identification fields are blank: "+fields;
            //
            //Display it
            this.show_error_msg(msg);
            //
            //Exit this function; hopefully the get-index-value process will
            //have displayed teh empty data error
            return false;
        }
        //
        //Get the querystring that is appropriate for saving this page. A 
        //descendant page needs more than a age of records
        var qstring = this.get_querystring(dom_record);
        //
        //Save the current record and return, as extra data, the json string
        //of the saved values, as a name/value pairs object 
        this.ajax("save_current_record", qstring, "json", function(result){
            //
            //The result's extra data is an object of name/value pairs
            var new_values = result.extra;
            //
            //Set the new values to the dom record -- this is a form of a very 
            //controlled refresh
            dom_record.update_view(new_values);
            //
            //Swith to display mode (i.e, not edit mode)
            dom_record.switch_record_to_edit(false);
        });
    };
}


 