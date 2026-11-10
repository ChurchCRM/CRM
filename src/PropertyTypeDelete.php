<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\PropertyTypeQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('Property Type Delete Confirmation');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFUtils::verifyRequest($_POST)) {
        http_response_code(403);
        die(gettext('Invalid CSRF token'));
    }
    $iPropertyTypeID = InputUtils::filterInt($_POST['PropertyTypeID'] ?? 0);

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

$iPropertyTypeID = InputUtils::filterInt($_GET['PropertyTypeID'] ?? 0);
$propertyType = PropertyTypeQuery::create()->findPk($iPropertyTypeID);
$prt_Name = $propertyType?->getPrtName() ?? '';

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

        <form method="post" action="PropertyTypeDelete.php">
            <input type="hidden" name="PropertyTypeID" value="<?= $iPropertyTypeID ?>">
            <?= CSRFUtils::getTokenInputField() ?>
            <button type="submit" class="btn btn-danger"><?= gettext('Yes, delete') ?></button>
            <a href="PropertyTypeList.php" class="btn btn-secondary ms-2"><?= gettext('No, cancel this deletion') ?></a>
        </form>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
