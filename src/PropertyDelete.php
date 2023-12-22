<?php

/*******************************************************************************
 *
 *  filename    : PropertyDelete.php
 *  last change : 2003-01-07
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

if (!AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Set the page title
$sPageTitle = gettext('Property Delete Confirmation');

// Get the Type and Property
$sType = $_GET['Type'];
$iPropertyID = InputUtils::legacyFilterInput($_GET['PropertyID'], 'int');

//Do we have deletion confirmation?
if (isset($_GET['Confirmed'])) {
    $sSQL = 'DELETE FROM property_pro WHERE pro_ID = ' . $iPropertyID;
    RunQuery($sSQL);

    $sSQL = 'DELETE FROM record2property_r2p WHERE r2p_pro_ID = ' . $iPropertyID;
    RunQuery($sSQL);

    RedirectUtils::redirect('PropertyList.php?Type=' . $sType);
}

//Get the family record in question
$sSQL = 'SELECT * FROM property_pro WHERE pro_ID = ' . $iPropertyID;
$rsProperty = RunQuery($sSQL);
extract(mysqli_fetch_array($rsProperty));

require 'Include/Header.php';

?>

<p>
    <?= gettext('Please confirm deletion of this property') ?>:
</p>

<p class="ShadedBox">
    <?= $pro_Name ?>
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

<?php require 'Include/Footer.php' ?>
