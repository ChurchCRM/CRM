<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have property and classification editing permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Property Type');

// Get the PersonID from the querystring
$iPropertyTypeID = InputUtils::legacyFilterInput($_GET['PropertyTypeID'], 'int');

// Do we have deletion confirmation?
if (isset($_GET['Confirmed'])) {
    $sSQL = 'DELETE FROM propertytype_prt WHERE prt_ID = ' . $iPropertyTypeID;
    RunQuery($sSQL);

    $sSQL = 'SELECT pro_ID FROM property_pro WHERE pro_prt_ID = ' . $iPropertyTypeID;
    $result = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($result)) {
        $sSQL = 'DELETE FROM record2property_r2p WHERE r2p_pro_ID = ' . $aRow['pro_ID'];
        RunQuery($sSQL);
    }

    $sSQL = 'DELETE FROM property_pro WHERE pro_prt_ID = ' . $iPropertyTypeID;
    RunQuery($sSQL);

    RedirectUtils::redirect('PropertyTypeList.php');
}

$sSQL = 'SELECT * FROM propertytype_prt WHERE prt_ID = ' . $iPropertyTypeID;
$rsProperty = RunQuery($sSQL);
extract(mysqli_fetch_array($rsProperty));
$sType = '';

require_once __DIR__ . '/Include/Header.php';
?>

<div class="card card-body text-center">
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
        <a href="PropertyTypeList.php?Type=<?= $sType ?>" class="btn btn-secondary ml-2"><?= gettext('No, cancel this deletion') ?></a>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
