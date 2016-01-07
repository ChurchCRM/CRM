<?php

class SystemService {

    function restoreDatabaseFromBackup(){
        $restoreResult = new StdClass();
        global $sUSER, $sPASSWORD, $sDATABASE, $cnInfoCentral;
        $file = $_FILES['restoreFile'];
        $restoreResult->file = $file;
        exec ("rm -rf ../Images");
        if ($file['type'] ==  "application/x-gzip" )
        {
            exec ("mkdir /tmp/restore_unzip");
            $restoreResult->uncompressCommand = "tar -zxvf ".$file['tmp_name']." --directory /tmp/restore_unzip";
            exec($restoreResult->uncompressCommand, $rs1, $returnStatus);
            #$restoreResult->uncompressReturn = $rs1;
            $restoreResult->SQLfiles = glob('/tmp/restore_unzip/SQL/*.sql');
            $restoreQueries = file($restoreResult->SQLfiles[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            exec ("mv -f /tmp/restore_unzip/Images/* ../Images");
        }
        else
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
        exec ("rm -rf /tmp/restore_unzip"); 
       return $restoreResult;
        
    }
    function getDatabaseBackup($params) {
        global $sUSER, $sPASSWORD, $sDATABASE, $sSERVERNAME, $sGZIPname, $sZIPname, $sPGPname; 
        
        $backup = new StdClass();
        $backup->backupRoot="/tmp/ChurchCRMBackups";
        $backup->headers = array();
        // Delete any old backup files
        exec("rm -rf  $backup->backupRoot");
        exec("mkdir  $backup->backupRoot");
        exec("mkdir  $backup->backupRoot/SQL");
        // Check to see whether this installation has gzip, zip, and gpg
        if (isset($sGZIPname)) $hasGZIP = true;
        if (isset($sZIPname)) $hasZIP = true;
        if (isset($sPGPname)) $hasPGP = true;

        $backup->params = $params;
        $bNoErrors = true;
        
        $backup->saveTo = "$backup->backupRoot/ChurchCRM-".date("Ymd-Gis");
		$backup->SQLFile = "$backup->backupRoot/SQL/ChurchCRM-Database.sql";
		$backupCommand = "mysqldump -u $sUSER --password=$sPASSWORD --host=$sSERVERNAME $sDATABASE > $backup->SQLFile";
		exec($backupCommand, $returnString, $returnStatus);

		switch ($params->iArchiveType)
		{
			case 0:
                $backup->saveTo.=".sql";
                exec("mv $backup->SQLFile  $backup->saveTo");
				$compressCommand = "$sGZIPname $backup->saveTo";
                $backup->compressCommand = $compressCommand;
				$backup->saveTo .= ".gz";
				exec($compressCommand, $returnString, $returnStatus);
                $backup->archiveResult = $returnString;
				break;
			case 1:
				$backup->saveTo.=".zip";
				$compressCommand = "$sZIPname $backup->SQLFile $backup->saveTo";
                $backup->compressCommand = $compressCommand;
				exec($compressCommand, $returnString, $returnStatus);
                $backup->archiveResult = $returnString;
				break;
            case 2:
                $backup->saveTo.=".sql";
                exec("mv $backup->SQLFile  $backup->saveTo");
            case 3:
                $backup->saveTo.=".tar.gz";
				$compressCommand = "tar -zcvf  $backup->saveTo $backup->backupRoot ./churchinfo/Images/*";
                $backup->compressCommand = $compressCommand;
				exec($compressCommand, $returnString, $returnStatus);
                $backup->archiveResult = $returnString;
				break;
             
		}

		if ($params->bEncryptBackup)
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
				array_push($backup->headers, "Content-type: application/x-gzip");
			case 1:
                array_push($backup->headers, "Content-type: application/x-zip");
			case 2:
				array_push($backup->headers, "Content-type: text/plain");
			case 3:
				array_push($backup->headers, "Content-type: application/pgp-encrypted");
		}

		$backup->filename = substr($backup->saveTo, 7);
		array_push($backup->headers, "Content-Disposition: attachment; filename=$backup->filename");

		return $backup;
    
    
    }

    function getConfigurationSetting($settingName,$settingValue){
        
    }
    
    function setConfigurationSetting($settingName,$settingValue){
        
    }
}