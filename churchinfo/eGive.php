<?php
/*******************************************************************************
 *
 *  filename    : eGive.php
 *  last change : 2009-08-27
 *  description : Tool for importing eGive data
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

if (!$_SESSION['bFinance'] and !$_SESSION['bAdmin']) {
    Redirect("Menu.php");
    exit;
}

$now = time();
$dDate = date("Y-m-d", $now);
$lwDate = date("Y-m-d", $now - (6 * 24 * 60 * 60));

$iFYID = CurrentFY();
$iDepositSlipID = FilterInput($_GET["DepositSlipID"]);

include ("Include/eGiveConfig.php"); // Specific account information is in here

$familySelectHtml = buildFamilySelect(0, 0, 0);

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
$rsFunds = RunQuery($sSQL);
mysql_data_seek($rsFunds,0);
while ($row = mysql_fetch_array($rsFunds)) {
	$fun_id = $row["fun_ID"];
	$fun_name = $row["fun_Name"];
} // end while

// get array of all existing payments into a 'cache' so we don't have to keep querying the DB

$sSQL = "SELECT egv_egiveID, egv_famID from egive_egv";
$egiveIDs = RunQuery($sSQL);
while ($row = mysql_fetch_array($egiveIDs)) {
	$egiveID2FamID[$row['egv_egiveID']] = $row['egv_famID'];
}

$sSQL = "SELECT plg_date, plg_amount, plg_fundID, plg_FamID, plg_comment from pledge_plg where plg_method=\"EGIVE\" AND plg_PledgeOrPayment=\"Payment\";";

$rsPlgIDs = RunQuery($sSQL);
while ($row = mysql_fetch_array($rsPlgIDs)) {
	$date = $row["plg_date"];
	$amount = $row["plg_amount"];
	$fundID = $row["plg_fundID"];
	$famID = $row["plg_FamID"];
	$comment = $row["plg_comment"];
	$key = $date . "|" . $fundID . "|" . $comment;
	$eGiveExisting[$key] = $amount;
} // end while



// Set the page title and include HTML header
$sPageTitle = "eGive Import";
require "Include/Header.php";

if (isset($_POST["ApiGet"])) {
	$startDate = $_POST["StartDate"];
	$endDate = $_POST["EndDate"];

	$url = $eGiveURL . "/api/login/?apiKey=" . $eGiveApiKey;
	$fp = fopen($url, 'r');

	//$meta_data = stream_get_meta_data($fp);
	//foreach($meta_data['wrapper_data'] as $response) {
	//}

	$json = stream_get_contents($fp);
	fclose($fp);

	$logon = json_decode($json, true);
	//$status = $logon["status"];
	//$message = $login["message"];

	if ($logon['status'] == 'success') {
 		$token = $logon["token"];

		$url = $eGiveURL . "/api/transactions/" . $eGiveOrgID . "/" . $startDate;
		if ($endDate) {
			$url  .= "/" . $endDate;
		}
		$url .= "/?token=" . $token;

		$fp = fopen($url, 'r');

		$json = stream_get_contents($fp);
		fclose($fp);
		$data = json_decode($json, true);
		if ($data['status'] == 'success') {
		//arrray($giftDataMissingEgiveID);

		// each transaction has these fields: 'transactionID' 'envelopeID' 'giftID' 'frequency' 'amount'
		// 'giverID' 'giverName' 'giverEmail' 'dateCompleted' 'breakouts'
			$importCreated = 0;
			$importUpdated = 0;
			$importNoChange = 0;
			$importError = 0;
			foreach ($data['transactions'] as $trans) {
				$transId = $trans['transactionID'];
				$name = $trans['giverName'];
				$totalAmount = $trans['amount'];
				$breakouts = $trans['breakouts'];
				$dateCompleted = $trans['dateCompleted'];
				$egiveID = $trans['giverID'];
				$frequency = $trans['frequency'];
				$dateTime = explode(' ', $dateCompleted);
				$date = $dateTime[0];
				$famID = 0;

				if ($egiveID2FamID and array_key_exists($egiveID, $egiveID2FamID)) {
					$famID = $egiveID2FamID[$egiveID];
				} else {
					$patterns[0] = '/\s+/'; // any whitespace
					$patterns[1] = '/\./'; // or dots
					$nameWithUnderscores = preg_replace($patterns, '_', $name);
					$egiveID2NameWithUnderscores[$egiveID] = $nameWithUnderscores;

				}
						
				foreach ($breakouts as $breakout) {
					$amount = $breakout[0];
					if ($amount) {
						$totalAmount -= $amount;
						$fundName = $breakout[1];
						if ($famID) {
					   		 updateDB($famID, $transId, $date, $name, $amount, $fundName, $frequency);
						} else {
							$missingValue = $transId . "|" . $date . "|" . $egiveID . "|" . $name . "|" . $amount . "|" . $fundName . "|" . $frequency;
 							$giftDataMissingEgiveID[] = $missingValue; 
							++$importError;
						}
					}
				}
				if ($totalAmount) {
					if ($famID) {
					   	updateDB($famID, $transId, $date, $name, $totalAmount, "unspecified", $frequency);
					} else {
						$missingValue = $transId . "|" . $date . "|" . $egiveID . "|" . $name . "|" . $totalAmount . "|" . "unspecified" . "|" . $frequency;
						$giftDataMissingEgiveID[] = $missingValue;
						++$importError;
					}
				}
			}
		}
	}
	$url = $eGiveURL . "/api/logout/?apiKey=" . $eGiveApiKey;
	$fp = fopen($url, 'r');

	$json = stream_get_contents($fp);
	fclose($fp);

	$logout = json_decode($json, true);

	$_SESSION['giftDataMissingEgiveID'] = $giftDataMissingEgiveID;
	$_SESSION['egiveID2NameWithUnderscores'] = $egiveID2NameWithUnderscores;
	importDoneFixOrContinue();
} elseif (isset($_POST["ReImport"])) {
	$giftDataMissingEgiveID = $_SESSION['giftDataMissingEgiveID'];
	$egiveID2NameWithUnderscores = $_SESSION['egiveID2NameWithUnderscores'];

	$importCreated = 0;
	$importUpdated = 0;
	$importNoChange = 0;
	$importError = 0;
	foreach ($egiveID2NameWithUnderscores as $egiveID => $nameWithUnderscores) {
		$famID = $_POST["MissingEgive_FamID_" . $nameWithUnderscores];
		$doUpdate = $_POST["MissingEgive_Set_" . $nameWithUnderscores];
		if ($famID) {
			if ($doUpdate) {
				$sSQL = "INSERT INTO egive_egv (egv_egiveID, egv_famID, egv_DateEntered, egv_EnteredBy) VALUES ('" . $egiveID . "','" . $famID . "','" . date("YmdHis") . "','" . $_SESSION['iUserID']	. "');";
				RunQuery($sSQL);
			}

			foreach ($giftDataMissingEgiveID as $data) {
				$fields = explode('|', $data);
				if ($fields[2] == $egiveID) {
					$transId = $fields[0];
					$date = $fields[1];
					$name = $fields[3];
					$amount = $fields[4];
					$fundName = $fields[5];
					$frequency = $fields[6];
					updateDB($famID, $transId, $date, $name, $amount, $fundName, $frequency);
				}
			}
		} else {
			++$importError;
		}
	}
	$_SESSION['giftDataMissingEgiveID'] = $giftDataMissingEgiveID;
	$_SESSION['egiveID2NameWithUnderscores'] = $egiveID2NameWithUnderscores;

	importDoneFixOrContinue();
} else  { ?>
	<table cellpadding="3" align="left">
	<tr><td>
		<form method="post" action="eGive.php?DepositSlipID=<?php echo $iDepositSlipID ?>" enctype="multipart/form-data">
		<class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><b><?php echo gettext("Start Date: "); ?></b>
			<class="TextColumn"><input type="text" name="StartDate" value="<?php echo $lwDate; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sDateError ?></font><br>
			<class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><b><?php echo gettext("End Date: "); ?></b>
			<class="TextColumn"><input type="text" name="EndDate" value="<?php echo $dDate; ?>" maxlength="10" id="sel2" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel2', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sDateError ?></font><br><br>
		<input type="submit" class="icButton" value="<?php echo gettext("Import eGive"); ?>" name="ApiGet">
		<br><br><br>
		</form>
		</td>
	</tr>
<?php

}

function updateDB($famID, $transId, $date, $name, $amount, $fundName, $frequency) {
	global $eGiveBreakoutNames2FundIds;
	global $eGiveExisting;
	global $defaultFundId;
	global $iFYID;
	global $iDepositSlipID;
	global $importCreated;
	global $importUpdated;
	global $importNoChange;

	$dateArray = explode('/', $date); // this date is in mm/dd/yy format.  churchinfo needs it in yyyy-mm-dd format
	if (strlen($dateArray[2]) == 2) {
		$dateArray[2] += 2000;
	}
	$dateArray[0] = sprintf ("%02d", $dateArray[0]);
	$dateArray[1] = sprintf ("%02d", $dateArray[1]);
	$dateCI = $dateArray[2] . "-" . $dateArray[0] . "-" . $dateArray[1];


	$foundFundId = '';
	foreach ($eGiveBreakoutNames2FundIds as $matchKey => $fundId) {
		if (preg_match("%$matchKey%i", $fundName)) {
			$foundFundId = $fundId;
			break;
		}
	}		

	// we may not need frequency to make it unique, but its added here to clarify any given gift.  
	// especially the case where someone sets up a one time gift, and on the same date sets up a 
	// recurring gift.  The date and fund name are no longer enough to make the gift unique.
	// within the DB.  So, we added ID to ensure the entry was unique.
	$comment = "egive: " . $frequency . "/" . $transId . "/" . $fundName;

	if ($foundFundId == '') {
		$foundFundId = $defaultFundId;
	}
	$keyExisting = $dateCI . "|" . $foundFundId . "|" . $comment;

	if ($eGiveExisting and array_key_exists($keyExisting, $eGiveExisting)) {
		if ($eGiveExisting[$keyExisting] <> $amount) { // record already exists, just update amount
			$sSQL = "UPDATE pledge_plg SET plg_DateLastEdited='" . date("YmdHis") . "', plg_comment = '" . $comment . "', plg_amount='" . $amount . "' WHERE plg_famID='" . $famID . "' AND plg_date='" . $dateCI . "' AND plg_FundID='" . $foundFundId . "' AND plg_method='EGIVE';";
			++$importUpdated;
			RunQuery($sSQL);
		} else {
			++$importNoChange;
		}
	} elseif ($famID) { //  insert a new record
		$sSQL = "INSERT INTO pledge_plg (plg_famID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_NonDeductible) VALUES ('" . $famID . "','" . $iFYID . "','" . $dateCI . "','" . $amount . "','Once','EGIVE','" . $comment . "','" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",'Payment'," . $foundFundId . ",'" . $iDepositSlipID . "','0')";
		++$importCreated;
		RunQuery($sSQL);
	}
}

function importDoneFixOrContinue() {
	global $importCreated;
	global $importUpdated;
	global $importNoChange;
	global $importError;
	global $iDepositSlipID;
	global $missingEgiveIDCount;
	global $egiveID2NameWithUnderscores;
	global $familySelectHtml;
	
	?>
	<form method="post" action="eGive.php?<?php echo "DepositSlipID=".$iDepositSlipID?>">
	<?php
	if ($importError) { // the only way we can fail to import data is if we're missing the egive IDs, so build a table, with text input, and prompt for it.
        ?>

		<table border=1>
		<tr><td><b>eGive Name</b></td><td><b>eGive ID</b></td><td><b>Family</b></td><td><b>Set eGive ID into Family</b></td></tr>
		<?php

		foreach ($egiveID2NameWithUnderscores as $egiveID => $nameWithUnderscores) {
			$name = preg_replace('/_/', ' ', $nameWithUnderscores);
			echo "<tr>";
			echo "<td>" . $name . "&nbsp;</td>"; ?>
			<td><class="TextColumn"><input type="text" name="MissingEgive_ID_<?php echo $nameWithUnderscores; ?>" value="<?php echo $egiveID; ?>" maxlength="10"></td>
			<td class="TextColumn">
			<select name="MissingEgive_FamID_<?php echo $nameWithUnderscores; ?>">
			<option value="0" selected><?php echo gettext("Unassigned"); ?></option>
			<?php
			echo $familySelectHtml;
			?>
			</select>
			</td>
			<td><input type="checkbox" name="MissingEgive_Set_<?php echo $nameWithUnderscores; ?>" value="1" checked></td>
			<?php 
			echo "</tr>";
		 }
 		?>
		</table><br>

		<input type="submit" class="icButton" value="<?php echo gettext("Re-import to selected family"); ?>" name="ReImport">
	<?php
	}

 ?>

	<p class="MediumLargeText"> <?php echo gettext("Data import results: ") . $importCreated . gettext(" gifts were imported, ") . $importUpdated . gettext(" gifts were updated, ") . $importNoChange . gettext(" gifts unchanged, and ") . $importError . gettext(" gifts not imported due to problems");?></p>
	<input type="button" class="icButton" value="<?php echo gettext("Back to Deposit Slip");?>" onclick="javascript:document.location='DepositSlipEditor.php?DepositSlipID=<?php echo $iDepositSlipID;?>'"
<?php
}

require "Include/Footer.php";

?>