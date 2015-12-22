<?php

class FinancialService {
		
	function getMemberByScanString($sstrnig)
	{
		global $bUseScannedChecks;
		if ($bUseScannedChecks) 
		{ 
			require "../Include/MICRFunctions.php";
			$micrObj = new MICRReader(); // Instantiate the MICR class
			$routeAndAccount = $micrObj->FindRouteAndAccount($tScanString); // use routing and account number for matching
			if ($routeAndAccount) {
				$sSQL = "SELECT fam_ID, fam_Name FROM family_fam WHERE fam_scanCheck=\"" . $routeAndAccount . "\"";
				$rsFam = RunQuery($sSQL);
				extract(mysql_fetch_array($rsFam));
				$iCheckNo = $micrObj->FindCheckNo ($tScanString);
				echo '{"ScanString": "'.$tScanString.'" , "RouteAndAccount": "'.$routeAndAccount.'" , "CheckNumber": "'.$iCheckNo.'" ,"fam_ID": "'.$fam_ID.'" , "fam_Name": "'.$fam_Name.'"}';
			}
			else
			{
				echo '{"status":"error in locating family"}';
			}
		}
		else
		{
			echo '{"status":"Scanned Checks is disabled"}';
		}	
	}

	function getDepositsByFamilyID($fid)
	{
		$sSQL = "SELECT plg_fundID, plg_amount from pledge_plg where plg_famID=\"" . $familyId . "\" AND plg_PledgeOrPayment=\"Pledge\"";
			if ($fyid != -1)
			{
				$sSQL .= " AND plg_FYID=\"" . $fyid . "\";";
			}
			echo $sSQL;
			$rsPledge = RunQuery($sSQL);
			$totalPledgeAmount = 0;
			while ($row = mysql_fetch_array($rsPledge)) {
				$fundID = $row["plg_fundID"];
				$plgAmount = $row["plg_amount"];
				$fundID2Pledge[$fundID] = $plgAmount;
				$totalPledgeAmount = $totalPledgeAmount + $plgAmount;
			} 
			if ($fundID2Pledge) {
				// division rounding can cause total of calculations to not equal total.  Keep track of running total, and asssign any rounding error to 'default' fund
				$calcTotal = 0;
				$calcOtherFunds = 0;
				foreach ($fundID2Pledge as $fundID => $plgAmount) {
					$calcAmount = round($iTotalAmount * ($plgAmount / $totalPledgeAmount), 2);

					$nAmount[$fundID] = number_format($calcAmount, 2, ".", "");
					if ($fundID <> $defaultFundID) {
						$calcOtherFunds = $calcOtherFunds + $calcAmount;
					}

					$calcTotal += $calcAmount;
				}
				if ($calcTotal <> $iTotalAmount) {
					$nAmount[$defaultFundID] = number_format($iTotalAmount - $calcOtherFunds, 2, ".", "");
				}
			} else {
				$nAmount[$defaultFundID] = number_format($iTotalAmount, 2, ".", "");
			}
		
		
	}


