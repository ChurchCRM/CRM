<?php
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
		
		
?>