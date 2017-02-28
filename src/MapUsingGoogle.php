<?php
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/ReportFunctions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Base\FamilyQuery;
use ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\Service\FamilyService;

$familyService = new FamilyService();

//Set the page title
$sPageTitle = gettext('View on Map');

require 'Include/Header.php'; ?>

<style>
    @media screen
    and (max-device-width: 600px) {
        #maplegend {
            visibility: hidden;
        }
    }

    @media screen
    and (min-device-width: 599px) {
        #maplegend {
            display: block;
        }
    }

    @media screen
    and (max-device-width: 600px) {
        #maplegend-mobile {
            display: block;
        }
    }

    @media screen
    and (min-device-width: 599px) {
        #maplegend-mobile {
            visibility: hidden;
        }
    }
</style>

<?php
$iGroupID = FilterInput($_GET['GroupID'], 'int');

//update SystemConfig with coordinates if nChurchLatitude or nChurchLongitude is not set by using address lookup
if (SystemConfig::getValue('nChurchLatitude') == '' || SystemConfig::getValue('nChurchLongitude') == '') {
    require 'Include/GeoCoder.php';
    $myAddressLatLon = new AddressLatLon();

    // Try to look up the church address to center the map.
    $myAddressLatLon->SetAddress(SystemConfig::getValue('sChurchAddress'), SystemConfig::getValue('sChurchCity'), SystemConfig::getValue('sChurchState'), SystemConfig::getValue('sChurchZip'));
    $ret = $myAddressLatLon->Lookup();
    if ($ret == 0) {
        $nChurchLatitude = $myAddressLatLon->GetLat();
        $nChurchLongitude = $myAddressLatLon->GetLon();

        SystemConfig::setValue('nChurchLatitude', $nChurchLatitude);
        SystemConfig::setValue('nChurchLongitude', $nChurchLongitude);
    }
} //end update systemConfig

