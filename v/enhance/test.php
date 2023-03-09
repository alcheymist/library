<?php
//
//Ensure that we are working in the capture namespace
namespace capture;
//
//Catch all errors, including warnings.
\set_error_handler(function($errno, $errstr, $errfile, $errline /*, $errcontext*/) {
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});
//
//Resolve the qustionnaire reference
include_once 'questionnaire.php';
//
//Get the data sets to export
$text = file_get_contents("test.json");
//
//Convert the data to a php structure
$php = json_decode($text);
//
//Use the desired data from the php object to create the questionnaire. 
//Remember questionnaire is defined in the root namspace
$q = new \questionnaire($php->csv);
//
//Export the questionnaire data and log the dta o the given xml file
$Imala = $q->load(__DIR__."\\log.xml");
//
echo json_encode($Imala);
