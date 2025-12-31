<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Property');

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
require_once __DIR__ . '/Include/Header.php';

?>

<div class="container-fluid mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        <?= gettext('Confirm Property Deletion') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <?= gettext('Deleting this Property will also delete all assignments of this Property to any People, Family, or Group records.') ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= gettext('Property to Delete') ?>:</label>
                        <div class="form-control-plaintext font-weight-bold text-danger">
                            <?= InputUtils::escapeHTML($property->getProName()) ?>
                        </div>
                    </div>

                    <div class="d-flex flex-column">
                        <a href="PropertyDelete.php?Confirmed=Yes&PropertyID=<?= InputUtils::escapeAttribute($iPropertyID) ?>&Type=<?= InputUtils::escapeAttribute($sType) ?>" class="btn btn-danger mb-2">
                            <i class="fa-solid fa-trash"></i>
                            <?= gettext('Yes, delete this record') ?>
                        </a>
                        <a href="PropertyList.php?Type=<?= InputUtils::escapeAttribute($sType) ?>" class="btn btn-secondary">
                            <i class="fa-solid fa-ban"></i>
                            <?= gettext('No, cancel this deletion') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/Include/Footer.php';
