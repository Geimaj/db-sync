<?php
require_once __DIR__ . "/../vendor/autoload.php";
use phpseclib\Net\SFTP;

  
    function sftpSend($host, $port, $remotePath, $user, $pw){
        try {           
            echo "connecting to {$host}:{$port}\n";
            $sftp = new SFTP($host, $port);
            echo "loggin in with {$user}:{$pw}\n";
            if (!$sftp->login($user, $pw)) {
                // TODO: get Log:err code 
                // NOTE: I have code for Log::err below… 
                die("Failed to login with $user to $host:$port via SFTP. " . print_r($sftp->getSFTPErrors(), true));
                return FALSE;
            }
        } catch(Exception $e) {
            die("Failed connecting to $host via SFTP. " . $e->getMessage());
            return FALSE;
        }
        
        if(!$sftp->put($remotePath . $fileName, $jsonPath . $fileName, SFTP::SOURCE_LOCAL_FILE)) {
            die("WARNING! SFTP function call error: Could not copy the file:\r\n" . $jsonPath . $fileName . " to " . $remotePath . $fileName . "\r\nERROR:" . print_r($sftp->getSFTPErrors(), true));
            return FALSE;
        }
        
        // Remove temp file
        unlink ($jsonPath . $fileName);
        return TRUE;
        
    }
?>