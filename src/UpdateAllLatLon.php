<?php
/*******************************************************************************
 *
 *  filename    : UpdateAllLatLon.php
 *  last change : 2013-02-02
 *  website     : http://www.churchcrm.io
 *
 ******************************************************************************/

use ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;

require 'Include/Config.php';
require 'Include/Functions.php';

$sPageTitle = gettext('Update Latitude & Longitude');
require 'Include/Header.php';

echo '<div class="box box-body box-info">';

$families = FamilyQuery::create()
    ->filterByLongitude(array(null,0), Criteria::IN)
    ->_or()
    ->filterByLatitude(array(null,0), Criteria::IN)
    ->limit(250)
    ->find();

echo '<h4>' . gettext('Families without Geo Info') . ": " . $families->count() .'</h4>';

foreach ($families as $family) {
    $family->updateLanLng();
    $sNewLatitude = $family->getLatitude();
    $sNewLongitude = $family->getLongitude();
    if (!empty($sNewLatitude)) {
        echo '<li>' . $fam_Name, ' Latitude ' . $sNewLatitude . ' Longitude ' . $sNewLongitude . '</li>';
    }
}

?>
</div>
<?php $families = FamilyQuery::create()->filterByLongitude(0)->limit(250)->find();
if ($families->count() > 0) {
    ?>
    <div class="box box-warning">
        <div class="box-header">
            <b><?= gettext('No coordinates found') ?></b>
        </div>
        <div class="box-body ">
            <?php

            foreach ($families as $family) {
                echo '<li><a href="'.$family->getViewURI().'">' . $family->getName() . '</a> ' . $family->getAddress() . '</li>';
            } ?>
        </div>
    </div>
    <?php
}

require 'Include/Footer.php'; ?>
