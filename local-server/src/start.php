<?php

    require_once(__DIR__ . '/DbSyncPublisher.php');

    $tablesToCheck = ['Person'];

    set_time_limit(3 * 60 * 60); // 3 hours

    //configure params here or with environment variables in docker-compose.yml
    $masterDbHost = getenv('MASTER_DB_HOST');
    $masterDbName = getenv('MASTER_DB_NAME');
    $masterDbUser = getenv('MASTER_DB_USER');
    $masterDbPassword = getenv('MASTER_DB_PASSWORD');
    
    $copyDbName = getenv('COPY_DB_NAME');

    $sftpHost = getenv('SFTP_HOST');
    $sftpPort = getenv('SFTP_PORT');
    $sftpRemotePath = getenv('SFTP_REMOTE_PATH');
    $sftpUser = getenv('SFTP_USER');;
    $sftpPassword = getenv('SFTP_PASSWORD');

    $proccessOutputUrl = getenv('PROCESS_OUTPUT_URL');


    $dbConnectionDetails = array(
        "host" => $masterDbHost,
        "user" => $masterDbUser,
        "password" => $masterDbPassword
    );

    $sftpConnectionDetails = array (
        "host" => $sftpHost,
        "port" => $sftpPort,
        "remotePath" => $sftpRemotePath,
        "user" => $sftpUser,
        "password" => $sftpPassword
    );

    //create dbsync object
    $dbSync = new DbSyncPublisher($sftpConnectionDetails, $dbConnectionDetails, $masterDbName, $copyDbName);
 
    // do sync
    $dbSync->sync($tablesToCheck, $proccessOutputUrl);

?>