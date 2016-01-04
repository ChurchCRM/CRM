<?php

class SystemService {

    function restoreDatabaseFromBackup($params){
         global $sUSER, $sPASSWORD, $sDATABASE;
        $file = $_FILES['restoreFile']['tmp_name'];
        $clearCommand = "mysqldump -u $sUSER --password=$sPASSWORD  -e 'drop database  $sDATABASE'";
        echo $clearCommand." ";
        exec($clearCommand, $returnString, $returnStatus);
        echo $returnString." ".$returnStatus;
        $createCommand = "mysql -u'$sUSER' -p'$sPASSWORD' -e 'CREATE DATABASE $CRM_DB_NAME CHARACTER SET utf8;' ";
        echo $createCommand." ";
        exec($createCommand, $returnString, $returnStatus);
        echo $returnString." ".$returnStatus;
        $restoreCommand = "mysql -u $sUSER --password=$sPASSWORD $sDATABASE < $file";
        echo $restoreCommand." ";
        exec($restoreCommand, $returnString, $returnStatus);
        echo $returnString." ".$returnStatus;
        
        echo '{"file":"'.$file.'"}';
        
    }
    function getDatabaseBackup($params) {
        global $sUSER, $sPASSWORD, $sDATABASE, $sSERVERNAME, $sGZIPname, $sZIPname, $sPGPname; 
        $backup = new StdClass();
        $backup->headers = array();
        // Delete any old backup files
        //exec("rm -f SQL/InfoCentral-Backup*");
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
				$backup->saveTo .= ".gz";
				exec($compressCommand, $returnString, $returnStatus);
				break;
			case 1:
				$archiveName = substr($backup->saveTo, 0, -4);
				$compressCommand = "$sZIPname $archiveName $backup->saveTo";
				$backup->saveTo = $archiveName . ".zip";
				exec($compressCommand, $returnString, $returnStatus);
				break;
		}

		if ($params->bEncryptBackup)
		{
			putenv("GNUPGHOME=/tmp");
			$encryptCommand = "echo $sPassword1 | $sPGPname -q -c --batch --no-tty --passphrase-fd 0 $backup->saveTo";
			$backup->saveTo .= ".gpg";
			system($encryptCommand);
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

		$backup->filename = substr($backup->saveTo, 4);
		array_push($backup->headers, "Content-Disposition: attachment; filename=$backup->filename");

		return $backup;
    
    
    }

    function getConfigurationSetting($settingName,$settingValue){
        
    }
    
    function setConfigurationSetting($settingName,$settingValue){
        
    }
}