<?php

    require_once('DbSync.php');

    $tablesToCheck = ['Person'];

    set_time_limit(3 * 60 * 60); // 3 hours


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
 
    //do sync
    $dbSync->sync($tablesToCheck);

    echo "looping\n";
    //infinate loop to catch input
    while(true){

    }

?>