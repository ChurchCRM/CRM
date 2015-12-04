<?php
require "Include/Config.php";
require "Include/Functions.php";

$count = 10;
$response = file_get_contents("http://api.randomuser.me/?results=".$count);
$data=json_decode($response);

$rs = $data->results;

foreach($rs as $index=>$u)
{
	$user=$u->user;
	print "<br>";
	print $index;
	$gender = $user->gender;
	$name = $user->name->title." ". $user->name->first." ". $user->name->last;
	$location = $user->location->street." ". $user->location->city." ". $user->location->state." ". $user->location->zip;
	print $gender."\r\n";
	print $name."\r\n";
	
	
	$sSQL = "INSERT INTO person_per (per_Title, per_FirstName, per_MiddleName, per_LastName, per_Suffix, per_Gender, per_Address1, per_Address2, per_City, per_State, per_Zip, per_Country, per_HomePhone, per_WorkPhone, per_CellPhone, per_Email, per_WorkEmail, per_BirthMonth, per_BirthDay, per_BirthYear, per_Envelope, per_fam_ID, per_fmr_ID, per_MembershipDate, per_cls_ID, per_DateEntered, per_EnteredBy, per_FriendDate, per_Flags ) VALUES ('" . $user->name->title . "','" . $user->name->first . "',NULL,'" . $user->name->last . "','" . $sSuffix . "'," . $iGender . ",'" . $sAddress1 . "','" . $sAddress2 . "','" . $sCity . "','" . $sState . "','" . $sZip . "','" . $sCountry . "','" . $sHomePhone . "','" . $sWorkPhone . "','" . $sCellPhone . "','" . $sEmail . "','" . $sWorkEmail . "'," . $iBirthMonth . "," . $iBirthDay . "," . $iBirthYear . "," . $iEnvelope . "," . $iFamily . "," . $iFamilyRole . ",";
	if ( strlen($dMembershipDate) > 0 )
	$sSQL .= "\"" . $dMembershipDate . "\"";
	else
	$sSQL .= "NULL";
	$sSQL .= "," . $iClassification . ",'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",";

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
	
	#print_r($user->user);
	print "<br>";
}


?>