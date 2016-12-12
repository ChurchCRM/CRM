<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FamilyService;

$familyService = new FamilyService();
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Verification";
$sRootPath = SystemURLs::getRootPath();
require(__DIR__ . "/../../../Include/HeaderNotLoggedIn.php");
?>

  <div class="box box-info">
    <div class="panel-body">
      <img alt="" class="img-circle m-b m-t-md pull-right" src="<?= $sRootPath?>/<?= $familyService->getFamilyPhoto($family->getId()) ?>">
      <h3><a href=""><?= $family->getName() ?></a></h3>
      <div class="text-muted font-bold m-b-xs">
        <?= $family->getAddress1() ?>, <?= $family->getAddress2() ?><br/>
        <?= $family->getCity() ?>, <?= $family->getState() ?> <?= $family->getZip() ?><br/>
        <?= $family->getCountry() ?></div>
    </div>
    <div class="border-right border-left">
      <section id="map">
        <div id="map1" style="height: 200px"></div>
      </section>
    </div>
    <div class="panel-footer contact-footer">
      <div class="row">
        <div class="col-md-4 border-right">
          <div class="contact-stat"><span>Projects: </span> <strong>200</strong></div>
        </div>
        <div class="col-md-4 border-right">
          <div class="contact-stat"><span>Messages: </span> <strong>300</strong></div>
        </div>
        <div class="col-md-4">
          <div class="contact-stat"><span>Views: </span> <strong>400</strong></div>
        </div>
      </div>
    </div>
  </div>

  <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js"></script>

  <script>

    // When the window has finished loading google map
    google.maps.event.addDomListener(window, 'load', init);

    function init() {
      // Options for Google map
      // More info see: https://developers.google.com/maps/documentation/javascript/reference#MapOptions
      var LatLng = new google.maps.LatLng(<?= $family->getLatitude() ?>, <?= $family->getLongitude() ?>)
      var mapOptions1 = {
        zoom: 14,
        center: LatLng,
        scrollwheel: false,
        disableDefaultUI: true,
        draggable: false,
        // Style for Google Maps
        styles: [{"featureType":"landscape","stylers":[{"saturation":-100},{"lightness":65},{"visibility":"on"}]},{"featureType":"poi","stylers":[{"saturation":-100},{"lightness":51},{"visibility":"simplified"}]},{"featureType":"road.highway","stylers":[{"saturation":-100},{"visibility":"simplified"}]},{"featureType":"road.arterial","stylers":[{"saturation":-100},{"lightness":30},{"visibility":"on"}]},{"featureType":"road.local","stylers":[{"saturation":-100},{"lightness":40},{"visibility":"on"}]},{"featureType":"transit","stylers":[{"saturation":-100},{"visibility":"simplified"}]},{"featureType":"administrative.province","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"labels","stylers":[{"visibility":"on"},{"lightness":-25},{"saturation":-100}]},{"featureType":"water","elementType":"geometry","stylers":[{"hue":"#ffff00"},{"lightness":-25},{"saturation":-97}]}]
      };

      // Get all html elements for map
      var mapElement1 = document.getElementById('map1');

      // Create the Google Map using elements
      var map1 = new google.maps.Map(mapElement1, mapOptions1);

      marker = new google.maps.Marker({position: LatLng, map: map1});
    }

  </script>

<?php
// Add the page footer
require(__DIR__ . "/../../../Include/FooterNotLoggedIn.php");
