<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled());

$sPageTitle = gettext('Property Delete Confirmation');

// Get the Type and Property
$sType = InputUtils::legacyFilterInput($_GET['Type']);
$iPropertyID = InputUtils::legacyFilterInput($_GET['PropertyID'], 'int');

// Do we have deletion confirmation?
if (isset($_GET['Confirmed'])) {
    PropertyQuery::create()->findOneByProId($iPropertyID)->delete();

    $records = RecordPropertyQuery::create()->findByPropertyId($iPropertyID);
    $records->delete();

    RedirectUtils::redirect('PropertyList.php?Type=' . $sType);
}

// Get the family record in question
$property = PropertyQuery::create()->findOneByProId($iPropertyID);
require_once 'Include/Header.php';

?>

<p>
    <?= gettext('Please confirm deletion of this property') ?>:
</p>

<p class="ShadedBox">
    <?= $property->getProName() ?>
</p>

<p>
    <?= gettext('Deleting this Property will also delete all assignments of this Property to any People, Family, or Group records.') ?>
</p>

<p align="center">
    <a href="PropertyDelete.php?Confirmed=Yes&PropertyID=<?php echo $iPropertyID ?>&Type=<?= $sType ?>"><?= gettext('Yes, delete this record') ?></a> <?= gettext('(this action cannot be undone)') ?>
     |
    <a href="PropertyList.php?Type=<?= $sType ?>"><?= gettext('No, cancel this deletion') ?></a>
</p>

</p>
<?php
require_once 'Include/Footer.php';
