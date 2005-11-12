<?php
require ("Include/Config.php");
require ("Include/Functions.php");
require ("Include/Header.php");
require ("Include/ReportFunctions.php");

// Read values from config table into local variables
// **************************************************
$sSQL = "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'";
$rsConfig = mysql_query($sSQL);			// Can't use RunQuery -- not defined yet
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$$cfg_name = $cfg_value;
	}
}

if ($nChurchLatitude == 0 || $nChurchLongitude == 0) {

	require ("Include/GeoCoder.php");
	$myAddressLatLon = new AddressLatLon;

	// Try to look up the church address to center the map.
	$myAddressLatLon->SetAddress ($sChurchAddress, $sChurchCity, $sChurchState, $sChurchZip);
	$ret = $myAddressLatLon->Lookup ();
	if ($ret == 0) {
		$nChurchLatitude = $myAddressLatLon->GetLat ();
		$nChurchLongitude = $myAddressLatLon->GetLon ();

		$sSQL = "UPDATE config_cfg SET cfg_value='" . $nChurchLatitude . "' WHERE cfg_name=\"nChurchLatitude\"";
		RunQuery ($sSQL);
		$sSQL = "UPDATE config_cfg SET cfg_value='" . $nChurchLongitude . "' WHERE cfg_name=\"nChurchLongitude\"";
		RunQuery ($sSQL);
	}
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

  <head>
    <script src="http://maps.google.com/maps?file=api&v=1&key=<?php echo $sGoogleMapKey; ?>" type="text/javascript"></script>

  </head>
  <body>
    <div id="map" style="width: 600px; height: 450px"></div>


    <script type="text/javascript">
    //<![CDATA[
   
    var map = new GMap(document.getElementById("map"));
    map.addControl(new GSmallMapControl());
    map.addControl(new GMapTypeControl());
    map.centerAndZoom(new GPoint(<?php echo $nChurchLongitude . ", " . $nChurchLatitude; ?>), 4);

	var churchPt = new GPoint (<?php echo $nChurchLongitude . ", " . $nChurchLatitude; ?> );
	var churchMark = new GMarker (churchPt);
	<?php 
		$churchDescription = $sChurchName;
		$churchDescription .= "<p>" . $sChurchAddress . "<p>" . $sChurchCity . ", " . $sChurchState . "  " . $sChurchZip;
	?>
	GEvent.addListener(churchMark, "click", function() {churchMark.openInfoWindowHtml("<?php echo $churchDescription; ?>");});
	map.addOverlay (churchMark);

<?php
	$sSQL = "SELECT fam_ID, fam_Name, fam_latitude, fam_longitude, fam_Address1, fam_City, fam_State, fam_Zip FROM family_fam";
	$rsFams = RunQuery ($sSQL);
	while ($aFam = mysql_fetch_array($rsFams)) {
		extract ($aFam);
		if ($fam_longitude != 0 && $fam_latitude != 0) {
?>
			var famPt<?php echo $fam_ID; ?> = new GPoint (<?php echo $fam_longitude . ", " . $fam_latitude; ?> );
			var famMark<?php echo $fam_ID; ?> = new GMarker (famPt<?php echo $fam_ID; ?>);
			<?php 
				$famDescription = MakeSalutationUtility ($fam_ID);
				$famDescription .= "<p>" . $fam_Address1 . "<p>" . $fam_City . ", " . $fam_State . "  " . $fam_Zip;
			?>
			GEvent.addListener(famMark<?php echo $fam_ID; ?>, "click", function() {famMark<?php echo $fam_ID; ?>.openInfoWindowHtml("<?php echo $famDescription; ?>");});

			map.addOverlay (famMark<?php echo $fam_ID; ?>);
<?php
		}

	}
?>

    //]]>
    </script>

  </body>
</html>
