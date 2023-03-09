<?php
//
//Access to the record (and other support classes) classes
include_once filter_input(INPUT_SERVER, 'DOCUMENT_ROOT').'\library\v\code\capture.php';
include_once filter_input(INPUT_SERVER, 'DOCUMENT_ROOT').'\library\v\code\schema.php';
include_once filter_input(INPUT_SERVER, 'DOCUMENT_ROOT').'\library\v\code\sql.php';

//Compile the data to save
$tabular_str = file_get_contents("products.json");

//Separate the data from the container.
$record = new record();
//
//Use the entity to export the data
$result = $record->export(json_decode($tabular_str), format::tabular);
//
echo json_encode($result);
