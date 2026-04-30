<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PropertyType;
use ChurchCRM\model\ChurchCRM\PropertyTypeQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security: User must have property and classification editing permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

// Get the PropertyID
$iPropertyTypeID = 0;
if (array_key_exists('PropertyTypeID', $_GET)) {
    $iPropertyTypeID = InputUtils::legacyFilterInput($_GET['PropertyTypeID'], 'int');
}

$sPageTitle = $iPropertyTypeID > 0 ? gettext('Edit Property Type') : gettext('Add Property Type');
$sPageSubtitle = gettext('Create or edit property type categories');

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
        if ($iPropertyTypeID === 0) {
            $propertyType = new PropertyType();
        } else {
            $propertyType = PropertyTypeQuery::create()->findOneByPrtId($iPropertyTypeID);
            if ($propertyType === null) {
                RedirectUtils::redirect('PropertyTypeList.php');
            }
        }

        $propertyType->setPrtClass($sClass);
        $propertyType->setPrtName($sName);
        $propertyType->setPrtDescription($sDescription);
        $propertyType->save();

        // Route back to the list
        RedirectUtils::redirect('PropertyTypeList.php');
    }
} elseif ($iPropertyTypeID > 0) {
    // Get the data on this property
    $propertyType = PropertyTypeQuery::create()->findOneByPrtId($iPropertyTypeID);
    if ($propertyType === null) {
        RedirectUtils::redirect('PropertyTypeList.php');
    }

    // Assign values locally
    $sName = $propertyType->getPrtName();
    $sDescription = $propertyType->getPrtDescription();
    $sClass = $propertyType->getPrtClass();
} else {
    $sName = '';
    $sDescription = '';
    $sClass = '';
}

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Admin'), '/admin/'],
    [gettext('Property Types'), '/PropertyTypeList.php'],
    [gettext('Edit')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fa-solid <?= $iPropertyTypeID > 0 ? 'fa-pen-to-square' : 'fa-plus' ?> me-2"></i>
            <?= $sPageTitle ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if ($bError): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong><?= gettext('Error') ?>:</strong> <?= gettext('Please correct the errors below.') ?>
        </div>
        <?php endif; ?>

        <form method="post" action="PropertyTypeEditor.php?PropertyTypeID=<?= $iPropertyTypeID ?>">
            <div class="row">
                <div class="col-md-6">
                    <!-- Class Selection -->
                    <div class="mb-3">
                        <label for="class">
                            <?= gettext('Class') ?>
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="class" name="Class" required>
                            <option value="p" <?= ($sClass == 'p' ? 'selected' : '') ?>>
                                <?= gettext('Person') ?>
                            </option>
                            <option value="f" <?= ($sClass == 'f' ? 'selected' : '') ?>>
                                <?= gettext('Family') ?>
                            </option>
                            <option value="g" <?= ($sClass == 'g' ? 'selected' : '') ?>>
                                <?= gettext('Group') ?>
                            </option>
                        </select>
                        <small class="form-text text-body-secondary">
                            <?= gettext('Select the type of record this property applies to') ?>
                        </small>
                    </div>

                    <!-- Name Field -->
                    <div class="mb-3">
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
                    <div class="mb-3">
                        <label for="description"><?= gettext('Description') ?></label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="Description"
                                  rows="8"
                                  maxlength="255"><?= InputUtils::escapeHTML($sDescription) ?></textarea>
                        <small class="form-text text-body-secondary">
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
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?= gettext('Save') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/Include/Footer.php';
