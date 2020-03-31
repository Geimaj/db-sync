<?php

    require_once(__DIR__ . "/processDelta.php");

    // this is the webservice that will listen for requests to begin the processing of our data
    if($_SERVER["REQUEST_METHOD"] === "GET"){
        // TODO: take this param from web request?
        processDelta("/ftp/test/delta.zip"); 
        echo "OK";
    } 

?>