<?php
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

//Set the page title
$sPageTitle = gettext('View on Map');

require 'Include/Header.php';

$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');
?>

<div class="callout callout-info">
    <a href="<?= SystemURLs::getRootPath() ?>/UpdateAllLatLon.php" class="btn btn-default"><i class="fa fa-map-marker"></i> </a>
    <?= gettext('Missing Families? Update Family Latitude or Longitude now.') ?>
</div>

<?php if (ChurchMetaData::getChurchLatitude() === '') {
    ?>
    <div class="callout callout-danger">
        <?= gettext('Unable to display map due to missing Church Latitude or Longitude. Please update the church Address in the settings menu.') ?>
    </div>
    <?php
} else {
    if (SystemConfig::getValue('sGoogleMapsRenderKey') === '') {
        ?>
        <div class="callout callout-warning">
          <a href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php"><?= gettext('Google Map API key is not set. The Map will work for smaller set of locations. Please create a Key in the maps sections of the setting menu.') ?></a>
        </div>
        <?php
    }

        $plotFamily = false;
        //Get the details from DB
        $dirRoleHead = SystemConfig::getValue('sDirRoleHead');

    if ($iGroupID > 0) {
        //Get all the members of this group
        $persons = PersonQuery::create()
        ->usePerson2group2roleP2g2rQuery()
        ->filterByGroupId($iGroupID)
        ->endUse()
        ->find();
    } elseif ($iGroupID == 0) {
        // group zero means map the cart
        if (!empty($_SESSION['aPeopleCart'])) {
            $persons = PersonQuery::create()
            ->filterById($_SESSION['aPeopleCart'])
            ->find();
        }
    } else {
        //Map all the families
        $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->filterByLatitude(0, Criteria::NOT_EQUAL)
        ->filterByLongitude(0, Criteria::NOT_EQUAL)
        ->usePersonQuery('per')
        ->filterByFmrId($dirRoleHead)
        ->endUse()
        ->find();
        $plotFamily = true;
    }

        //Markericons list
        $icons = Classification::getAll();

        $markerIcons = explode(',', SystemConfig::getValue('sGMapIcons'));
        array_unshift($markerIcons, 'red-pushpin'); //red-pushpin for unassigned classification?>

    <!--Google Map Scripts -->
    <script
        src="https://maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue('sGoogleMapsRenderKey') ?>">
    </script>

    <div class="card">
        <!-- Google map div -->
        <div id="map" class="map-div"></div>

        <!-- map Desktop legend-->
        <div id="maplegend"><h4><?= gettext('Legend') ?></h4>
            <div class="row legendbox">
                <div class="legenditem" data-classification="0">
                    <img
                        src='https://www.google.com/intl/en_us/mapfiles/ms/micons/<?= $markerIcons[0] ?>.png'/>
                    <input type="checkbox" class="legenditem-checkbox" id="legenditem-0" checked/>
                    <?= gettext('Unassigned') ?>
                </div>
                <?php
                foreach ($icons as $icon) {
                    ?>
                    <div class="legenditem" data-classification="<?= $icon->getOptionId() ?>">
                        <img
                            src='https://www.google.com/intl/en_us/mapfiles/ms/micons/<?= $markerIcons[$icon->getOptionId()] ?>.png'/>
                        <input type="checkbox" class="legenditem-checkbox" id="legenditem-<?= $icon->getOptionId() ?>" checked/>
                        <?= $icon->getOptionName() ?>
                    </div>
                    <?php
                } ?>
            </div>
        </div>

        <!-- map Mobile legend-->
        <div id="maplegend-mobile" class="box visible-xs-block">
            <div class="row legendbox">
                <div class="btn bg-primary col-xs-12"><?= gettext('Legend') ?></div>
            </div>
            <div class="row legendbox">
                <div class="col-xs-6 legenditem" data-classification="0">
                    <img
                        class="legendicon" src='https://www.google.com/intl/en_us/mapfiles/ms/micons/<?= $markerIcons[0] ?>.png'/>
                    <div class="legenditemtext"><?= gettext('Unassigned') ?></div>
                </div>
                <?php
                foreach ($icons as $icon) {
                    ?>
                    <div class="col-xs-6 legenditem" data-classification="<?= $icon->getOptionId() ?>">
                        <img
                            class="legendicon" src='https://www.google.com/intl/en_us/mapfiles/ms/micons/<?= $markerIcons[$icon->getOptionId()] ?>.png'/>
                        <div class="legenditemtext"><?= $icon->getOptionName() ?></div>
                    </div>
                    <?php
                } ?>
            </div>
        </div>
    </div> <!--Box-->

    <script nonce="<?= SystemURLs::getCSPNonce() ?>" >

        $(document).ready(function() {
            $(".legenditem-checkbox").change(function(e){
                var category = $(this).parent().data("classification");
                var checked = $(this).prop("checked");
                window.CRM.map.setClassificationVisible(category,checked);
                //e.preventDefault();
                //return false;
            });
            $(".legenditem").click(function(e) {
                if (e.target.tagName != 'INPUT') {
                    var inp = $(this).find("input");
                    inp.prop("checked",!inp.prop("checked"));
                    inp.trigger("change");
                    return false;
                }
            })
        });

        window.CRM.map = {
            markers:  new Array(),
            setClassificationVisible: function(clsID, visible) {
                for (var i=0; i<window.CRM.map.plotArray.length; i++) {
                    if (window.CRM.map.plotArray[i].Classification == clsID) {
                        window.CRM.map.markers[i].setVisible(visible);
                    }
                }
            }
        };
        var churchloc = {
            lat: <?= ChurchMetaData::getChurchLatitude() ?>,
            lng: <?= ChurchMetaData::getChurchLongitude() ?>};


        var markerIcons = <?= json_encode($markerIcons) ?>;
        var iconsJSON = <?= $icons->toJSON() ?>;
        var icons = iconsJSON.ListOptions;
        var iconBase = 'https://www.google.com/intl/en_us/mapfiles/ms/micons/';

        var map = null;
        var infowindow = new google.maps.InfoWindow({
            maxWidth: 200
        });

        function addMarkerWithInfowindow(map, marker_position, image, title, infowindow_content) {
            //Create marker
            var marker = new google.maps.Marker({
                position: marker_position,
                map: map,
                icon: image,
                title: title
            });
            window.CRM.map.markers.push(marker);

            google.maps.event.addListener(marker, 'click', function () {
                infowindow.setContent(infowindow_content);
                infowindow.open(map, marker);
            });
        }

        function initialize() {
            // init map
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: <?= SystemConfig::getValue("iMapZoom")?>,
                center: churchloc

            });

            //Churchmark
            var churchMark = new google.maps.Marker({
                icon: window.CRM.root + "/skin/icons/church.png",
                position: new google.maps.LatLng(churchloc),
                map: map
            });

            google.maps.event.addListener(map, 'click', function () {
                infowindow.close();
            });

            <?php
            $arr = [];
            $arrPlotItems = [];
            if ($plotFamily) {
                foreach ($families as $family) {
                    if ($family->hasLatitudeAndLongitude()) {
                        //this helps to add head people persons details: otherwise doesn't seems to populate
                        $class = $family->getHeadPeople()[0];
                        $family->getHeadPeople()[0];
                        $photoFileThumb = SystemURLs::getRootPath() . '/api/family/' . $family->getId() . '/photo';
                        $arr['ID'] = $family->getId();
                        $arr['Name'] = $family->getName();
                        $arr['Salutation'] = $family->getSalutation();
                        $arr['Address'] = $family->getAddress();
                        $arr['Thumbnail'] = $photoFileThumb;
                        $arr['Latitude'] = $family->getLatitude();
                        $arr['Longitude'] = $family->getLongitude();
                        $arr['Name'] = $family->getName();
                        $arr['Classification'] = $class->GetClsId();
                        $arrPlotItems[] = $arr;
                    }
                }
            } else {
                //plot Person
                foreach ($persons as $member) {
                    $latLng = $member->getLatLng();
                    $photoFileThumb = SystemURLs::getRootPath() . '/api/person/' . $member->getId() . '/thumbnail';
                    $arr['ID'] = $member->getId();
                    $arr['Salutation'] = $member->getFullName();
                    $arr['Name'] = $member->getFullName();
                    $arr['Address'] = $member->getAddress();
                    $arr['Thumbnail'] = $photoFileThumb;
                    $arr['Latitude'] = $latLng['Latitude'];
                    $arr['Longitude'] = $latLng['Longitude'];
                    $arr['Name'] = $member->getFullName();
                    $arr['Classification'] = $member->getClsId();
                    $arrPlotItems[] = $arr;
                }
            } //end IF $plotFamily

            ?>

            window.CRM.map.plotArray = <?= json_encode($arrPlotItems) ?>;
            var bPlotFamily = <?= ($plotFamily) ? 'true' : 'false' ?>;
            if (window.CRM.map.plotArray.length == 0) {
                return;
            }
            //loop through the families/persons and add markersmarkers
            for (var i = 0; i < window.CRM.map.plotArray.length; i++) {
                if (window.CRM.map.plotArray[i].Latitude + window.CRM.map.plotArray[i].Longitude == 0)
                    continue;

                //icon image
                var clsid = window.CRM.map.plotArray[i].Classification;
                var markerIcon = markerIcons[clsid];
                var iconurl = iconBase + markerIcon + '.png';
                var image = {
                    url: iconurl,
                    // This marker is 37 pixels wide by 34 pixels high.
                    size: new google.maps.Size(37, 34),
                    // The origin for this image is (0, 0).
                    origin: new google.maps.Point(0, 0),
                    // The anchor for this image is the base of the flagpole at (0, 32).
                    anchor: new google.maps.Point(0, 32)
                };

                //Latlng object
                var latlng = new google.maps.LatLng(window.CRM.map.plotArray[i].Latitude, window.CRM.map.plotArray[i].Longitude);

                //Infowindow Content
                var imghref, contentString;
                if (bPlotFamily) {
                    imghref = "v2/family/" + window.CRM.map.plotArray[i].ID;
                } else {
                    imghref = "PersonView.php?PersonID=" + window.CRM.map.plotArray[i].ID;
                }

                contentString = "<b><a href='" + imghref + "'>" + window.CRM.map.plotArray[i].Salutation + "</a></b>";
                contentString += "<p>" + window.CRM.map.plotArray[i].Address + "</p>";
                if (window.CRM.map.plotArray[i].Thumbnail.length > 0) {
                    //contentString += "<div class='image-container'><p class='text-center'><a href='" + imghref + "'>";
                    contentString += "<div class='image-container'><a href='" + imghref + "'>";
                    contentString += "<img class='profile-user-img img-responsive img-circle' border='1' src='" + window.CRM.map.plotArray[i].Thumbnail + "' style='width:" + <?= SystemConfig::getValue('iProfilePictureListSize') ?> + "px; height:" + <?= SystemConfig::getValue('iProfilePictureListSize') ?> + "px'></a></div>" ;
                }

                //Add marker and infowindow
                addMarkerWithInfowindow(map, latlng, image, window.CRM.map.plotArray[i].Name, contentString);
            }

            //push Legend to right bottom
            var legend = document.getElementById('maplegend');
            map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(legend);

        }
        initialize();

    </script>
    <?php
}
require 'Include/Footer.php' ?>
