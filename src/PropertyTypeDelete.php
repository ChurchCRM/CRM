<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\PropertyTypeQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have property and classification editing permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Property Type');

// Get the PersonID from the querystring
$iPropertyTypeID = InputUtils::legacyFilterInput($_GET['PropertyTypeID'], 'int');

// Do we have deletion confirmation?
if (isset($_GET['Confirmed'])) {
    $iPropertyTypeID = (int) $iPropertyTypeID;

    // Delete record-property mappings for all properties of this type
    $propertyIds = PropertyQuery::create()
        ->filterByProPrtId($iPropertyTypeID)
        ->select(['ProId'])
        ->find()
        ->toArray();
    if (!empty($propertyIds)) {
        RecordPropertyQuery::create()->filterByPropertyId($propertyIds)->delete();
    }

    // Delete properties of this type
    PropertyQuery::create()->filterByProPrtId($iPropertyTypeID)->delete();

    // Delete the property type itself
    PropertyTypeQuery::create()->findPk($iPropertyTypeID)?->delete();

    RedirectUtils::redirect('PropertyTypeList.php');
}

$propertyType = PropertyTypeQuery::create()->findPk((int) $iPropertyTypeID);
$prt_Name = $propertyType?->getPrtName() ?? '';
$sType = '';

require_once __DIR__ . '/Include/Header.php';
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fa-solid fa-trash me-2"></i>
            <?= gettext('Delete Confirmation') ?>
        </h5>
    </div>
    <div class="card-body text-center">
        <?php if (isset($_GET['Warn'])) { ?>
        <div class="alert alert-warning">
            <strong><?= gettext('Warning') ?>:</strong>
            <?= gettext('This property type is still being used by at least one property.') ?>
            <?= gettext('If you delete this type, you will also remove all properties using it and lose any corresponding property assignments.') ?>
        </div>
        <?php } ?>

        <p class="lead"><?= gettext('Please confirm deletion of this Property Type') ?>: <strong><?= InputUtils::escapeHTML($prt_Name) ?></strong></p>

        <div>
            <a href="PropertyTypeDelete.php?Confirmed=Yes&PropertyTypeID=<?= $iPropertyTypeID ?>" class="btn btn-danger"><?= gettext('Yes, delete this record') ?></a>
            <a href="PropertyTypeList.php?Type=<?= $sType ?>" class="btn btn-secondary ms-2"><?= gettext('No, cancel this deletion') ?></a>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
