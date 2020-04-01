<?php

    require_once(__DIR__ . "/DbSyncReciever.php");

    $host =     $_ENV["TARGET_POSTGRES_HOST"];
    $dbName =   $_ENV["TARGET_POSTGRES_DB_NAME"];
    $user =     $_ENV["TARGET_POSTGRES_USER"];
    $pass = $_ENV["TARGET_POSTGRES_PASSWORD"];

    $dbSynReciever = new DbSyncReciever($host, $dbName, $user, $pass); 

    // this is the webservice that will listen for requests to begin the processing of our data
    if($_SERVER["REQUEST_METHOD"] === "GET"){

        // TODO: take this param from web request?
        $dbSynReciever->processDelta("/ftp/test/delta.zip"); 
        echo "OK";
    } 

?>
