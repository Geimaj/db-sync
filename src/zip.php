<?php
/**
     * zipDir method
     *
     *  ----------- ZIP AND UNZIP -----------
     * http://code.hyperspatial.com/1464/zip-directory-recursively/
     * 
     * recursively zip a directory and its contents:
     *  - $source contents of this file/directory gets zipped
     *  - $destinationFile full path of destination zip archive
     * 
     * @param $dir_to_zip
     *         $archive
     *
     * This is the only zip code that creates .xlsx files that Libre Office and Google Docs also open - tried 3 other versions!
     * because zip files must use '/' in paths, not '\'
     * see http://forum.lazarus.freepascal.org/index.php?topic=25110.0
     */
    
    function zipDir($source, $destinationFile, $overwriteFile=TRUE){
        
        if(!extension_loaded('zip')) 
            return "PHP 'zip' extension not loaded.";
        
        if(!file_exists($source))
            return "The source file/folder '$source' does not exist.";
        
        if(file_exists($destinationFile) && !$overwriteFile)
                return "The destination file '$destinationFile' already exists. This could be two users creating files at exactly the same moment.";
        
        $zip = new ZipArchive();
        $result = $zip->open($destinationFile, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
        if($result !== TRUE) 
            return "Error creating destination file '$destinationFile'. Err# $result";
 
        $source = str_replace('\\', '/', realpath($source));
        $filelist = array();
        
        if(is_dir($source) === true){
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
            
            foreach($files as $file){
                // Joshua Gatley-Dewing's fix to allow Libre Office to open files:
                $file = realpath($file);
                $selfDir = dirname($destinationFile);
               
                if($file != $selfDir){
                    $file = str_replace('\\', '/', $file);
                    if(is_dir($file) === true)                  
                        $zip->addEmptyDir(str_replace($source.'/', '', $file.'/'));
                    elseif(file_exists($file) === true)
                        $zip->addFile($file, str_replace($source.'/', '', $file));
                }
            }
        
        } elseif(file_exists($source) === true)
            $zip->addFile($source, basename($source));
        
        $result = $zip->close();
        if($result !== true)
            return "Could not finalize zip archive. Err# $result";
           
        echo "output: {$destinationFile}\n";
        return TRUE;
    }   
    
    /**
     * unzip method
     *
     * @param $file_to_zip
     *         $save_dir
     *
     */
    function unzip($file_to_zip, $savedir) {
         $zip = new ZipArchive;
         $res = $zip->open($file_to_zip);
         if ($res === TRUE) {
             $zip->extractTo($savedir);
             $zip->close();
         } 
         else {
             exit("Error unpacking '$file_to_zip'. Check that it is a valid .xlsx file.");
         }
    }
    
    /**
     * Recursively create parts of a long directory path that do not exist in the file system
     * @param string $path
     * @return bool TRUE on success, FALSE on error
     */
    function createPath($path) {
        if (is_dir($path))
            return true;
 
        $prev_path = dirname($path);
        $return = createPath($prev_path);
        return ($return && is_writable($prev_path)) ? mkdir($path, 0770) : false;
    }

?>