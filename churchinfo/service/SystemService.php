<?php

class SystemService {

    function restoreDatabaseFromBackup(){
        $restoreResult = new StdClass();
        global $sUSER, $sPASSWORD, $sDATABASE, $cnInfoCentral;
        $file = $_FILES['restoreFile'];
        $restoreResult->file = $file;
        if ($file['type'] ==  "application/x-gzip" )
        {
            exec ("mkdir /tmp/restore_unzip");
            $restoreResult->uncompressCommand = "tar -zxvf ".$file['tmp_name']." --directory /tmp/restore_unzip";
            exec($restoreResult->uncompressCommand, $rs1, $returnStatus);
            $restoreResult->uncompressReturn = $rs1;
            $restoreResult->SQLfiles = glob('/tmp/restore_unzip/SQL/*.sql');
            $restoreResult->restoreQueries = file($restoreResult->SQLfiles[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            exec ("rm -rf ../Images");
            exec ("mv -f /tmp/restore_unzip/Images/* ../Images");
        }
        else
        {
             $restoreResult->restoreQueries = "mysql -u $sUSER --password=$sPASSWORD $sDATABASE < ".$file['tmp_name'];
        }
        $query = '';
        foreach ($restoreResult->restoreQueries as $line) {
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
        $backup->headers = array();
        // Delete any old backup files
        exec("rm -f ../SQL/*");
        // Check to see whether this installation has gzip, zip, and gpg
        if (isset($sGZIPname)) $hasGZIP = true;
        if (isset($sZIPname)) $hasZIP = true;
        if (isset($sPGPname)) $hasPGP = true;

        $backup->params = $params;
        $bNoErrors = true;
        

		$backup->saveTo = "../SQL/ChurchCRM-Backup-" . date("Ymd-Gis") . ".sql";
		$backupCommand = "mysqldump -u $sUSER --password=$sPASSWORD --host=$sSERVERNAME $sDATABASE > $backup->saveTo";
		exec($backupCommand, $returnString, $returnStatus);

		switch ($params->iArchiveType)
		{
			case 0:
				$compressCommand = "$sGZIPname $backup->saveTo";
                $backup->compressCommand = $compressCommand;
				$backup->saveTo .= ".gz";
				exec($compressCommand, $returnString, $returnStatus);
                $backup->archiveResult = $returnString;
				break;
			case 1:
				$archiveName = substr($backup->saveTo, 0, -4);
				$compressCommand = "$sZIPname $archiveName $backup->saveTo";
                $backup->compressCommand = $compressCommand;
				$backup->saveTo = $archiveName . ".zip";
				exec($compressCommand, $returnString, $returnStatus);
                $backup->archiveResult = $returnString;
				break;
            case 3:
                $archiveName = substr($backup->saveTo, 0, -4).".tar.gz";
				$compressCommand = "tar -zcvf $archiveName $backup->saveTo ../Images/*";
                $backup->compressCommand = $compressCommand;
				$backup->saveTo = $archiveName;
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