<?php

class SystemService {

    function restoreDatabaseFromBackup(){
        $restoreResult = new StdClass();
        global $sUSER, $sPASSWORD, $sDATABASE, $cnInfoCentral,$sGZIPname;
        $file = $_FILES['restoreFile'];
        $restoreResult->file = $file;
        $restoreResult->type = pathinfo($file['name'], PATHINFO_EXTENSION);
        $restoreResult->type2 = pathinfo(substr($file['name'],0,strlen($file['name'])-3), PATHINFO_EXTENSION);
        $restoreResult->root = dirname(dirname(__FILE__));
        $restoreResult->backupRoot="$restoreResult->root/tmp_attach/ChurchCRMBackups";
        $restoreResult->imagesRoot = "Images";
        $restoreResult->headers = array();
        // Delete any old backup files
        exec("rm -rf  $restoreResult->backupRoot");
        exec("mkdir  $restoreResult->backupRoot");
        if ($restoreResult->type ==  "gz")
        {   
            if ($restoreResult->type2 ==  "tar")
            {
                exec ("mkdir $restoreResult->backupRoot");
                $restoreResult->uncompressCommand = "tar -zxvf ".$file['tmp_name']." --directory $restoreResult->backupRoot";
                exec($restoreResult->uncompressCommand, $rs1, $returnStatus);
                $restoreResult->SQLfile = "$restoreResult->backupRoot/ChurchCRM-Database.sql";
                $restoreQueries = file($restoreResult->SQLfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                exec("rm -rf $restoreResult->root/Images/*");
                exec("mv -f $restoreResult->backupRoot/Images/* $restoreResult->root/Images");
                
            }
            else if ($restoreResult->type2 ==  "sql")
            {
                exec ("mkdir $restoreResult->backupRoot");
                exec ("mv  ".$file['tmp_name']." ".$restoreResult->backupRoot."/".$file['name']);
                $restoreResult->uncompressCommand = "sGZIPname -d $restoreResult->backupRoot/".$file['name'];
                exec($restoreResult->uncompressCommand, $rs1, $returnStatus);;
                $restoreResult->SQLfile = $restoreResult->backupRoot."/".substr($file['name'],0,strlen($file['name'])-3);
                $restoreQueries = file($restoreResult->SQLfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }
        }
        else if ($restoreResult->type == "sql")
        {
             $restoreQueries = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
        $query = '';
        foreach ($restoreQueries as $line) {
            if ($line != '' && strpos($line, '--') === false) {
                $query .= $line;
                if (substr($query, -1) == ';') {
                    $person = RunQuery($query);
                    $query = '';
                }
            }
        }
        exec ("rm -rf $restoreResult->backupRoot"); 
        
       return $restoreResult;
        
    }
    function getDatabaseBackup($params) {
        global $sUSER, $sPASSWORD, $sDATABASE, $sSERVERNAME, $sGZIPname, $sZIPname, $sPGPname; 
        
        $backup = new StdClass();
        $backup->root = dirname(dirname(__FILE__));
        $backup->backupRoot="$backup->root/tmp_attach/ChurchCRMBackups";
        $backup->imagesRoot = "Images";
        $backup->headers = array();
        // Delete any old backup files
        exec("rm -rf  $backup->backupRoot");
        exec("mkdir  $backup->backupRoot");
        // Check to see whether this installation has gzip, zip, and gpg
        if (isset($sGZIPname)) $hasGZIP = true;
        if (isset($sZIPname)) $hasZIP = true;
        if (isset($sPGPname)) $hasPGP = true;

        $backup->params = $params;
        $bNoErrors = true;
        
        $backup->saveTo = "$backup->backupRoot/ChurchCRM-".date("Ymd-Gis");
		$backup->SQLFile = "$backup->backupRoot/ChurchCRM-Database.sql";
       
		$backupCommand = "mysqldump -u $sUSER --password=$sPASSWORD --host=$sSERVERNAME $sDATABASE > $backup->SQLFile";
		exec($backupCommand, $returnString, $returnStatus);

		switch ($params->iArchiveType)
		{
			case 0: # The user wants a gzip'd SQL file. 
                $backup->saveTo.=".sql";
                exec("mv $backup->SQLFile  $backup->saveTo");
                $backup->compressCommand =  "$sGZIPname $backup->saveTo";
				$backup->saveTo .= ".gz";
				exec($backup->compressCommand, $returnString, $returnStatus);
                $backup->archiveResult = $returnString;
				break;
			case 1: #The user wants a .zip file
				$backup->saveTo.=".zip";
                $backup->compressCommand = "$sZIPname -r -y -q -9 $backup->saveTo $backup->backupRoot";
				exec($backup->compressCommand, $returnString, $returnStatus);
                $backup->archiveResult = $returnString;
				break;
            case 2: #The user wants a plain ol' SQL file
                $backup->saveTo.=".sql";
                exec("mv $backup->SQLFile  $backup->saveTo");
                break;
            case 3: #the user wants a .tar.gz file
                $backup->saveTo.=".tar.gz";
                $backup->compressCommand ="tar -zcvf $backup->saveTo -C $backup->backupRoot ChurchCRM-Database.sql -C $backup->root $backup->imagesRoot";
				exec($backup->compressCommand, $returnString, $returnStatus);
                $backup->archiveResult = $returnString;
				break;
             
		}

		if ($params->bEncryptBackup)  #the user has selected an encrypted backup
		{
			putenv("GNUPGHOME=/tmp");
			$backup->encryptCommand = "echo $params->password | $sPGPname -q -c --batch --no-tty --passphrase-fd 0 $backup->saveTo";
			$backup->saveTo .= ".gpg";
			system($backup->encryptCommand);
			$archiveType = 3;
		}

		switch ($params->iArchiveType)
		{
			case 0:
				array_push($backup->headers, "");
			case 1:
                array_push($backup->headers, "Content-type: application/x-zip");
			case 2:
				array_push($backup->headers, "Content-type: text/plain");
			case 3:
				array_push($backup->headers, "Content-type: application/pgp-encrypted");
		}

		$backup->filename = substr($backup->saveTo, strrpos($backup->saveTo,"/",-1)+1);
		array_push($backup->headers, "Content-Disposition: attachment; filename=$backup->filename");

		return $backup;
    
    
    }
    
    function download($filename) {
        set_time_limit(0);
        $path = dirname(dirname(__FILE__))."/tmp_attach/ChurchCRMBackups/$filename";
        if (file_exists($path))
        {
            if ($fd = fopen ($path, "r")) {
                $fsize = filesize($path);
                $path_parts = pathinfo($path);
                $ext = strtolower($path_parts["extension"]);
                switch ($ext) {
                    case "gz":
                        header("Content-type: application/x-gzip");
                        header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\"");
                    break;
                    case "tar.gz":
                        header("Content-type: application/x-gzip");
                        header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\"");
                    break;
                    case "sql":
                        header("Content-type: text/plain");
                        header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\"");
                    break;
                    case "gpg":
                        header("Content-type: application/pgp-encrypted");
                        header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\"");
                    break;
                    case "zip":
                        header("Content-type: application/zip");
                        header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\"");
                    break;
                        // add more headers for other content types here
                    default;
                        header("Content-type: application/octet-stream");
                        header("Content-Disposition: filename=\"".$path_parts["basename"]."\"");
                    break;
                }
                header("Content-length: $fsize");
                header("Cache-control: private"); //use this to open files directly
                while(!feof($fd)) {
                    $buffer = fread($fd, 2048);
                    echo $buffer;
                }
            }
            fclose ($fd); 
            exec("rm -rf  ".dirname(dirname(__FILE__))."/tmp_attach/ChurchCRMBackups");
        }
    }

    function getConfigurationSetting($settingName,$settingValue){
        
    }
    
    function setConfigurationSetting($settingName,$settingValue){
        
    }
}