<?php
require "Include/Config.php";
require "Include/Functions.php";

function getPerson()
{
	global $pseronPointer, $rs;
	$user=$rs[$pseronPointer]->user;
	$pseronPointer += 1;
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


function GenerateFamilies($families,$kidsPerFamily,$kidsdev)
{
	global $rs,$pseronPointer;
	$count = $families * ($kidsPerFamily+2);

	$response = file_get_contents("http://api.randomuser.me/?nat=US&results=".$count);
	$data=json_decode($response);
	$rs = $data->results;

	for ($i=0;$i<$families;$i++)
	{
		$hoh = getPerson();
		$FamilyID = insertFamily($hoh);
		$familyName = $hoh->name->last;
		$hoh->famID = $FamilyID;
		$hoh->per_fmr_id = 1;

		$spouse = getPerson();
		$spouse->name->last = $familyName;
		$spouse->famID = $FamilyID;
		$spouse->per_fmr_id = 2;
		
		insertPerson($hoh);
		insertPerson($spouse);
		
		#$thisFamChildren = stats_rand_gen_normal ($kidsPerFamily, $stddev);
		$thisFamChildren = rand($kidsPerFamily-$kidsdev,$kidsPerFamily+$kidsdev);
		
		for ($y=0;$y<$thisFamChildren;$y++)
		{
			$child = getPerson();
			$child->name->last = $familyName;
			$child->famID = $FamilyID;
			$child->per_fmr_id = 3;
			insertPerson($child);
		}
		
	}
}

function GenerateDeposits($numDeposits,$numPaymentsPerDeposit,$pmtAverage)
{



}

$kidsPerFamily=3;
$kidsdev=3;
$families=30;
$pseronPointer = 0;
$rs = 0;
$numDeposits = 20;
$numPaymentsPerDeposit=12;
$pmtAverage=100;

GenerateFamilies($families,$kidsPerFamily,$kidsdev);
GenerateDeposits($numDeposits,$numPaymentsPerDeposit,$pmtAverage);

Redirect("Menu.php");


?>