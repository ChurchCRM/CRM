<?php
require ("Include/Config.php");
require ("Include/Functions.php");
require ("Include/ReportFunctions.php");

//Set the page title
$sPageTitle = gettext("Family View");

require ("Include/Header.php");

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

if ($nChurchLatitude == "" || $nChurchLongitude == "") {

	require ("Include/GeoCoder.php");
	$myAddressLatLon = new AddressLatLon;

	// Try to look up the church address to center the map.
	$myAddressLatLon->SetAddress ($sChurchAddress, $sChurchCity, $sChurchState, $sChurchZip);
	$ret = $myAddressLatLon->Lookup ();
	if ($ret == 0) {
		$nChurchLatitude = $myAddressLatLon->GetLat ();
		$nChurchLongitude = $myAddressLatLon->GetLon ();

		$sSQL = "UPDATE config_cfg SET cfg_value='" . $nChurchLatitude . "' WHERE cfg_name='nChurchLatitude'";
		RunQuery ($sSQL);
		$sSQL = "UPDATE config_cfg SET cfg_value='" . $nChurchLongitude . "' WHERE cfg_name='nChurchLongitude'";
		RunQuery ($sSQL);
	}
}

if ($nChurchLatitude == "") {
?>
  <div class="callout callout-danger">
    <?= gettext("Unable to display map due to missing Church Latitude or Longitude. Please update the church Address in the settings menu.") ?>
  </div>
<?php } else {
  if ($sGoogleMapKey == "") {
?>
    <div class="callout callout-warning">
      <?= gettext("Google Map API key is not set. The Map will work for smaller set of locations. Please create a Key in the maps sections of the setting menu.") ?>
    </div>
<?php }
  ?>

<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=<?= $sGoogleMapKey ?>&sensor=false"></script>

<div class="box box-body">
    <div class="col-lg-12">
        <div id="map" class="col-lg-12" style="height: 400px;"></div>
        <script type="text/javascript">
            var mapOptions = {
               center: new google.maps.LatLng(<?= $nChurchLatitude . ", " . $nChurchLongitude ?>),
               zoom: 4,
               mapTypeId: google.maps.MapTypeId.ROADMAP
            };

            var map = new google.maps.Map(document.getElementById("map"), mapOptions);

            var shadow = new google.maps.MarkerImage('http://google-maps-icons.googlecode.com/files/shadow.png',
                new google.maps.Size(51, 37),
                null,
                new google.maps.Point(18, 37));

            var churchMark = new google.maps.Marker({
                icon: window.CRM.root + "/skin/icons/church.png",
                shadow: shadow,
                position: new google.maps.LatLng(<?= $nChurchLatitude . ", " . $nChurchLongitude ?>),
                map: map});
	 

            var churchInfoWin = new google.maps.InfoWindow({content: "<?= $sChurchName . "<p>" . $sChurchAddress . "<p>" . $sChurchCity . ", " . $sChurchState . "  " . $sChurchZip; ?>"});

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

                                 var image = new google.maps.MarkerImage('http://www.google.com/intl/en_us/mapfiles/ms/micons/<?= (array_key_exists ($per_cls_ID, $markerIcons) ? $markerIcons[$per_cls_ID] : 0) ?>.png',
                                             new google.maps.Size(32, 32),
                                             new google.maps.Point(0,0),
                                             new google.maps.Point(0, 32));
                                 var shadow = new google.maps.MarkerImage('http://maps.google.com/mapfiles/shadow50.png',
                                             new google.maps.Size(37, 34),
                                             new google.maps.Point(0,0),
                                             new google.maps.Point(-4, 34));

                        var famMark<?= $fam_ID ?> = new google.maps.Marker({
                                                                                position: new google.maps.LatLng(<?= $fam_latitude . ", " . $fam_longitude ?>),
                                                                                shadow:shadow,
                                                                                icon: image,
                                                                                map: map
                                                                                               });
                        <?php
                            $famDescription = MakeSalutationUtility ($fam_ID);
                            $famDescription .= "<p>" . $fam_Address1 . "<p>" . $fam_City . ", " . $fam_State . "  " . $fam_Zip;
                        ?>
                                    var fam<?= $fam_ID ?>InfoWin = new google.maps.InfoWindow({content: "<?= $famDescription ?>"});
                        google.maps.event.addListener(famMark<?= $fam_ID ?>, "click", function() {
                                                                  fam<?= $fam_ID ?>InfoWin.open(map,famMark<?php echo $fam_ID;?>);
                                                                 });
            <?php
		}

	}
?>

    //]]>
    </script>

    </div>
    <div id="mapkey" class="col-lg-2 col-md-2 col-sm-2">
        <table>
            <tr>
                <th colspan='2'><?= gettext("Key") ?>:</th>
            </tr>
            <?php
                $sSQL = "SELECT lst_OptionID, lst_OptionName from list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
                $rsIcons = RunQuery ($sSQL);
                    while ($aIcons = mysql_fetch_array($rsIcons)) {
                    extract ($aIcons);
                        ?>
                  <tr>
                       <td><img style="vertical-align:middle;" src='http://www.google.com/intl/en_us/mapfiles/ms/micons/<?= $markerIcons[$lst_OptionID] ?>.png'/></td>
                       <td><?= $lst_OptionName ?></td>
                 </tr>
        <?php
                }
        ?>
        </table>
    </div>
</div>

<?php }

require "Include/Footer.php" ?>
