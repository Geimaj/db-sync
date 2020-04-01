<?php
    set_include_path(get_include_path() . PATH_SEPARATOR . 'phpseclib');
    require_once __DIR__ . "/vendor/autoload.php";
    use phpseclib\Net\SFTP;
  
    function sftpSend($host, $port, $localPath, $remotePath, $user, $pw){
        try {           
            echo "connecting to {$host}:{$port}\n";
            $sftp = new SFTP($host, $port);
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

        $remoteFilePath = $remotePath;
        $localFileToSendPath = $localPath;
        if(!$sftp->put($remoteFilePath, $localFileToSendPath, SFTP::SOURCE_LOCAL_FILE)) {
            die("WARNING! SFTP function call error: Could not copy the file:\r\n" . $localFileToSendPath . " to " . $remoteFilePath . "\r\nERROR:" . print_r($sftp->getSFTPErrors(), true));
            return FALSE;
        }
        
        // // Remove temp file
        // unlink ($jsonPath . $fileName);
        return TRUE;
        
    }
?>