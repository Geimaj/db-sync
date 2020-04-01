<?php


function unzip($pathToZip, $unzipPath){
    $zip = new ZipArchive;
    $res = $zip->open($pathToZip);
    if($res === TRUE){
        $zip->extractTo($unzipPath);
        $zip->close();
    } else {
        echo "\nerror unzipping\n";
    }
}

?>