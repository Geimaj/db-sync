<?php
    /*
        This class is used to find the difference between two mssql databases.
        One database is the master and the other is a copy, meant to replicate the data
        in a postgres DB on a remote server.

        A delta is created for each table as `tablename`.json and then zipped together.
        The zip file is uploaded to an SFTP server and then a web service is called 
        to start processing the newly uploaded zip fle.

        Once an OK response is recieved from the webservice this class will apply all the changes 
        to copyDB. CopyDB is meant to replicate the remote postgres DB so copyDB will not get updated 
        unless an OK response is recieved from the webservice, indicating the changes were applied to
        the postgres DB. 

    */
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

        function sync($tablesToCheck,$proccessOutputUrl){
            $dbDiff = array();
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
                    $dbDiff[$tableName] = $tableDiff;
                }
            }
            // all tables have been processed into output/
            //zip output/
            echo "zipping...\n";
            $zipArchive = zipDir($this->outputDir, "{$this->outputDir}.zip");

            echo "uploading via SFTP...\n";
            //SFTP output.zip to server
            $didSend = sftpSend(
                $this->sftpConnectionDetails["host"], 
                $this->sftpConnectionDetails["port"], 
                $zipArchive,
                $this->sftpConnectionDetails["remotePath"], 
                $this->sftpConnectionDetails["user"], 
                $this->sftpConnectionDetails["password"]
            );

            if($didSend){
                // call web service to process zip
                $response = file_get_contents("{$proccessOutputUrl}?zipPath={$zipArchive}");
                if($response === "OK"){
                    //update copy db
                    echo "\ngot repose OK: time to update copy DB\n";
                    //write changes to copy db
                    foreach($dbDiff as $tableName => $tableDiff){
                        //insert new rows
                        $this->insertNewRows($tableName, $tableDiff['newRows'][0]);
                        //update updated rows
                        $this->updateRows($tableName, $tableDiff['updatedRows'][0]);
                        //delete deleted rows
                        $this->deleteRows($tableName, $tableDiff['deletedRows'][0]);
                    }
                } else {
                    // the request to process the data was unsuccessful. DO NOT update copy db
                    die("remote server failed to proccess data. {$response}");
                }
            } else {
                die('error uploading via SFTP ');
            }
        }

        function deleteRows($tableName, $rows){
            echo "\nDeleting rows: \n";
            $pk = $this->getPrimaryKey($tableName);
            foreach($rows as $row){
                $pkVal = $row[$pk];
                $query = "delete from {$this->copyDbName}.dbo.{$tableName} where {$pk} = {$pkVal}";

                echo "\n{$query}\n";

                $result = sqlsrv_query($this->conn, $query);
                if(!$result){
                    die(print_r(sqlsrv_errors()));
                }

                sqlsrv_free_stmt($result);
            }
        }

        function updateRows($tableName, $updatedRows){
            //find primary key for table
            $pk = $this->getPrimaryKey($tableName);
            foreach($updatedRows as $row){
                // build string of set name=val
                $setString = "set ";
                $syncVersion = $row['SYNC_VERSION'];
                unset($row['SYNC_VERSION']);
                // $row["SYNC_LAST_VERSION"] = hexdec($syncVersion);
                $row["SYNC_LAST_VERSION"] = $syncVersion;

                foreach($row as $name => $val){
                    $wrappedVal = $val;
                    //wrap the strings in ''
                    if($name !== "SYNC_LAST_VERSION" && is_string($val)){
                        $wrappedVal = "'{$val}'";
                    }
                    $setString = "{$setString} {$name}={$wrappedVal}, ";
                }
                //remove trailing comma and space
                $setString = substr($setString, 0, -2);

                $pkVal = $row[$pk];
                $query = "update {$this->copyDbName}.dbo.{$tableName} {$setString} where {$pk} = {$pkVal};";

                $result = sqlsrv_query($this->conn, $query);
                if(!$result){
                    // die("error running query against copy DB: \n" . $query);
                    die(print_r(sqlsrv_errors()));

                }

                sqlsrv_free_stmt($result);
            }
        }

        function insertNewRows($tableName, $newRows){
            foreach($newRows as $row){
                //get column names
                $syncVersion = $row['SYNC_VERSION'];
                unset($row['SYNC_VERSION']);
                $row['SYNC_LAST_VERSION'] = $syncVersion;
                $columnNames = implode(', ',array_keys($row));

                //get values
                foreach($row as $i => $value){
                    if($i !== "SYNC_LAST_VERSION" && is_string($row[$i])){
                        $row[$i] = "'{$value}'";
                    }
                }
                $values = implode(', ', array_values($row));

                $query = "insert into {$this->copyDbName}.dbo.{$tableName}
                        ($columnNames)
                        values($values)";
                $stmt = sqlsrv_query($this->conn, $query);
                if($stmt === FALSE){
                    die("error running query against copy DB: \n" . $query);
                }
                sqlsrv_free_stmt( $stmt);  
            }
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