	function processPayment($payment)
	{
			$sSQL = "INSERT INTO pledge_plg
			(plg_famID,
			plg_FYID, 
			plg_date, 
			plg_amount,
			plg_schedule, 
			plg_method, 
			plg_comment, 
			plg_DateLastEdited, 
			plg_EditedBy, 
			plg_PledgeOrPayment, 
			plg_fundID, 
			plg_depID, 
			plg_CheckNo, 
			plg_scanString, 
			plg_aut_ID, 
			plg_NonDeductible, 
			plg_GroupKey)
			VALUES ('" . 
			$payment->FamilyID . "','" . 
			$payment->FYID . "','" . 
			$payment->Date . "','" .
			$nAmount[$fun_id] . "','" . 
			$iSchedule . "','" . 
			$iMethod  . "','" . 
			$sComment[$fun_id] . "','".
			date("YmdHis") . "'," .
			$_SESSION['iUserID'] . ",'" . 
			$PledgeOrPayment . "'," . 
			$fun_id . "," . 
			$iCurrentDeposit . "," . 
			$iCheckNo . ",'" . 
			$tScanString . "','" . 
			$iAutID  . "','" . 
			$nNonDeductible[$fun_id] . "','" . 
			$sGroupKey . "')";
			echo $sSQL;

		
	}


	function getPerson($rs,&$personPointer)
	{
		$user=$rs[$personPointer]->user;
		$personPointer += 1;
		return $user;
	}

	function sanitize($str)
	{
		return str_replace("'","",$str);
	}

	function insertPerson($user)
	{
		$sSQL = "INSERT INTO person_per 
		(per_Title, 
		per_FirstName, 
		per_MiddleName, 
		per_LastName, 
		per_Suffix, 
		per_Gender, 
		per_Address1, 
		per_Address2, 
		per_City, 
		per_State, 
		per_Zip, 
		per_Country, 
		per_HomePhone, 
		per_WorkPhone, 
		per_CellPhone, 
		per_Email, 
		per_WorkEmail, 
		per_BirthMonth, 
		per_BirthDay, 
		per_BirthYear, 
		per_Envelope, 
		per_fam_ID, 
		per_fmr_ID, 
		per_MembershipDate, 
		per_cls_ID, 
		per_DateEntered, 
		per_EnteredBy, 
		per_FriendDate, 
		per_Flags ) 
		VALUES ('" . 
		sanitize($user->name->title) . "','" . 
		sanitize($user->name->first) . "',NULL,'" . 
		sanitize($user->name->last) . "',NULL,'" . 
		sanitize($user->gender) . "','" . 
		sanitize($user->location->street) . "',NULL,'" . 
		sanitize($user->location->city) . "','" . 
		sanitize($user->location->state) . "','" . 
		sanitize($user->location->zip) . "','USA','" . 
		sanitize($user->phone) . "',NULL,'" . 
		sanitize($user->cell) . "','" . 
		sanitize($user->email) . "',NULL," . 
		date('m', $user->dob) . "," .
		date('d', $user->dob) . "," . 
		date('Y', $user->dob) . ",NULL,'".
		sanitize($user->famID) ."',". 
		sanitize($user->per_fmr_id) .","."\"" . 
		date('Y-m-d', $user->registered) . 
		"\"". ",1,'" . 
		date("YmdHis") . 
		"'," . 
		sanitize($_SESSION['iUserID']) . ",";
		
		if ( strlen($dFriendDate) > 0 )
		$sSQL .= "\"" . $dFriendDate . "\"";
		else
		$sSQL .= "NULL";
		$sSQL .= ", 0" ;
		$sSQL .= ")";
		$bGetKeyBack = True;
		RunQuery($sSQL);
		// If this is a new person, get the key back and insert a blank row into the person_custom table
		if ($bGetKeyBack)
		{
			$sSQL = "SELECT MAX(per_ID) AS iPersonID FROM person_per";
			$rsPersonID = RunQuery($sSQL);
			extract(mysql_fetch_array($rsPersonID));
			$sSQL = "INSERT INTO `person_custom` (`per_ID`) VALUES ('" . $iPersonID . "')";
			RunQuery($sSQL);
		}

	}

	function insertFamily($user)
	{
	$dWeddingDate="NULL";
	$iCanvasser=0;
	$nLatitude=0;
	$nLongitude=0;
	$nEnvelope=0;   
	$sSQL = "INSERT INTO family_fam (
							fam_Name, 
							fam_Address1, 
							fam_Address2, 
							fam_City, 
							fam_State, 
							fam_Zip, 
							fam_Country, 
							fam_HomePhone, 
							fam_WorkPhone, 
							fam_CellPhone, 
							fam_Email, 
							fam_WeddingDate, 
							fam_DateEntered, 
							fam_EnteredBy, 
							fam_SendNewsLetter,
							fam_OkToCanvass,
							fam_Canvasser,
							fam_Latitude,
							fam_Longitude,
							fam_Envelope)
						VALUES ('"							. 
							$user->name->last				. "','" . 
							$user->location->street				. "','" . 
							$sAddress2				. "','" . 
							$user->location->city				. "','" . 
							$user->location->state					. "','" . 
							$user->location->zip					. "','" . 
							$sCountry				. "','" . 
							$sHomePhone				. "','" . 
							$sWorkPhone				. "','" . 
							$sCellPhone				. "','" . 
							$sEmail					. "'," . 
							$dWeddingDate			. ",'" . 
							date("YmdHis")			. "'," . 
							$_SESSION['iUserID']	. "," . 
							"FALSE," . 
							"FALSE,'" .
							$iCanvasser				. "'," .
							$nLatitude				. "," .
							$nLongitude				. "," .
							$nEnvelope              . ")";
					RunQuery($sSQL);
					$sSQL = "SELECT MAX(fam_ID) AS iFamilyID FROM family_fam";
					
				$rsLastEntry = RunQuery($sSQL);
				extract(mysql_fetch_array($rsLastEntry));
				return $iFamilyID;

	}

	function listDeposits($id) {

		$sSQL = "SELECT dep_ID, dep_Date, dep_Comment, dep_Closed, dep_Type FROM deposit_dep";
		if ($id)
		{
				$sSQL.=" WHERE dep_ID = ".$id;
		}
		$rsDep = RunQuery($sSQL);
		$return = array();
		while ($aRow = mysql_fetch_array($rsDep))
		{
			extract ($aRow);
			$values['dep_ID']=$dep_ID;
			$values['dep_Date']=$dep_Date;
			$values['dep_Comment']=$dep_Comment;
			$values['dep_Closed']=$dep_Closed;
			$values['dep_Type']=$dep_Type;
			array_push($return,$values);
		}
		echo '{"deposits": ' . json_encode($return) . '}';
	}

	function listPayments($id) {
		$sSQL = "SELECT * from pledge_plg";
		if ($id)
		{
				$sSQL.=" WHERE plg_plgID = ".$id;
		}
		$rsDep = RunQuery($sSQL);
		$return = array();
		while ($aRow = mysql_fetch_array($rsDep))
		{
			extract ($aRow);
			$values['plg_plgID']=$plg_plgID;
			$values['plg_FamID']=$plg_FamID;
			$values['plg_FYID']=$plg_FYID;
			$values['plg_date']=$plg_date;
			$values['plg_amount']=$plg_amount;
			$values['plg_schedule']=$plg_schedule;
			$values['plg_method']=$plg_method;
			$values['plg_comment']=$plg_comment;
			$values['plg_DateLastEdited']=$plg_DateLastEdited;
			$values['plg_EditedBy']=$plg_EditedBy;
			$values['plg_PledgeOrPayment']=$plg_PledgeOrPayment;
			$values['plg_fundID']=$plg_fundID;
			$values['plg_depID']=$plg_depID;
			$values['plg_CheckNo']=$plg_CheckNo;
			$values['plg_Problem']=$plg_Problem;
			$values['plg_scanString']=$plg_scanString;
			$values['plg_aut_ID']=$plg_aut_ID;
			$values['plg_aut_Cleared']=$plg_aut_Cleared;
			$values['plg_aut_ResultID']=$plg_aut_ResultID;
			$values['plg_NonDeductible']=$plg_NonDeductible;
			$values['plg_GroupKey']=$plg_GroupKey;

			array_push($return,$values);
		}
		echo '{"pledges": ' . json_encode($return) . '}';
		
	}

	function searchMembers($query) {
			$sSearchTerm = $query;
			$sSearchType = "person";
			$fetch = 'SELECT per_ID, per_FirstName, per_LastName, CONCAT_WS(" ",per_FirstName,per_LastName) AS fullname, per_fam_ID  FROM `person_per` WHERE per_FirstName LIKE \'%'.$sSearchTerm.'%\' OR per_LastName LIKE \'%'.$sSearchTerm.'%\' OR per_Email LIKE \'%'.$sSearchTerm.'%\' OR CONCAT_WS(" ",per_FirstName,per_LastName) LIKE \'%'.$sSearchTerm.'%\' LIMIT 15';
			$result=mysql_query($fetch);
			
	}

	function validateDate($date) { 
		// Validate Date
		if (strlen($dDate) > 0) {
			list($iYear, $iMonth, $iDay) = sscanf($dDate,"%04d-%02d-%02d");
			if ( !checkdate($iMonth,$iDay,$iYear) ) {
				$sDateError = "<span style=\"color: red; \">" . gettext("Not a valid Date") . "</span>";
				$bErrorFlag = true;
				echo "bErrorFlag 156";
			}
		}
	
	}
	
	function validateCheck($check) {

		// detect check inconsistencies
		if ($PledgeOrPayment=='Payment' and $iCheckNo) {
			if ($iMethod == "CASH") {
				$sCheckNoError = "<span style=\"color: red; \">" . gettext("Check number not valid for 'CASH' payment") . "</span>";
				$bErrorFlag = true;
				echo "bErrorFlag 139";
			} elseif ($iMethod=='CHECK' and !$sGroupKey) {
				$chkKey = $iFamily . "|" . $iCheckNo;
				if (array_key_exists($chkKey, $checkHash)) {
					$text = "Check number '" . $iCheckNo . "' for selected family already exists.";
					$sCheckNoError = "<span style=\"color: red; \">" . gettext($text) . "</span>";
					$bErrorFlag = true;
					echo "bErrorFlag 146";
				}
			}
		}
		
		
	}
	
	
	function validateFund($fund) {
		
		if (!$iSelectedFund) { // split
			// make sure at least one fund has a non-zero numer
			$nonZeroFundAmountEntered = 0;
			foreach ($fundId2Name as $fun_id => $fun_name) {
				//$fun_active = $fundActive[$fun_id];
				$nAmount[$fun_id] = FilterInput($_POST[$fun_id . "_Amount"]);
				$sComment[$fun_id] = FilterInput($_POST[$fun_id . "_Comment"]);
				if ($nAmount[$fun_id] > 0) {
					++$nonZeroFundAmountEntered;
				}

				if ($bEnableNonDeductible) {
					$nNonDeductible[$fun_id] = FilterInput($_POST[$fun_id . "_NonDeductible"]);
					//Validate the NonDeductible Amount
					if ($nNonDeductible[$fun_id] > $nAmount[$fun_id]) { //Validate the NonDeductible Amount
						$sNonDeductibleError[$fun_id] = gettext("NonDeductible amount can't be greater than total amount.");
					$bErrorFlag = true;
					echo "bErrorFlag 111";
					}
				}
			} // end foreach

			if (!$nonZeroFundAmountEntered) {
				$sAmountError[$fun_id] = gettext("At least one fund must have a non-zero amount.");
				$bErrorFlag = true;
				echo "bErrorFlag 118";
			}
		}
		

	}
	
	
	function submitPledgeOrPayment() {
		
			$iFamily = FilterInput($_POST["FamilyID"],'int');
			
			$dDate = FilterInput($_POST["Date"]);
			if (!$dDate) {
				if (array_key_exists ('idefaultDate', $_SESSION))
					$dDate = $_SESSION['idefaultDate'];
				else
					$dDate = date ("Y-m-d");
			}

			if (isset($_POST["FundSplit"])) {
				$iSelectedFund = FilterInput($_POST["FundSplit"]);
			} 

			// set from drop-down if set, saved session default, or by calcuation
			$iFYID = FilterInput($_POST["FYID"], 'int');
			if (!$iFYID) {
				$iFYID =  $_SESSION['idefaultFY'];
			}
			if (!$iFYID) {
				$iFYID = CurrentFY();
			}
			
			if (array_key_exists ("CheckNo", $_POST))
				$iCheckNo = FilterInput($_POST["CheckNo"], 'int');
			else
				$iCheckNo = 0;
			
			if (array_key_exists ("Schedule", $_POST))
				$iSchedule = FilterInput($_POST["Schedule"]);
			else
				$iSchedule='Once';
			$_SESSION['iDefaultSchedule'] = $iSchedule;
			
			$iMethod = FilterInput($_POST["Method"]);
			
			if (!$iMethod) {
				if ($sGroupKey) 
				{
					$sSQL = "SELECT DISTINCT plg_method FROM pledge_plg WHERE plg_GroupKey='" . $sGroupKey . "'";
					$rsResults = RunQuery($sSQL);
					list($iMethod) = mysql_fetch_row($rsResults);
				} 
				elseif ($iCurrentDeposit) 
				{
					$sSQL = "SELECT plg_method from pledge_plg where plg_depID=\"" . $iCurrentDeposit . "\" ORDER by plg_plgID DESC LIMIT 1";
					$rsMethod = RunQuery($sSQL);
					$num = mysql_num_rows($rsMethod);
					if ($num) 
					{	// set iMethod to last record's setting
						extract(mysql_fetch_array($rsMethod));  
						$iMethod = $plg_method;
					} 
					else 
					{
						$iMethod = "CHECK";
					}
				} 
				else 
				{
					$iMethod = "CHECK";
				}
			}

			$iEnvelope = 0;
			if (array_key_exists ("Envelope", $_POST))
				$iEnvelope = FilterInput($_POST["Envelope"], 'int');
			$iTotalAmount = FilterInput($_POST["TotalAmount"]);
			if (array_key_exists ("OneComment", $_POST))
				$sOneComment = FilterInput($_POST["OneComment"]);
			else
				$sOneComment = "";
			if ($iSelectedFund) {
				$nAmount[$iSelectedFund] = $iTotalAmount;
				$sComment[$iSelectedFund] = $sOneComment;
			}

		//Initialize the error flag
		$bErrorFlag = false;

		validateFund($fund);

		if (array_key_exists ("ScanInput", $_POST))
			$tScanString = FilterInput($_POST["ScanInput"]);
		else
			$tScanString = "";
		$iAutID = 0;
		if (array_key_exists ("AutoPay", $_POST))
			$iAutID = FilterInput($_POST["AutoPay"]);
		//$iEnvelope = FilterInput($_POST["Envelope"], 'int');

		if ($PledgeOrPayment=='Payment' and !$iCheckNo and $iMethod == "CHECK") {
			$sCheckNoError = "<span style=\"color: red; \">" . gettext("Must specify non-zero check number") . "</span>";
			$bErrorFlag = true;
			echo "bErrorFlag 133";
		}

		validateCheck();

		validateDate($date);


		//If no errors, then let's update...
		if (!$bErrorFlag and !$dep_Closed) {
			echo "no errors, update!";
			// Only set PledgeOrPayment when the record is first created
			// loop through all funds and create non-zero amount pledge records
			foreach ($fundId2Name as $fun_id => $fun_name) {
				if (!$iCheckNo) { $iCheckNo = 0; }
				unset($sSQL);
				if ($fund2PlgIds and array_key_exists($fun_id, $fund2PlgIds)) {
					if ($nAmount[$fun_id] > 0) {
						$sSQL = "UPDATE pledge_plg SET plg_famID = '" . $iFamily . "',plg_FYID = '" . $iFYID . "',plg_date = '" . $dDate . "', plg_amount = '" . $nAmount[$fun_id] . "', plg_schedule = '" . $iSchedule . "', plg_method = '" . $iMethod . "', plg_comment = '" . $sComment[$fun_id] . "'";
						$sSQL .= ", plg_DateLastEdited = '" . date("YmdHis") . "', plg_EditedBy = " . $_SESSION['iUserID'] . ", plg_CheckNo = '" . $iCheckNo . "', plg_scanString = '" . $tScanString . "', plg_aut_ID='" . $iAutID . "', plg_NonDeductible='" . $nNonDeductible[$fun_id] . "' WHERE plg_plgID='" . $fund2PlgIds[$fun_id] . "'";
					} else { // delete that record
						$sSQL = "DELETE FROM pledge_plg WHERE plg_plgID =" . $fund2PlgIds[$fun_id];
					}
				} elseif ($nAmount[$fun_id] > 0) {
					if ($iMethod <> "CHECK") {
						$iCheckNo = "NULL";
					}
					if (!$sGroupKey) {
						if ($iMethod == "CHECK") {
							$sGroupKey = genGroupKey($iCheckNo, $iFamily, $fun_id, $dDate);
						} elseif ($iMethod == "BANKDRAFT") {
							if (!$iAutID) {
								$iAutID = "draft";
							}
							$sGroupKey = genGroupKey($iAutID, $iFamily, $fun_id, $dDate);
						} elseif ($iMethod == "CREDITCARD") {
							if (!$iAutID) {
								$iAutID = "credit";
							}
							$sGroupKey = genGroupKey($iAutID, $iFamily, $fun_id, $dDate);
						} else {
							$sGroupKey = genGroupKey("cash", $iFamily, $fun_id, $dDate);
						} 
					}
					$sSQL = "INSERT INTO pledge_plg (plg_famID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_CheckNo, plg_scanString, plg_aut_ID, plg_NonDeductible, plg_GroupKey)
				VALUES ('" . $iFamily . "','" . $iFYID . "','" . $dDate . "','" . $nAmount[$fun_id] . "','" . $iSchedule . "','" . $iMethod  . "','" . $sComment[$fun_id] . "'";
					$sSQL .= ",'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",'" . $PledgeOrPayment . "'," . $fun_id . "," . $iCurrentDeposit . "," . $iCheckNo . ",'" . $tScanString . "','" . $iAutID  . "','" . $nNonDeductible[$fun_id] . "','" . $sGroupKey . "')";
					
					echo "SQL: ".$sSQL;
				}
				if (isset ($sSQL)) {
					RunQuery($sSQL);
					unset($sSQL);
				}
				
			} // end foreach of $fundId2Name
			
			
			//Process the Currency Denominations for this deposit
			if ($iMethod == "CASH"){
				$sGroupKey = genGroupKey("cash", $iFamily, $fun_id, $dDate);
				foreach ($currencyDenomination2Name as $cdem_denominationID =>$cdem_denominationName)
				{
					$sSQL = "INSERT INTO pledge_denominations_pdem (pdem_plg_GroupKey, pdem_denominationID, pdem_denominationQuantity) 
					VALUES ('". $sGroupKey ."','".$cdem_denominationID."','".$_POST['currencyCount-'.$cdem_denominationID]."')";
				
					if (isset ($sSQL)) {
						RunQuery($sSQL);
						unset($sSQL);
					}
					//$currencyDenomination2Name[$cdem_denominationID] = $cdem_denominationName;
					//$currencyDenominationValue[$cdem_denominationID] = $cdem_denominationValue;
				}
			}
			
			
		} // end if !$bErrorFlag
		
	}
}
?>