<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have property and classification editing permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

$sPageTitle = gettext('Property Type Delete Confirmation');

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

require_once 'Include/Header.php';

if (isset($_GET['Warn'])) {
    ?>
    <p class="text-center LargeError">
        <?= '<b>' . gettext('Warning') . ': </b>' . gettext('This property type is still being used by at least one property.') . '<BR>' . gettext('If you delete this type, you will also remove all properties using') . '<BR>' . gettext('it and lose any corresponding property assignments.'); ?>
    </p>
    <?php
} ?>

<p class="text-center MediumLargeText">
    <?= gettext('Please confirm deletion of this Property Type') ?>: <b><?= $prt_Name ?></b>
</p>

<p class="text-center">
    <a href="PropertyTypeDelete.php?Confirmed=Yes&PropertyTypeID=<?php echo $iPropertyTypeID ?>"><?= gettext('Yes, delete this record') ?></a>
    &nbsp;&nbsp;
    <a href="PropertyTypeList.php?Type=<?= $sType ?>"><?= gettext('No, cancel this deletion') ?></a>

</p>
<?php
require_once 'Include/Footer.php';
