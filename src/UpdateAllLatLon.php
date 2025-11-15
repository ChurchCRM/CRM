<?php

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

$sPageTitle = gettext('Update Latitude & Longitude');
require_once 'Include/Header.php';

echo '<div class="card card-body">';

$families = FamilyQuery::create()
    ->filterByLongitude([null, 0], Criteria::IN)
    ->_or()
    ->filterByLatitude([null, 0], Criteria::IN)
    ->limit(250)
    ->find();

echo '<h4>' . gettext('Families without Geo Info') . ": " . $families->count() . '</h4>';

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
    <div class="card card-warning">
        <div class="card-header">
            <b><?= gettext('No coordinates found') ?></b>
        </div>
        <div class="card-body ">
            <?php

            foreach ($families as $family) {
                echo '<li><a href="' . $family->getViewURI() . '">' . $family->getName() . '</a> ' . $family->getAddress() . '</li>';
            } ?>
        </div>
    </div>
<?php
}

require_once 'Include/Footer.php';
