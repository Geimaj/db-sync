<?php

/*
    Input: list of table names
*/

    class DbSync {
        public $masterDbConnectionString;
        public $copyDbConnectionString;
        public $tableNames;

        function __construct($tableNames, $masterDbConnectionString, $copyDbConnectionString){
            $this->masterDbConnectionString = $masterDbConnectionString;
            $this->copyDbConnectionString = $copyDbConnectionString;
                            
            // <YourNewStrong@Passw0rd>
            $serverName = "database"; //TODO param
            $connectionOptions = array(
                "Database" => "master", //TODO param
                "Uid" => "sa", //TODO param
                "PWD" => "<YourNewStrong@Passw0rd>" //TODO param
            );
            //Establishes the connection to db A
            echo "Connecting to database...\n";
            $conn = sqlsrv_connect($serverName, $connectionOptions);
            if( $conn === false ) {
                echo "Error connecting to DB";
                die( print_r( sqlsrv_errors(), true));
            }

            // find columns for each table in master db
            $tables = array();
            foreach($tableNames as $table){
                $columns = [];
                $query = "select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='" . $table . "'";
                $params = array($table);
                
                $stmt = sqlsrv_query($conn, $query);
                
                if( $stmt === false ) {
                    die( print_r( sqlsrv_errors(), true));
                }

                while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
                    // print_r($row);
                    array_push($columns, $row['COLUMN_NAME']);
                }

                $tables[$table] = $columns;

                //shut it down
                sqlsrv_free_stmt( $stmt);  
            }

            print_r($tables);

            //connect to copy db 

            sqlsrv_close( $conn);  
        }


        function __destruct(){
            echo "bye";
        }
    }

?>