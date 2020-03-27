<?php
    require_once(__DIR__ . '/DbSync.php');

    $tablesToSync = ['Person'];

    $dbA = "mssql:host=localhost;dbname=master";
    $dbB = "mssql:host=localhost;dbname=copy";


    $dbSync = new DbSync($tablesToSync, $dbA, $dbB);


?>