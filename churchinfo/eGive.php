<?php
/*******************************************************************************
 *
 *  filename    : eGive.php
 *  last change : 2009-08-27
 *  description : Tool for importing CSV eGive data
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

if (!$_SESSION['bAdmin']) {
    Redirect("Menu.php");
    exit;
}
				
$iFYID = CurrentFY();
$iDepositSlipID = FilterInput($_GET["DepositSlipID"]);

// hard-code these for now, eventually, these would be put into a config file somewhere
$eGiveBreakoutNames2FundIds["rrr"] = 4;
$eGiveBreakoutNames2FundIds["renew"] = 4;
$eGiveBreakoutNames2FundIds["rejoice"] = 4;
$eGiveBreakoutNames2FundIds["refresh"] = 4;
$eGiveBreakoutNames2FundIds["capital"] = 4;
$eGiveBreakoutNames2FundIds["ocwm"] = 2;
$eGiveBreakoutNames2FundIds["mission"] = 2;

//$eGiveBreakoutNames2FundIds["building"] = 5;
//$eGiveBreakoutNames2FundIds["mortgage"] = 5;


$eGiveBreakoutNames2FundIds["general"] = 1;
$eGiveBreakoutNames2FundIds["operating"] = 1;
$eGiveBreakoutNames2FundIds["offering"] = 1;

$defaultFundId = 5; // misc

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
$rsFunds = RunQuery($sSQL);
mysql_data_seek($rsFunds,0);
while ($row = mysql_fetch_array($rsFunds)) {
	$fun_id = $row["fun_ID"];
	$fun_name = $row["fun_Name"];
	$fundName2Id[$fun_name] = $fun_id;
	$fundId2Name[$fun_id] = $fun_name;
	// if (!isset($defaultFundId)) {
	//	$defaultFundId = $fun_id;
	//}
} // end while

// get array of all existing payments into a 'cache' so we don't have to keep querying the DB

//  need to use plg_FamID to get fam_Envelope from family_fam  

// I'm going to do this a dumb way 'cause I'm not that good with SQL..  first get a hash of famIDs to famEnvelopes, then get the pledge data and use famID to get envelope

$sSQL = "SELECT fam_ID, fam_Envelope from family_fam where fam_Envelope <> ''";
$famIDs = RunQuery($sSQL);
while ($row = mysql_fetch_array($famIDs)) {
	$famID = $row["fam_ID"];
	$famEnvelope = $row["fam_Envelope"];
	$famID2Envelope[$famID] = $famEnvelope;
	$famEnvelope2ID[$famEnvelope] = $famID;
//echo "famID2Envelope . '" . $famID . "'->'" . $famEnvelope . "'\n";
}

$sSQL = "SELECT plg_plgID, plg_date, plg_amount, plg_fundID, plg_FamID, plg_comment from pledge_plg where plg_method=\"EGIVE\" AND plg_PledgeOrPayment=\"Payment\";";

$rsPlgIDs = RunQuery($sSQL);
while ($row = mysql_fetch_array($rsPlgIDs)) {
	$plgID = $row["plg_plgID"];  // don't think I need this one, but I'll leave it in in case I do....
	$date = $row["plg_date"];
	$amount = $row["plg_amount"];
	$fundID = $row["plg_fundID"];
	$famID = $row["plg_FamID"];
	$envelope = $famID2Envelope[$famID];
	$comment = $row["plg_comment"];
	$key = $date . "|" . $fundID . "|" . $envelope . "|" . $comment;
	$eGiveExisting[$key] = $amount;
} // end while



// Set the page title and include HTML header
$sPageTitle = "eGive CSV Import";
require "Include/Header.php";

// Is the CSV file being uploaded?
if (isset($_POST["UploadCSV"])) {
    // Check if a valid CSV file was actually uploaded
    if ($_FILES['CSVfile']['name'] == "") {
        $csvError = gettext("No file selected for upload.");
    } else { // Valid file, so save it and display the import mapping form.
        $system_temp = ini_get("session.save_path");
        $csvTempFile = $system_temp . "/import.csv";
        move_uploaded_file($_FILES['CSVfile']['tmp_name'], $csvTempFile);
        // create the file pointer
        $pFile = fopen ($csvTempFile, "r");

        // count # lines in the file
        $iNumRows = 0;
        while ($tmp = fgets($pFile,2048)) $iNumRows++;
        rewind($pFile);

        // create the form
        ?>
		<form method="post" action="eGive.php?<?php echo "DepositSlipID=".$iDepositSlipID?>"

        <?php
        echo gettext("Total number of rows in the CSV file:") . $iNumRows; ?>
        <br>
		<input type="checkbox" value="1" checked name="IgnoreFirstRow"><?php echo gettext("Ignore first CSV row (to exclude a header)"); ?>
    		<br>

        <table border=1>
		<?php
        // grab and display up to the first 8 lines of data in the CSV in a table
        $iRow = 0;
        while (($aData = fgetcsv($pFile, 2048, ","))) // do this if we only want to show a subset of dat && $iRow++ < 9)
        {
            $numCol = count($aData);

            echo "<tr>";
            for ($col = 0; $col < $numCol; $col++) {
				if ($col == 1 or $col == 2 or $col == 3 or $col == 10 or $col == 11) {
                		echo "<td>" . $aData[$col] . "&nbsp;</td>"; 
				}
            }
            echo "</tr>";
        }

        fclose($pFile);
	}

    echo "</table>";
    ?>
    <BR>
    <input type="submit" class="icButton" value="<?php echo gettext("Perform Import"); ?>" name="DoImport">
    </form>
    <?php
} elseif (isset($_POST["DoImport"])) {
    $system_temp = ini_get("session.save_path");
    $csvTempFile = $system_temp . "/import.csv";

    // make sure the file still exists
    if (file_exists($csvTempFile)) {
        // create the file pointer
        $pFile = fopen ($csvTempFile, "r");

        // Get the number of CSV columns for future reference
        $aData = fgetcsv($pFile, 2048, ",");
        $numCol = count($aData);
        if (!isset($_POST["IgnoreFirstRow"])) rewind($pFile);
		$importCreated = 0;
		$importUpdated = 0;
		$importNoChange = 0;
		$importError = 0;

        while ($aData = fgetcsv($pFile, 2048, ",")) {
			$dateArray = explode('/', $aData[1]); // this date is in mm/dd/yy format.  churchinfo needs it in yyyy-mm-dd format, so switch it
			if (strlen($dateArray[2]) == 2) {
				$dateArray[2] += 2000;
			}
			$date = $dateArray[2] . "-" . $dateArray[0] . "-" . $dateArray[1];
			$envelope = $aData[2];
			if (array_key_exists($envelope, $famEnvelope2ID)) {
				$iFamily = $famEnvelope2ID[$envelope];
			
				$amount = $aData[10];
				$breakdown = $aData[11];

				$foundFundId = '';
				foreach ($eGiveBreakoutNames2FundIds as $matchKey => $fundId) {
					if (preg_match("%$matchKey%i", $breakdown)) {
						$foundFundId = $fundId;
						break;
					}
				}		

				$comment = "egive breakdown: " . $breakdown;

				if ($foundFundId == '') {
					$foundFundId = $defaultFundId;
				}
				$keyExisting = $date . "|" . $foundFundId . "|" . $envelope . "|" . $comment;
				if ($eGiveExisting and array_key_exists($keyExisting, $eGiveExisting)) {
					if ($eGiveExisting[$keyExisting] <> $amount) { // record already exists, just update amount
						$sSQL = "UPDATE pledge_plg SET plg_DateLastEdited='" . date("YmdHis") . "', plg_comment = '" . $comment . "', plg_amount='" . $amount . "' WHERE plg_famID='" . $iFamily . "' AND plg_date='" . $date . "' AND plg_FundID='" . $foundFundId . "' AND plg_method='EGIVE';";
						++$importUpdated;
						RunQuery($sSQL);
					} else {
						++$importNoChange;
					}
				} else { //  insert a new record
					$sSQL = "INSERT INTO pledge_plg (plg_famID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_NonDeductible) VALUES ('" . $iFamily . "','" . $iFYID . "','" . $date . "','" . $amount . "','Once','EGIVE','" . $comment . "','" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",'Payment'," . $foundFundId . ",'" . $iDepositSlipID . "','0')";
					++$importCreated;
					RunQuery($sSQL);
				}
			} else {
				echo '<p style="color: red">' . "Error: Gift from '" . $aData[3] . "' for amount '" . $amount . "' for fund '" . $breakdown . "' not imported, envelope '" . $envelope . "' not matched to family</p>";
				++$importError;
			}
		}
        fclose($pFile);

        // delete the temp file
        unlink($csvTempFile);
    }
    else {
        echo gettext("ERROR: the uploaded CSV file no longer exists!");
	} ?>

	<p class="MediumLargeText"> <?php echo gettext("Data import results: ") . $importCreated . gettext(" gifts were imported, ") . $importUpdated . gettext(" gifts were updated, ") . $importNoChange . gettext(" gifts unchanged, and ") . $importError . gettext(" gifts not imported due to problems");?></p>
	<input type="button" class="icButton" value="<?php echo gettext("Back to Deposit Slip");?>" onclick="javascript:document.location='DepositSlipEditor.php?DepositSlipID=<?php echo $iDepositSlipID;?>'"
<?php
} else  { ?>
	<p style="color: red"> <?php echo gettext($csvError); ?></p>
	<form method="post" action="eGive.php?DepositSlipID=<?php echo $iDepositSlipID ?>" enctype="multipart/form-data">
	<input class="icTinyButton" type="file" name="CSVfile"> 
	<input type="submit" class="icButton" value="<?php echo gettext("Upload CSV File"); ?>" name="UploadCSV">
	</form>
<?php
}

require "Include/Footer.php";
?>
