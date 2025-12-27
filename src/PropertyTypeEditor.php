<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have property and classification editing permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

// Get the PropertyID
$iPropertyTypeID = 0;
if (array_key_exists('PropertyTypeID', $_GET)) {
    $iPropertyTypeID = InputUtils::legacyFilterInput($_GET['PropertyTypeID'], 'int');
}

$sPageTitle = $iPropertyTypeID > 0 ? gettext('Edit Property Type') : gettext('Add Property Type');

$sClass = '';
$sNameError = '';
$bError = false;

if (isset($_POST['Submit'])) {
    $sName = InputUtils::sanitizeText($_POST['Name']);
    $sDescription = InputUtils::sanitizeText($_POST['Description']);
    $sClass = InputUtils::legacyFilterInput($_POST['Class'], 'char', 1);

    // Did they enter a name?
    if (strlen($sName) < 1) {
        $sNameError = gettext('You must enter a name');
        $bError = true;
    }

    // If no errors, let's update
    if (!$bError) {
        // Vary the SQL depending on if we're adding or editing
        if ($iPropertyTypeID == 0) {
            $sSQL = "INSERT INTO propertytype_prt (prt_Class,prt_Name,prt_Description) VALUES ('" . $sClass . "','" . $sName . "','" . $sDescription . "')";
        } else {
            $sSQL = "UPDATE propertytype_prt SET prt_Class = '" . $sClass . "', prt_Name = '" . $sName . "', prt_Description = '" . $sDescription . "' WHERE prt_ID = " . $iPropertyTypeID;
        }

        // Execute the SQL
        RunQuery($sSQL);

        // Route back to the list
        RedirectUtils::redirect('PropertyTypeList.php');
    }
} elseif ($iPropertyTypeID > 0) {
    // Get the data on this property
    $sSQL = 'SELECT * FROM propertytype_prt WHERE prt_ID = ' . $iPropertyTypeID;
    $rsProperty = mysqli_fetch_array(RunQuery($sSQL));
    extract($rsProperty);

    // Assign values locally
    $sName = $prt_Name;
    $sDescription = $prt_Description;
    $sClass = $prt_Class;
} else {
    $sName = '';
    $sDescription = '';
    $sClass = '';
}

require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fa-solid <?= $iPropertyTypeID > 0 ? 'fa-edit' : 'fa-plus' ?>"></i>
            <?= $sPageTitle ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if ($bError): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <strong><?= gettext('Error') ?>:</strong> <?= gettext('Please correct the errors below.') ?>
        </div>
        <?php endif; ?>

        <form method="post" action="PropertyTypeEditor.php?PropertyTypeID=<?= $iPropertyTypeID ?>">
            <div class="row">
                <div class="col-md-6">
                    <!-- Class Selection -->
                    <div class="form-group">
                        <label for="class">
                            <?= gettext('Class') ?>
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" id="class" name="Class" required>
                            <option value="p" <?= ($sClass == 'p' ? 'selected' : '') ?>>
                                <i class="fa-solid fa-user"></i> <?= gettext('Person') ?>
                            </option>
                            <option value="f" <?= ($sClass == 'f' ? 'selected' : '') ?>>
                                <i class="fa-solid fa-users"></i> <?= gettext('Family') ?>
                            </option>
                            <option value="g" <?= ($sClass == 'g' ? 'selected' : '') ?>>
                                <i class="fa-solid fa-user-group"></i> <?= gettext('Group') ?>
                            </option>
                        </select>
                        <small class="form-text text-muted">
                            <?= gettext('Select the type of record this property applies to') ?>
                        </small>
                    </div>

                    <!-- Name Field -->
                    <div class="form-group">
                        <label for="name">
                            <?= gettext('Name') ?>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control <?= $sNameError ? 'is-invalid' : '' ?>" 
                               id="name" 
                               name="Name"
                               value="<?= InputUtils::escapeAttribute($sName) ?>" 
                               maxlength="100"
                               required>
                        <?php if ($sNameError): ?>
                        <div class="invalid-feedback d-block">
                            <?= $sNameError ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- Description Field -->
                    <div class="form-group">
                        <label for="description"><?= gettext('Description') ?></label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="Description"
                                  rows="8"
                                  maxlength="255"><?= InputUtils::escapeHTML($sDescription) ?></textarea>
                        <small class="form-text text-muted">
                            <?= gettext('Optional detailed description of this property type') ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="border-top pt-3 mt-3">
                <div class="d-flex justify-content-between">
                    <a href="PropertyTypeList.php" class="btn btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        <?= gettext('Cancel') ?>
                    </a>
                    <button type="submit" class="btn btn-primary" name="Submit">
                        <i class="fa-solid fa-save"></i>
                        <?= gettext('Save') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/Include/Footer.php';
