<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

$sPageTitle = gettext('Property Delete Confirmation');

// Get the Type and Property
$sType = InputUtils::legacyFilterInput($_GET['Type']);
$iPropertyID = InputUtils::legacyFilterInput($_GET['PropertyID'], 'int');

// Get the property record in question
$property = PropertyQuery::create()->findOneByProId($iPropertyID);

// Handle property not found
if ($property === null) {
    RedirectUtils::redirect('PropertyList.php?Type=' . $sType);
}

// Do we have deletion confirmation?
if (isset($_GET['Confirmed'])) {
    $property->delete();

    $records = RecordPropertyQuery::create()->findByPropertyId($iPropertyID);
    $records->delete();

    RedirectUtils::redirect('PropertyList.php?Type=' . $sType);
}

require_once __DIR__ . '/Include/Header.php';

?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0"><?= gettext('Delete Property') ?></h4>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        <?= gettext('Please confirm deletion of this property:') ?>
                    </p>

                    <div class="alert alert-light border border-danger mb-4">
                        <strong><?= InputUtils::escapeHTML($property->getProName()) ?></strong>
                    </div>

                    <div class="alert alert-warning">
                        <strong><?= gettext('Warning:') ?></strong>
                        <?= gettext('Deleting this Property will also delete all assignments of this Property to any People, Family, or Group records. This action cannot be undone.') ?>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="PropertyDelete.php?Confirmed=Yes&PropertyID=<?= $iPropertyID ?>&Type=<?= InputUtils::escapeAttribute($sType) ?>" class="btn btn-danger flex-fill">
                            <i class="fa fa-trash"></i> <?= gettext('Yes, delete this property') ?>
                        </a>
                        <a href="PropertyList.php?Type=<?= InputUtils::escapeAttribute($sType) ?>" class="btn btn-secondary flex-fill">
                            <?= gettext('Cancel') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</p>
<?php
require_once __DIR__ . '/Include/Footer.php';
