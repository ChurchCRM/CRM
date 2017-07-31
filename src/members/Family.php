<?php

/*******************************************************************************
 *
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2017
 *
 ******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\FamilyQuery;

$family = NULL;
//Get the FamilyID out of the querystring
if (!empty($_GET['id'])) {
    $familyId = InputUtils::FilterInt($_GET['id'], 'int');
    $family = FamilyQuery::create()->findPk($familyId);
}

if (empty($family)) {
    Redirect('members/404.php?type=Person');
    exit;
}

//Set the page title
$sPageTitle = gettext("Family View") . " - " . $family->getName();
require '../Include/Header.php';

?>

<?php if (!$family->isActive()) { ?>
    <div class="alert alert-warning">
        <strong><?= gettext(" This Family is Deactivated") ?> </strong>
    </div>
    <?php
} ?>

<?php if (!empty($family->getLatitude())) { ?>
<div class="border-right border-left">
    <section id="map">
        <div id="map1"></div>
    </section>
</div>

<!-- Map Scripts -->
<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue("sGoogleMapKey") ?>&sensor=false"></script>
<script>
    var LatLng = new google.maps.LatLng(<?= $family->getLatitude() ?>, <?= $family->getLongitude() ?>)
</script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Map.js"></script>
<?php } ?>

<script src="<?= SystemURLs::getRootPath(); ?>/skin/randomcolor/randomColor.js"></script>
<script src="<?= SystemURLs::getRootPath(); ?>/skin/js/initial.js"></script>



<style>
    #map1 {
        height: 200px;
    }
</style>

<?php



require '../Include/Footer.php';
?>
