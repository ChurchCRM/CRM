<?php

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

$sPageTitle = gettext('Update Latitude & Longitude');
require_once __DIR__ . '/Include/Header.php';

echo '<div class="card card-body">';

$families = FamilyQuery::create()
    ->filterByAddress1('', Criteria::NOT_EQUAL)
    ->where('(family_fam.fam_Latitude IS NULL OR family_fam.fam_Latitude = 0) OR (family_fam.fam_Longitude IS NULL OR family_fam.fam_Longitude = 0)')
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

require_once __DIR__ . '/Include/Footer.php';
