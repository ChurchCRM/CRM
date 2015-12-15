<?php
require "Include/Config.php";
require "Include/Functions.php";

$kidsPerFamily=3;
$kidsdev=3;
$percentMaleAdult=50;
$percentMaleChild=50;
$families=30;
$pseronPointer = 0;
$rs = 0;

function getPerson()
{
	global $pseronPointer, $rs;
	$user=$rs[$pseronPointer]->user;
	$pseronPointer += 1;
	return $user;
}

function insertPerson($user)
{
	$gender = $user->gender;
	$name = $user->name->title." ". $user->name->first." ". $user->name->last;
	$location = $user->location->street." ". $user->location->city." ". $user->location->state." ". $user->location->zip;
	if ($bHideAge) {
	$per_Flags = 1;
	} else {
	$per_Flags = 0;
	}
	$sSQL = "INSERT INTO person_per (per_Title, per_FirstName, per_MiddleName, per_LastName, per_Suffix, per_Gender, per_Address1, per_Address2, per_City, per_State, per_Zip, per_Country, per_HomePhone, per_WorkPhone, per_CellPhone, per_Email, per_WorkEmail, per_BirthMonth, per_BirthDay, per_BirthYear, per_Envelope, per_fam_ID, per_fmr_ID, per_MembershipDate, per_cls_ID, per_DateEntered, per_EnteredBy, per_FriendDate, per_Flags ) 
	VALUES ('" . $user->name->title . "','" . $user->name->first . "',NULL,'" . $user->name->last . "',NULL,'" . $user->gender . "','" . $user->location->street . "',NULL,'" . $user->location->city . "','" . $user->location->state . "','" . $user->location->zip . "','USA','" . $user->phone . "',NULL,'" . $user->cell . "','" . $user->email . "',NULL," . date('m', $user->dob) . "," . date('d', $user->dob) . "," . date('Y', $user->dob) . ",NULL,'".$user->famID."',0,"."\"" . date('Y-m-d', $user->registered) . "\"". ",1,'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",";
	if ( strlen($dFriendDate) > 0 )
	$sSQL .= "\"" . $dFriendDate . "\"";
	else
	$sSQL .= "NULL";
	$sSQL .= ", " . $per_Flags;
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

function insertFamily($hoh)
{


}



$count = $families * ($kidsPerFamily+2);

$response = file_get_contents("http://api.randomuser.me/?results=".$count);
$data=json_decode($response);
$rs = $data->results;

for ($i=0;$i<$families;$i++)
{
	#$thisFamChildren = stats_rand_gen_normal ($kidsPerFamily, $stddev);
	$thisFamChildren = rand($kidsPerFamily-$kidsdev,$kidsPerFamily+$kidsdev);
	echo $thisFamChildren;
	
	$hoh = getPerson();
	$familyName = $hoh->name->last;
	$familyID = $i;
	$hoh->famID = $familyID;
	
	insertFamily($hoh);
	
	$spouse = getPerson();
	$spouse->name->last = $familyName;
	$spouse->famID = $familyID;
	
	insertPerson($hoh);
	insertPerson($spouse);
	for ($y=0;$y<$thisFamChildren;$y++)
	{
		$child = getPerson();
		$child->name->last = $familyName;
		insertPerson($child);
	}
	
}


?>