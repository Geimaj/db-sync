<?php
    require_once "zip.php";
    require_once "sftp.php";


    class DbSync {
        private $masterDbName;
        private $copyDbName;
        private $tablesToCheck;
        private $conn;
        private $outputDir;
        private $sftpConnectionDetails;

        function __construct($sftpConnectionDetails, $connectionDetails, $masterDbName, $copyDbName){
            $this->masterDbName = $masterDbName;
            $this->copyDbName = $copyDbName;
            $this->outputDir =  __DIR__ . "/../output";
            $this->sftpConnectionDetails = $sftpConnectionDetails;
            $host = $connectionDetails["host"];

            $connectionOptions = array(
                "Uid" => $connectionDetails["user"],
                "PWD" => $connectionDetails["password"]
            );
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

        function sync($tablesToCheck){
            foreach($tablesToCheck as $tableName){ //get table
                $tableDiff = array();

                //get columns for this table
                // NOTE: we expect the tables to have the same columns (AND PK) across databases
                $columns = $this->getColumns($tableName);
                //get primary key for this table
                $pk = $this->getPrimaryKey($tableName);
                if($pk === null){
                    die("No Primary key set for {$this->masterDbName}.{$tableName}");
                }

                // Get all new records
                $newRows = $this->getNewRows($tableName, $pk);
                $tableDiff['newRows'] = $newRows;

                // Get all deleted records 
                $deletedRows = $this->getDeletedRows($tableName, $pk);
                $tableDiff['deletedRows'] = $deletedRows;
                
                // Get all updated records 
                $updatedRows = $this->getUpdatedRows($tableName, $pk);
                $tableDiff['updatedRows'] = $updatedRows;

                if(sizeof($tableDiff) > 0){
                    $this->writeTableDiffToFile("{$this->outputDir}/{$tableName}.json", $tableDiff);
                }
            }
            // all tables have been processed into output/
            //zip output/
            echo "zipping...\n";
            $zipArchive = zipDir($this->outputDir, "{$this->outputDir}.zip");

            echo "uploading via SFTP...\n";
            //SFTP output.zip to server
            $didSend = sftpSend(
                // $this->sftpConnectionDetails["host"], 
                // "sftp://dbsync_destination_server_1",
                // "dbsync_destination_server_1",
                // "destination_server",

                "172.18.0.3",
                // $this->sftpConnectionDetails["port"], 
                21,
                // $this->sftpConnectionDetails["remotePath"], 
                "/",
                // $this->sftpConnectionDetails["user"], 
                "test",
                // $this->sftpConnectionDetails["password"]
                "testpassword"
            );

            if($didSend){
                echo "\nsent\n";
            } else {
                echo "\nbad send\n";
            }

            // call web service to process zip

            //update copy db

                // -- Update  table_ts_max after sync process is successful
                
                //write changes to copy db

                // UPDATE  table_ts_max
                // SET  ts_max = (SELECT max(ts) as lastTs FROM tb_x)

        }

        function writeTableDiffToFile($path, $diff){
            $json = json_encode($diff);
            $file = fopen($path, "w");
            fwrite($file, $json);
            fclose($file);
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
            $stmt = sqlsrv_query($this->conn, $query);

            array_push($newRows, $this->getResults($stmt));

            sqlsrv_free_stmt( $stmt);  
            return $newRows;
        }

        // here we trust that the masterDB table has a row version column
        // and that the copyDB table has a field keeping track of the highest version seen from masterDB
        function getUpdatedRows($tableName, $pk){
            $updatedRows = [];
            $query = "SELECT A.* 
                FROM {$this->masterDbName}.dbo.{$tableName} A
                LEFT JOIN {$this->copyDbName}.dbo.{$tableName} B
                ON A.{$pk} = B.{$pk} 
                WHERE A.SYNC_VERSION > B.SYNC_LAST_VERSION ";
            $stmt = sqlsrv_query($this->conn, $query);

            array_push($updatedRows, $this->getResults($stmt));

            sqlsrv_free_stmt( $stmt);  
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

            array_push($deletedRows, $this->getResults($stmt));

            sqlsrv_free_stmt($stmt);  

            return $deletedRows;
        }

        function getResults($stmt){
            $results = array();

            //make sure query was success
            if($stmt === false){
                die(print_r(sqlsrv_errors(), true));
            }

            //loop through results
            while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
                // change format of rowversion field to a hex string
                if(isset($row['SYNC_VERSION'])){
                    $row['SYNC_VERSION'] = "0x" . bin2hex($row['SYNC_VERSION']);
                }
                array_push($results, $row);
            }
            
            return $results;
        }
    }

?>