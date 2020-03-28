<?php
    require_once(__DIR__ . '/DbSync.php');

    $tablesToCheck = ['Person'];

    $masterDbHost = getenv('MASTER_DB_HOST');
    $masterDbName = getenv('MASTER_DB_NAME');
    $masterDbUser = getenv('MASTER_DB_USER');
    $masterDbPassword = getenv('MASTER_DB_PASSWORD');
    
    $copyDbName = getenv('COPY_DB_NAME');

    $connectionDetails = array(
        "host" => $masterDbHost,
        "user" => $masterDbUser,
        "password" => $masterDbPassword
    );

    //create dbsync object
    $dbSync = new DbSync($connectionDetails, $masterDbName, $copyDbName);
 
    //get diff
    $diff = $dbSync->getDiff($tablesToCheck);

    echo "\ndiff:\n";
    print_r($diff);
?>