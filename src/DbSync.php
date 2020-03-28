<?php

/*
    Input: list of table names
*/

    class DbSync {
        private $masterDbName;
        private $copyDbName;
        private $tablesToCheck;
        private $conn;

        function __construct($connectionDetails, $masterDbName, $copyDbName){
            $this->masterDbName = $masterDbName;
            $this->copyDbName = $copyDbName;
            $host = $connectionDetails["host"];

            $connectionOptions = array(
                "Uid" => $connectionDetails["user"],
                "PWD" => $connectionDetails["password"]
            );
            echo "connecting to {$host}...\n";
            $this->conn = sqlsrv_connect($host, $connectionOptions);
            if($this->conn === false){
                echo "Error connecting to DB {$host}\n";
                die( print_r( sqlsrv_errors(), true));
            }
        }

        function __destruct(){
            sqlsrv_close($this->conn);
            echo "bye";
        }

        function getDiff($tablesToCheck){
            $difference = array();
            foreach($tablesToCheck as $tableName){ //get table
                echo "checking table {$tableName}";
                $tableDiff = array();

                //get columns for this table
                // NOTE: we expect the tables to have the same columns across databases
                $columns = $this->getColumns($tableName);

                //get primary key for this table
                $pk = $this->getPrimaryKey($tableName);
                if($pk === null){
                    die("No Primary key set for {$this->masterDbName}.{$tableName}");
                }

                // Get all new records
                $newRows = $this->getNewRows($tableName, $pk);

                // Get all deleted records 
                // $deletedRows = $this->getDeletedRows($tableName, $pk);
                
                // // -- Get all updated records 
                // $updatedRows = $this->getUpdatedRows($tableName, $pk);


                // -- Update  table_ts_max after sync process is successful
                
                //write changes to copy db

                // UPDATE  table_ts_max
                // SET  ts_max = (SELECT max(ts) as lastTs FROM tb_x)
                


 
                // print_r($columns);

                if(sizeof($tableDiff) > 0){
                    array_push($difference, $tableDiff);
                }
            }

            return $difference;
        }

        function getColumns($tableName){
            $columns = [];
            $query = "select * from {$this->masterDbName}.INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='{$tableName}'";
            $stmt = sqlsrv_query($this->conn, $query);
            
            if( $stmt === false ) {
                echo "error fetching columns";
                die( print_r( sqlsrv_errors(), true));
            } 
            
            while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
                array_push($columns, $row['COLUMN_NAME']);
            }

            sqlsrv_free_stmt($stmt);  

            return $columns;
        }

        function getPrimaryKey($tableName){
            $pk = null;
            $query = "SELECT * 
                      FROM {$this->masterDbName}.INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                      where TABLE_NAME = '{$tableName}' ";
            $stmt = sqlsrv_query($this->conn, $query);

            if($stmt === false){
                die(print_r(sqlsrv_errors(), true));
            }
        
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if(sizeof($row) > 0){
                $pk = $row["COLUMN_NAME"];
            }

            sqlsrv_free_stmt( $stmt);
            return $pk;  
        }

        function getNewRows($tableName, $pk){
            $newRows = [];
            $query = "SELECT A.* 
                FROM {$this->masterDbName}.dbo.{$tableName} A
                LEFT JOIN {$this->copyDbName}.dbo.{$tableName} B
                ON A.{$pk} = B.{$pk} 
                WHERE B.{$pk} IS NULL ";
            echo "running query: \n";
            echo $query;
            echo "\n";
            $stmt = sqlsrv_query($this->conn, $query);

            if($stmt === false){
                die(print_r(sqlsrv_errors(), true));
            }

            while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
                array_push($newRows, $row);
            }

            sqlsrv_free_stmt( $stmt);  
            return $newRows;
        }

        function getUpdatedRows($tableName, $pk){
            $updatedRows = array();
            // $query = "SELECT A.*
            // FROM (
            //     {$this->masterDbName}.dbo.{$tableName} A
            //     INNER JOIN {$this->copyDbName}.dbo.{$tableName} B
            // )
            // LEFT JOIN "

                // SELECT A.*
                // FROM (A.tb_x INNER JOIN B.tb_x ON A.tb_x.pkey = B.tb_x.pkey)
                //     LEFT JOIN  table_ts_max tsMax  ON  tsMax.table_name ='tb_x' 
                // WHERE A.ts > tsMax.ts_max
                

            return $updatedRows;
        }

        function getDeletedRows($tableName, $pk){
            $deletedRows = array();
            $query = "SELECT B.*
            FROM {$this->copyDbName}.dbo.{$tableName} B
            LEFT JOIN {$this->masterDbName}.dbo.{$tableName} A
            ON A.{$pk} = B.{$pk}
            WHERE A.${pk} IS NULL";

            $stmt = sqlsrv_query($this->conn, $query);
            if($stmt === false){
                die(print_r(sqlsrv_errors(), true));
            }

            while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
                array_push($deletedRows, $row);
            }

            sqlsrv_free_stmt( $stmt);  

            return $deletedRows;
        }
    }

?>