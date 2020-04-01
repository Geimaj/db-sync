<?php
    /*
    This class is used to update the postgres db.
    processDelta() is called from a web service
    which instructs us to unzip a file, loop through the contents
    and apply the changes found in `tablename`.json
    to the postgres DB.

    When the processDelta() is called we expect there to be a file
    at $pathToZip. We extract this file to /tmp/delta/ as an intermediary for processing. 
    /tmp/delta is removed after processing to avoid stale data
    */
    require_once __DIR__ . "/unzip.php";


    class DbSyncReciever {
        private $conn;

        function __construct($dbHost, $dbName, $dbUser, $dbPass){
            //connect to postgres
            $connectionString = "host={$dbHost} dbname={$dbName} user={$dbUser} password={$dbPass}";
            $this->conn = pg_connect($connectionString)
            or die('Could not connect: ' . pg_last_error());
        }

        function __destruct(){
            // close db connection
            pg_close($this->conn);
        }

        function processDelta($pathToZip){
            //unzip file
            $unzipPath = "/tmp/delta";
            unzip($pathToZip, $unzipPath);


            // //loop through json files
            $files = array_diff(scandir($unzipPath), array('.', '..', 'app'));
            
            foreach($files as $file){
                $filePath = "{$unzipPath}/{$file}";
                $fileContents = file_get_contents($filePath);
                $json = json_decode($fileContents, true);
                $tableName = substr($file, 0, strpos($file, '.json'));
                //insert new rows
                $this->insertNewRows($tableName, $json["newRows"][0]);
                //update updated rows
                $this->updateRows($tableName, $json["updatedRows"][0]);
                // //delete deleted rows
                $this->deleteRows($tableName, $json["deletedRows"][0]);

                //delete json file after it has been processed
                unlink($filePath);
            }
        }

        function getColumnNames($row){
            $keys = array_keys($row);
            return implode(", ", array_keys($row));
        }

        function getValues($row){
            // convert SYNC_LAST_VERSION to an int
            $rowVersion =  hexdec($row["SYNC_VERSION"]);
            // wrap strings with '
            foreach($row as $i => $value){
                if(is_string($value)){
                    $row[$i] = "'{$value}'";
                }
            }
            $row["SYNC_VERSION"] = $rowVersion;

            return implode(", ", array_values($row));
        }

        function getPrimaryKey($tableName){
            $query = "select attname
            from pg_index i
            join pg_attribute a 
            on a.attrelid = i.indrelid
            and a.attnum = ANY(i.indkey)
            where i.indrelid = '{$tableName}'::regclass
            and i.indisprimary;";

            $result = pg_query($this->conn, $query);
            if(!$result){
                die(pg_last_error($this->conn));
            }
            
            $pk = pg_fetch_row($result)[0];
            pg_free_result($result);
            return $pk;
        }

        function insertNewRows($table, $rows){
            foreach($rows as $row){
                $columns = $this->getColumnNames($row);
                $values = $this->getValues($row);

                $query = "insert into {$table} ({$columns}) values ({$values});";

                $result = pg_query($this->conn, $query);
                if(!$result){
                    die(pg_last_error($this->conn));
                }

                pg_free_result($result);
            }
        }

        function updateRows($table, $rows){
            //find primary key for table
            $pk = $this->getPrimaryKey($table);
            foreach($rows as $row){
                // build string of set name=val
                $setString = "set ";
                $row["SYNC_VERSION"] = hexdec($row['SYNC_VERSION']);
                foreach($row as $name => $val){
                    $wrappedVal = $val;
                    //wrap the strings in ''
                    if($name !== "SYNC_VERSION" && is_string($val)){
                        $wrappedVal = "'{$val}'";
                    }
                    $setString = "{$setString} {$name}={$wrappedVal}, ";
                }
                //remove trailing comma and space
                $setString = substr($setString, 0, -2);

                $pkVal = $row[$pk];
                $query = "update {$table} {$setString} where {$pk} = {$pkVal}";

                $result = pg_query($this->conn, $query);
                if(!$result){
                    die(pg_last_error($this->conn));
                }

                pg_free_result($result);
            }
        }

        function deleteRows($tableName, $rows){
            $pk = $this->getPrimaryKey($tableName);
            foreach($rows as $row){
                $pkVal = $row[$pk];
                $query = "delete from {$tableName} where {$pk} = {$pkVal};";

                $result = pg_query($this->conn, $query);
                if(!$result){
                    die(pg_last_error($this->conn));
                }

                pg_free_result($result);
            }
        }
    }

?>
