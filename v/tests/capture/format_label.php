<?php
//
//This work relates to the capture namespace
namespace capture;
//
//include the config file 
include_once filter_input(INPUT_SERVER, 'DOCUMENT_ROOT').'\capture\v11\config.php';
//
//Access to the record (and other support classes) classes
include_once \config::home();

//Compile the data to save
$json= file_get_contents("format_label.json");
//
//Separate the data from the container.
$record = new record();
//
//Use the entity to export the data
$result = $record->export($json,format::label);
//
$result->report();
