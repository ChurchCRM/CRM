<?php
require ("Include/Config.php");
require ("Include/Functions.php");
require ("Include/Header.php");
require ("Include/ReportFunctions.php");

$iGroupID = FilterInput($_GET["GroupID"],'int');

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

   <script type="text/javascript" src="http://maps.googleapis.com/maps/api/js?<?php echo $sGoogleMapKey ? "key=$sGoogleMapKey&" : ""; ?>sensor=false"></script>
   <div>
    <div id="map" style="width: 800px; height: 600px; float:left;"></div>


    <script type="text/javascript">
    //<![CDATA[

    var myOptions = {
       center: new google.maps.LatLng(<?php echo $nChurchLatitude . ", " . $nChurchLongitude; ?>),
       zoom: 12,
       mapTypeId: google.maps.MapTypeId.ROADMAP
    };

    var map = new google.maps.Map(document.getElementById("map"), myOptions);   

    var shadow = new google.maps.MarkerImage('http://google-maps-icons.googlecode.com/files/shadow.png',
                                 new google.maps.Size(51, 37),
                                 null,
                                 new google.maps.Point(18, 37));
	var churchMark = new google.maps.Marker({
                                                icon: "http://google-maps-icons.googlecode.com/files/church2.png",
                                                shadow: shadow,
                                                position: new google.maps.LatLng(<?php echo $nChurchLatitude . ", " . $nChurchLongitude; ?>), 
                                                map: map});
	 
	var churchInfoWin = new google.maps.InfoWindow({content: "<?php echo $sChurchName . "<p>" . $sChurchAddress . "<p>" . $sChurchCity . ", " . $sChurchState . "  " . $sChurchZip;?>"});
	google.maps.event.addListener(churchMark, "click", function() {
                                                  churchInfoWin.open(map,churchMark);
                                    });

<?php
	$appendToQuery = "";
	if ($iGroupID > 0) {
		// If mapping only members of  a group build a condition to add to the query used below
	
		//Get all the members of this group
		$sSQL = "SELECT per_fam_ID FROM person_per, person2group2role_p2g2r WHERE per_ID = p2g2r_per_ID AND p2g2r_grp_ID = " . $iGroupID;
		$rsGroupMembers = RunQuery($sSQL);
		$appendToQuery = " WHERE fam_ID IN (";
		while ($aPerFam = mysql_fetch_array($rsGroupMembers)) {
			extract ($aPerFam);
			$appendToQuery .= $per_fam_ID . ",";
		}
		$appendToQuery = substr($appendToQuery, 0, strlen ($appendToQuery)-1);
		$appendToQuery .= ")";
	} elseif ($iGroupID > -1) {
        // group zero means map the cart
		$sSQL = "SELECT per_fam_ID FROM person_per WHERE per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ")";
		$rsGroupMembers = RunQuery($sSQL);
		$appendToQuery = " WHERE fam_ID IN (";
		while ($aPerFam = mysql_fetch_array($rsGroupMembers)) {
			extract ($aPerFam);
			$appendToQuery .= $per_fam_ID . ",";
		}
		$appendToQuery = substr($appendToQuery, 0, strlen ($appendToQuery)-1);
		$appendToQuery .= ")";        
    }

	$sSQL = "SELECT fam_ID, per_cls_ID, fam_Name, fam_latitude, fam_longitude, fam_Address1, fam_City, fam_State, fam_Zip FROM family_fam LEFT JOIN person_per on family_fam.fam_ID = person_per.per_fam_ID AND per_fmr_ID IN ( $sDirRoleHead )";
	$sSQL .= $appendToQuery;
	$rsFams = RunQuery ($sSQL);
	$markerIcons =  explode ( "," , $sGMapIcons );
	array_unshift($markerIcons, "red-pushpin");

	while ($aFam = mysql_fetch_array($rsFams)) {
		extract ($aFam);
		if ($fam_longitude != 0 && $fam_latitude != 0) {
?>

                     var image = new google.maps.MarkerImage('http://www.google.com/intl/en_us/mapfiles/ms/micons/<?php echo (array_key_exists ($per_cls_ID, $markerIcons) ? $markerIcons[$per_cls_ID] : 0); ?>.png',
                                 new google.maps.Size(32, 32),
                                 new google.maps.Point(0,0),
                                 new google.maps.Point(0, 32));
                     var shadow = new google.maps.MarkerImage('http://maps.google.com/mapfiles/shadow50.png',
                                 new google.maps.Size(37, 34),
                                 new google.maps.Point(0,0),
                                 new google.maps.Point(-4, 34));

			var famMark<?php echo $fam_ID; ?> = new google.maps.Marker({
                                                                    position: new google.maps.LatLng(<?php echo $fam_latitude . ", " . $fam_longitude; ?>),
                                                                    shadow:shadow, 
                                                                    icon: image,
                                                                    map: map
                                                                                   });
			<?php 
				$famDescription = MakeSalutationUtility ($fam_ID);
				$famDescription .= "<p>" . $fam_Address1 . "<p>" . $fam_City . ", " . $fam_State . "  " . $fam_Zip;
			?>
                        var fam<?php echo $fam_ID; ?>InfoWin = new google.maps.InfoWindow({content: "<?php echo $famDescription; ?>"}); 
			google.maps.event.addListener(famMark<?php echo $fam_ID; ?>, "click", function() {
                                                      fam<?php echo $fam_ID; ?>InfoWin.open(map,famMark<?php echo $fam_ID;?>);
                                                     });
<?php
		}

	}
?>

    //]]>
    </script>

<div style="float:left; margin-left: 10px;" id='mapkey'>
<table>
<tr><th colspan='2'>Key:</th></tr>
<?php
	$sSQL = "SELECT lst_OptionID, lst_OptionName from list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
	$rsIcons = RunQuery ($sSQL);
        while ($aIcons = mysql_fetch_array($rsIcons)) {
	    extract ($aIcons);
            ?>
      <tr>
           <td><img style="vertical-align:middle;" src='http://www.google.com/intl/en_us/mapfiles/ms/micons/<?php echo $markerIcons[$lst_OptionID]; ?>.png'/></td>
           <td><?php echo $lst_OptionName; ?></td>
     </tr>
<?php
        }
?>
</table>
</div>
</div>
  </body>
</html>