if (SystemConfig::getValue('nChurchLatitude') == '') {
    ?>
    <div class="callout callout-danger">
        <?= gettext('Unable to display map due to missing Church Latitude or Longitude. Please update the church Address in the settings menu.') ?>
    </div>
    <?php
} else {
    if (SystemConfig::getValue('sGoogleMapKey') == '') {
        ?>
        <div class="callout callout-warning">
            <?= gettext('Google Map API key is not set. The Map will work for smaller set of locations. Please create a Key in the maps sections of the setting menu.') ?>
        </div>
        <?php

    }

    $plotFamily = false;
    //Get the details from DB
    $dirRoleHead = SystemConfig::getValue('sDirRoleHead');
    if (empty($dirRoleHead)) {
        $dirRoleHead = 1;
    }

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
            ->usePersonQuery('per')
            ->filterByFmrId($dirRoleHead)
            ->endUse()
            ->find();
        $plotFamily = true;

    }

    //Markericons list
    $icons = ListOptionQuery::create()
        ->filterById(1)
        ->orderByOptionSequence()
        ->find();

    ?>

    <div class="box">
        <div class="col-lg-12">
            <!-- Google map div -->
            <div id="map" class="map-div" ></div>
            <!-- map legend, only Show for persons plot -->
            <div id="maplegend"><h4>Legend</h4></div>

            <!--Google Map Scripts -->
            <script
                src="https://maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue('sGoogleMapKey') ?>">
            </script>

            <script type="text/javascript">
                var churchloc = {
                    lat: <?= SystemConfig::getValue('nChurchLatitude') ?>,
                    lng: <?= SystemConfig::getValue('nChurchLongitude') ?>};

                <?php
                $markerIcons = explode(',', SystemConfig::getValue('sGMapIcons'));
                array_unshift($markerIcons, 'red-pushpin'); //red-pushpin for unassigned classification
                ?>

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

                    google.maps.event.addListener(marker, 'click', function () {
                        infowindow.setContent(infowindow_content);
                        infowindow.open(map, marker);
                    });
                }

                function initialize() {
                    // init map
                    map = new google.maps.Map(document.getElementById('map'), {
                        zoom: 10,
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
                    $arr = array();
                    $arrPlotItems = array();
                    if ($plotFamily) {
                        foreach ($families as $family) {
                            //this helps to add head people persons details: otherwise doesn't seems to populate
                            $class = $family->getHeadPeople()[0];
                            $family->getHeadPeople()[0];
                            //echo $class['per_cls_Id'];
                            $photoFileThumb = $familyService->getFamilyPhoto($family->getId());
                            $arr['ID'] = $family->getId();
                            $arr['Salutation'] = MakeSalutationUtility($family->getId());
                            $arr['Address'] = $family->getAddress();
                            $arr['Thumbnail'] = $photoFileThumb;
                            $arr['Latitude'] = $family->getLatitude();
                            $arr['Longitude'] = $family->getLongitude();
                            $arr['Name'] = $family->getName();
                            $arr['Classification'] = $class->GetClsId();
                            array_push($arrPlotItems, $arr);
                        }
                    } else {
                        //plot Person
                        foreach ($persons as $member) {
                            $photoFileThumb = '/api/persons/' . $member->getId() . '/photo';
                            $arr['ID'] = $member->getId();
                            $arr['Salutation'] = $member->getFullName();
                            $arr['Address'] = $member->getAddress();
                            $arr['Thumbnail'] = $photoFileThumb;
                            $arr['Latitude'] = $member->getLatitude();
                            $arr['Longitude'] = $member->getLongitude();
                            $arr['Name'] = $member->getFullName();
                            $arr['Classification'] = $member->getClsId();
                            array_push($arrPlotItems, $arr);
                        }
                    } //end IF $plotFamily

                    ?>

                    var plotArray = <?= json_encode($arrPlotItems) ?>;
                    var bPlotFamily = <?= ($plotFamily) ? 'true' : 'false' ?>;
                    if (plotArray.length == 0) {
                        return;
                    }
                    //loop through the families/persons and add markers
                    for (var i = 0; i < plotArray.length; i++) {
                        if (plotArray[i].Latitude + plotArray[i].Longitude == 0)
                            continue;

                        //icon image
                        var clsid = plotArray[i].Classification;
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
                        var latlng = new google.maps.LatLng(plotArray[i].Latitude, plotArray[i].Longitude);

                        //Infowindow Content
                        var imghref, contentString;
                        if (bPlotFamily) {
                            imghref = "FamilyView.php?FamilyID=" + plotArray[i].ID;
                        } else {
                            imghref = "PersonView.php?PersonID=" + plotArray[i].ID;
                        }

                        contentString = "<b><a href='" + imghref + "'>" + plotArray[i].Salutation + "</a></b>";
                        contentString += "<p>" + plotArray[i].Address + "</p>";
                        if (plotArray[i].Thumbnail.length > 0) {
                            contentString += "<p style='text-align: center'><a href='" + imghref + "'>";
                            contentString += "<img class='img-circle img-responsive profile-user-img' border='1' src='" + plotArray[i].Thumbnail + "'></a></p>";
                        }

                        //Add marker and infowindow
                        addMarkerWithInfowindow(map, latlng, image, plotArray[i].Name, contentString);
                    }
                    //Add Legend for person

                    var legend = document.getElementById('maplegend');
                    for (var j = 0; j <= icons.length; j++) {
                        var type, name, icon, div;
                        if (j == 0) {
                            name = 'Unassigned';
                            icon = iconBase + markerIcons[0] + '.png';
                        } else {
                            type = icons[j - 1];
                            name = type['OptionName'];
                            icon = iconBase + markerIcons[type['OptionId']] + '.png';
                        }
                        div = document.createElement('div');
                        div.innerHTML = '<img src="' + icon + '">' + name;
                        legend.appendChild(div);
                    }
                    map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(legend);

                }

                initialize();

            </script>
        </div>
        <br>
        <div id="maplegend-mobile">
            <label><b>Key:</b></label>
            <div class="row">
                <?php
                $i = 0;
                foreach ($icons as $icon) {

                    ?>
                    <div class="col-xs-6 col-sm-4 col-md-3">
                        <img
                            src='http://www.google.com/intl/en_us/mapfiles/ms/micons/<?= $markerIcons[$icon->getOptionId()] ?>.png'/>
                        <?= $icon->getOptionName() ?>
                    </div>
                    <?php
                    $i += 1;

                }
                ?>

            </div>
        </div>
    </div>
    <?php

}

require 'Include/Footer.php' ?>
