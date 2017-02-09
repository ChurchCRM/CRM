<?php
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/ReportFunctions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Base\FamilyQuery;
use ChurchCRM\Base\ListOptionQuery;

//Set the page title
$sPageTitle = gettext('Family View');

require 'Include/Header.php';

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

    //Get the details from DB
    if ($iGroupID > 0) {
        //Get all the members of this group
        if (!empty(SystemConfig::getValue('sDirRoleHead'))) {
            $families = FamilyQuery::create()
                ->filterByDateDeactivated(null)
                ->usePersonQuery('per')
                ->usePerson2group2roleP2g2rQuery()
                ->filterByGroupId($iGroupID)
                ->endUse()
                ->filterByFmrId(SystemConfig::getValue('sDirRoleHead'))
                ->endUse()
                ->find();

        }
    } elseif ($iGroupID == 0) {
        // group zero means map the cart
        if (!empty($_SESSION['aPeopleCart']) && !empty(SystemConfig::getValue('sDirRoleHead'))) {
            $families = FamilyQuery::create()
                ->filterByDateDeactivated(null)
                ->usePersonQuery('per')
                ->filterById($_SESSION['aPeopleCart'])
                ->filterByFmrId(SystemConfig::getValue('sDirRoleHead'))
                ->endUse()
                ->find();

        }
    } else {
        //Map all the families
        if (!empty(SystemConfig::getValue('sDirRoleHead'))) {
            $families = FamilyQuery::create()
                ->filterByDateDeactivated(null)
                ->usePersonQuery('per')
                ->filterByFmrId(SystemConfig::getValue('sDirRoleHead'))
                ->endUse()
                ->find();

        }
    }

    //Markericons list
    $icons = ListOptionQuery::create()
        ->filterById(1)
        ->orderByOptionSequence()
        ->find();

    ?>
    <style>
        #maplegend {
            font-family: Arial, sans-serif;
            background: #fff;
            padding: 5px;
            margin: 5px;
            border: 1px solid #000;
            opacity: 0.7;
        }

        #maplegend h4 {
            margin-top: 0;
        }

        #maplegend img {
            vertical-align: middle;
        }

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

        }
    </style>

    <div class="box">
        <div class="col-lg-12">
            <!-- Google map div -->
            <div id="map" style="height: 700px;"></div>
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
                array_unshift($markerIcons, 'red-pushpin');
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
                        position: new google.maps.LatLng(<?= SystemConfig::getValue('nChurchLatitude') . ', ' . SystemConfig::getValue('nChurchLongitude') ?>),
                        map: map
                    });


                    google.maps.event.addListener(map, 'click', function () {
                        infowindow.close();
                    });

                    <?php

                    $arr = array();
                    $arrFamily = array();
                    foreach ($families as $family) {
                        //this helps to add head people persons details: otherwise doesn't seems to populate
                        $family->getHeadPeople()[0];

                        $photoFileThumb = "Images/Family/thumbnails/" . $family->getId() . ".jpg";
                        if (!file_exists($photoFileThumb)) {
                            $photoFileThumb = "Images/Family/family-128.png";
                        }
                        $arr['ID'] = $family->getId();
                        $arr['Salutation'] = MakeSalutationUtility($family->getId());
                        $arr['Address'] = $family->getAddress();
                        $arr['Thumbnail'] = $photoFileThumb;
                        array_push($arrFamily, $arr);
                    }
                    ?>

                    var familyArray = <?= json_encode($arrFamily) ?>;
                    //convert Propel object to JS array
                    var familiesJSON = <?= $families->toJSON() ?>;
                    var families = familiesJSON.Families;

                    //loop through the families and add markers
                    for (var i = 0; i < families.length; i++) {
                        if (families[i].Latitude + families[i].Latitude == 0)
                            continue;

                        //icon image
                        var clsid = families[i].People["0"].ClsId;
                        var markerIcon = markerIcons[clsid];
                        var image = {
                            url: iconBase + markerIcon + '.png',
                            // This marker is 37 pixels wide by 34 pixels high.
                            size: new google.maps.Size(37, 34),
                            // The origin for this image is (0, 0).
                            origin: new google.maps.Point(0, 0),
                            // The anchor for this image is the base of the flagpole at (0, 32).
                            anchor: new google.maps.Point(0, 32)
                        };

                        //Latlng object
                        var latlng = new google.maps.LatLng(families[i].Latitude, families[i].Longitude);

                        //Infowindow Content
                        var contentString = "<b><a href='FamilyView.php?FamilyID=" + families[i].Id + "'>" + familyArray[i].Salutation + "</a></b>";
                        contentString += "<p>" + familyArray[i].Address + "</p>";
                        contentString += "<p style='text-align: center'><a href='FamilyView.php?FamilyID=" + familyArray[i].ID + "'>";
                        contentString += "<img class='img-circle img-responsive profile-user-img' border='1' src='" + familyArray[i].Thumbnail + "'></a></p>";

                        //contentString="Hello";

                        //Add marker and infowindow
                        addMarkerWithInfowindow(map, latlng, image, families[i].Name, contentString);
                    }
                    //Add Legend
                    var legend = document.getElementById('maplegend');
                    var i = 0;
                    for (var i = 0; i < icons.length; i++) {
                        var type = icons[i];
                        var name = type['OptionName'];
                        var icon = iconBase + markerIcons[i + 1] + '.png';
                        var div = document.createElement('div');
                        div.innerHTML = '<img src="' + icon + '"> ' + name;
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
                        <img style="vertical-align:middle;"
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
