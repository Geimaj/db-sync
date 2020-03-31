<?php

function unzipYo($pathToZip, $unzipPath){
    $zip = new ZipArchive;
    $res = $zip->open($pathToZip);
    if($res === TRUE){
        $zip->extractTo($unzipPath);
        $zip->close();
    } else {
//         exit("Error unpacking {$file_to_zip} Check that it is a valid .xlsx file.");
        echo "\nerror zipping\n";
    }
}


    function processDelta($pathToZip){
        //unzip file
        $unzipPath = "/tmp/delta/";
        unzipYo($pathToZip, $unzipPath);

        // //loop through json files
        // $files = array_diff(scandir($path), array('.', '..'));

        // print_r($files);
    }

?>
