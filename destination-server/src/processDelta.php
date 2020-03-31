<?php

function unzipYo($pathToZip, $unzipPath){
    $zip = new ZipArchive;
}


    function processDelta($pathToZip){
        //unzip file
        $unzipPath = "/tmp/delta/";
        unzipYo($pathToZip, $unzipPath);

        // //loop through json files
        // $files = array_diff(scandir($path), array('.', '..'));

        // print_r($files);
    }


//     function unzip($file_to_zip, $savedir) {
//          $zip = new ZipArchive;
//          $res = $zip->open($file_to_zip);
//          if ($res === TRUE) {
//              $zip->extractTo($savedir);
//              $zip->close();
//          } 
//          else {
//              exit("Error unpacking '$file_to_zip'. Check that it is a valid .xlsx file.");
//          }
//     }

?>
