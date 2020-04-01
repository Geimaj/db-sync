<?php
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
                // $this->updateRows($tableName, $json["updatedRows"][0]);
                // //delete deleted rows
                // deleteRows($json["deletedRows"]);

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
    }

?>
