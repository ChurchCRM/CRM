<?php

/*******************************************************************************
 *
 *  filename    : PropertyTypeEditor.php
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

// Security: User must have property and classification editing permission
if (!AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Set the page title
$sPageTitle = gettext('Property Type Editor');

//Get the PropertyID
$iPropertyTypeID = 0;
if (array_key_exists('PropertyTypeID', $_GET)) {
    $iPropertyTypeID = InputUtils::legacyFilterInput($_GET['PropertyTypeID'], 'int');
}

$sClass = '';
$sNameError = '';
$bError = false;

//Was the form submitted?
if (isset($_POST['Submit'])) {
    $sName = InputUtils::legacyFilterInput($_POST['Name']);
    $sDescription = InputUtils::legacyFilterInput($_POST['Description']);
    $sClass = InputUtils::legacyFilterInput($_POST['Class'], 'char', 1);

    //Did they enter a name?
    if (strlen($sName) < 1) {
        $sNameError = '<span style="color: red;">' . gettext('You must enter a name') . '</span>';
        $bError = true;
    }

    //If no errors, let's update
    if (!$bError) {
        //Vary the SQL depending on if we're adding or editing
        if ($iPropertyTypeID == '') {
            $sSQL = "INSERT INTO propertytype_prt (prt_Class,prt_Name,prt_Description) VALUES ('" . $sClass . "','" . $sName . "','" . $sDescription . "')";
        } else {
            $sSQL = "UPDATE propertytype_prt SET prt_Class = '" . $sClass . "', prt_Name = '" . $sName . "', prt_Description = '" . $sDescription . "' WHERE prt_ID = " . $iPropertyTypeID;
        }

        //Execute the SQL
        RunQuery($sSQL);

        //Route back to the list
        RedirectUtils::redirect('PropertyTypeList.php');
    }
} elseif ($iPropertyTypeID > 0) {
    //Get the data on this property
    $sSQL = 'SELECT * FROM propertytype_prt WHERE prt_ID = ' . $iPropertyTypeID;
    $rsProperty = mysqli_fetch_array(RunQuery($sSQL));
    extract($rsProperty);

    //Assign values locally
    $sName = $prt_Name;
    $sDescription = $prt_Description;
    $sClass = $prt_Class;
} else {
    $sName = '';
    $sDescription = '';
    $sClass = '';
}

require 'Include/Header.php';

?>
<div class="card card-body">
<form class="form-horizontal" method="post" action="PropertyTypeEditor.php?PropertyTypeID=<?= $iPropertyTypeID ?>">
    <div class="form-group">
        <label class="control-label col-sm-2" for="class"><?= gettext('Class') ?>:</label>
        <div class="col-sm-4">
            <select class="form-control" id="class" name="Class">
                <option value="p" <?= ($sClass == 'p' ? 'selected' : '') ?>><?= gettext('Person') ?></option>
                <option value="f" <?= ($sClass == 'f' ? 'selected' : '') ?>><?= gettext('Family') ?></option>
                <option value="g" <?= ($sClass == 'g' ? 'selected' : '') ?>><?= gettext('Group') ?></option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-sm-2" for="name"><?= gettext('Name') ?>:</label>
        <div class="col-sm-4">
            <input type="text" class="form-control" id="name" name="Name"
                   value="<?= htmlentities(stripslashes($sName), ENT_NOQUOTES, 'UTF-8') ?>" size="40"><?= $sNameError ?>
        </div>
    </div>
    <br>
    <div class="form-group">
        <label class="control-label col-sm-2" for="description"><?= gettext('Description') ?>:</label>
        <div class="col-sm-4">
            <textarea class="form-control" id="description" name="Description"
                      rows="10"><?= htmlentities(stripslashes($sDescription), ENT_NOQUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-8">
            <button type="submit" class="btn btn-primary" name="Submit"><?= gettext('Save') ?></button>
            <button type="button" class="btn btn-default" name="Cancel"
                    onclick="document.location='PropertyTypeList.php';"><?= gettext('Cancel') ?></button>
        </div>

    </div>
</form>
</div>

<?php require 'Include/Footer.php' ?>
