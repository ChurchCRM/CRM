<?php

require "Include/Config.php";
require "Include/Functions.php";

require "Include/GeoCoder.php";

require "Include/Header.php";

$sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State, fam_Zip from family_fam";

$rsFamilies = RunQuery ($sSQL);

$myAddressLatLon = new AddressLatLon;

while ($aFam = mysql_fetch_array($rsFamilies)) {
	extract ($aFam);

	$myAddressLatLon->SetAddress ($fam_Address1, $fam_City, $fam_State, $fam_Zip);
	$ret = $myAddressLatLon->Lookup ();
	if ($ret == 0) {
		echo "<p>" . $fam_Name, " Latitude " . $myAddressLatLon->GetLat () . " Longitude " . $myAddressLatLon->GetLon () . "</p>";
		$sSQL = "UPDATE family_fam SET fam_Latitude='" . $myAddressLatLon->GetLat () . "',fam_Longitude='" . $myAddressLatLon->GetLon () . "' WHERE fam_ID=" . $fam_ID;
		RunQuery ($sSQL);
	} else {
		echo "<p>" . $fam_Name . ": " . $myAddressLatLon->GetError () . "</p>";
	}
	flush ();
	ob_flush ();
}

?>
