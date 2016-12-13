<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\ListOptionQuery;

$familyService = new FamilyService();
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Verification";
$sRootPath = SystemURLs::getRootPath();

require(SystemURLs::getDocumentRoot(). "/Include/HeaderNotLoggedIn.php");
?>

  <div class="box box-info">
    <div class="panel-body">
      <img class="img-circle center-block pull-right img-responsive" width="200" height="200"
           src="<?= $sRootPath ?>/<?= $familyService->getFamilyPhoto($family->getId()) ?>">
      <h2><a href=""><?= $family->getName() ?></a></h2>
      <div class="text-muted font-bold m-b-xs">
        <i class="fa fa-fw fa-map-marker" title="Home Address"></i><?= $family->getAddress() ?><br/>
        <i class="fa fa-fw fa-phone" title="Home Phone"></i><?= $family->getHomePhone() ?><br/>
        <i class="fa fa-fw fa-envelope" title="Family Email"></i><?= $family->getEmail() ?><br/>
        <i class="fa fa-fw fa-heart" title="Wedding Date"></i><?= $family->getWeddingDate() ?><br/>
        <i class="fa fa-fw fa-newspaper-o" title="Send Newsletter"></i><?= $family->getWeddingDate() ?><br/>
      </div>
    </div>
    <div class="border-right border-left">
      <section id="map">
        <div id="map1" style="height: 200px"></div>
      </section>
    </div>
    <div class="box box-solid">
      <div class="box-header">
        <i class="fa fa-users"></i>
        <h3 class="box-title">Family Member(s)</h3>
      </div>
      <div class="row">
        <?php foreach ($family->getPeopleSorted() as $person) { ?>
          <div class="col-sm-3">
            <div class="box box-primary">
              <div class="box-body box-profile">
                <img class="profile-user-img img-responsive img-circle" src="<?= $person->getPhoto() ?>">

                <h3 class="profile-username text-center"><?= $person->getTitle() ?> <?= $person->getFullName() ?></h3>

                <p class="text-muted text-center"><i
                    class="fa fa-fw fa-<?= ($person->isMale() ? "male" : "female") ?>"></i> <?= $person->getFamilyRoleName() ?>
                </p>

                <ul class="list-group list-group-unbordered">
                  <li class="list-group-item">
                    <i class="fa fa-fw fa-phone" title="Home Phone"></i><?= $person->getHomePhone() ?><br/>
                    <i class="fa fa-fw fa-briefcase" title="Work Phone"></i><?= $person->getWorkPhone() ?><br/>
                    <i class="fa fa-fw fa-mobile" title="Mobile Phone"></i><?= $person->getCellPhone() ?><br/>
                    <i class="fa fa-fw fa-envelope" title="Email"></i><?= $person->getEmail() ?><br/>
                    <i class="fa fa-fw fa-envelope-o" title="Work Email"></i><?= $person->getWorkEmail() ?><br/>
                    <i class="fa fa-fw fa-birthday-cake"
                       title="Birthday"></i><?= $person->getBirthDate()->format("M d Y") ?> <?php if ($person->hideAge()) { ?>
                      <i class="fa fa-fw fa-eye-slash" title="Age Hidden"></i><?php } ?><br/>
                  </li>
                  <li class="list-group-item">
                    <?php $classification = ListOptionQuery::create()->filterById(1)->filterByOptionId($person->getClsId())->findOne()->getOptionName(); ?>
                    <b>Classification:</b> <?= $classification ?>
                  </li>
                  <li class="list-group-item">
                    <h4>Groups</h4>
                    <?php foreach ($person->getPerson2group2roleP2g2rs() as $groupMembership) {
                      $listOption = ListOptionQuery::create()->filterById($groupMembership->getGroup()->getRoleListId())->filterByOptionId($groupMembership->getRoleId())->findOne()->getOptionName();
                      ?>
                      <b><?= $groupMembership->getGroup()->getName() ?></b>: <span
                        class="pull-right"><?= $listOption ?></span>
                    <?php } ?>
                  </li>
                </ul>
              </div>
              <!-- /.box-body -->
            </div>
            <!-- /.box -->
          </div>
        <?php } ?>
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
        styles: [{
          "featureType": "landscape",
          "stylers": [{"saturation": -100}, {"lightness": 65}, {"visibility": "on"}]
        }, {
          "featureType": "poi",
          "stylers": [{"saturation": -100}, {"lightness": 51}, {"visibility": "simplified"}]
        }, {
          "featureType": "road.highway",
          "stylers": [{"saturation": -100}, {"visibility": "simplified"}]
        }, {
          "featureType": "road.arterial",
          "stylers": [{"saturation": -100}, {"lightness": 30}, {"visibility": "on"}]
        }, {
          "featureType": "road.local",
          "stylers": [{"saturation": -100}, {"lightness": 40}, {"visibility": "on"}]
        }, {
          "featureType": "transit",
          "stylers": [{"saturation": -100}, {"visibility": "simplified"}]
        }, {"featureType": "administrative.province", "stylers": [{"visibility": "off"}]}, {
          "featureType": "water",
          "elementType": "labels",
          "stylers": [{"visibility": "on"}, {"lightness": -25}, {"saturation": -100}]
        }, {
          "featureType": "water",
          "elementType": "geometry",
          "stylers": [{"hue": "#ffff00"}, {"lightness": -25}, {"saturation": -97}]
        }]
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
require(SystemURLs::getDocumentRoot(). "/Include/FooterNotLoggedIn.php");
