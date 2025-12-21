<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Property;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have property and classification editing permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

$sClassError = '';
$sNameError = '';

// Get the PropertyID
$iPropertyID = 0;
if (array_key_exists('PropertyID', $_GET)) {
    $iPropertyID = InputUtils::legacyFilterInput($_GET['PropertyID'], 'int');
}

// Get the Type
$sType = InputUtils::legacyFilterInput($_GET['Type'], 'char', 1);

// Based on the type, set the TypeName
switch ($sType) {
    case 'p':
        $sTypeName = gettext('Person');
        break;

    case 'f':
        $sTypeName = gettext('Family');
        break;

    case 'g':
        $sTypeName = gettext('Group');
        break;

    default:
        RedirectUtils::redirect('v2/dashboard');
        break;
}

$sPageTitle = $sTypeName . ' ' . gettext('Property Editor');

$bError = false;
$iType = 0;

// Was the form submitted?
if (isset($_POST['Submit'])) {
    $sName = addslashes(InputUtils::legacyFilterInput($_POST['Name']));
    $sDescription = addslashes(InputUtils::legacyFilterInput($_POST['Description']));
    $iClass = InputUtils::legacyFilterInput($_POST['Class'], 'int');
    $sPrompt = InputUtils::legacyFilterInput($_POST['Prompt']);

    // Did they enter a name?
    if (strlen($sName) < 1) {
        $sNameError = '<br><span class="text-error">' . gettext('You must enter a name') . '</span>';
        $bError = true;
    }

    // Did they select a Type
    if (strlen($iClass) < 1) {
        $sClassError = '<br><span class="text-error">' . gettext('You must select a type') . '</span>';
        $bError = true;
    }

    // If no errors, let's update
    if (!$bError) {
        // Vary the SQL depending on if we're adding or editing
        if ($iPropertyID == 0) {
            $property = new Property();
            $property
                ->setProClass($sType)
                ->setProPrtId($iClass)
                ->setProName($sName)
                ->setProDescription($sDescription)
                ->setProPrompt($sPrompt);
            $property->save();
        } else {
            $property = PropertyQuery::create()->findOneByProId($iPropertyID);
            $property
                ->setProPrtId($iClass)
                ->setProName($sName)
                ->setProDescription($sDescription)
                ->setProPrompt($sPrompt);
            $property->save();
        }

        // Route back to the list
        RedirectUtils::redirect('PropertyList.php?Type=' . $sType);
    }
} else {
    if ($iPropertyID != 0) {
        // Get the data on this property
        $sSQL = 'SELECT * FROM property_pro WHERE pro_ID = ' . $iPropertyID;
        $rsProperty = mysqli_fetch_array(RunQuery($sSQL));
        extract($rsProperty);

        // Assign values locally
        $sName = $pro_Name;
        $sDescription = $pro_Description;
        $iType = $pro_prt_ID;
        $sPrompt = $pro_Prompt;
    } else {
        $sName = '';
        $sDescription = '';
        $iType = 0;
        $sPrompt = '';
    }
}

// Get the Property Types
$sSQL = "SELECT * FROM propertytype_prt WHERE prt_Class = '" . $sType . "' ORDER BY prt_Name";
$rsPropertyTypes = RunQuery($sSQL);

require_once __DIR__ . '/Include/Header.php';

?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <?php if ($iPropertyID == 0) { ?>
                            <i class="fa fa-plus"></i> <?= gettext('Add New') ?> <?= $sTypeName ?> <?= gettext('Property') ?>
                        <?php } else { ?>
                            <i class="fa fa-edit"></i> <?= gettext('Edit') ?> <?= $sTypeName ?> <?= gettext('Property') ?>
                        <?php } ?>
                    </h4>
                </div>
                <div class="card-body">
                    <form method="post" action="PropertyEditor.php?PropertyID=<?= $iPropertyID ?>&Type=<?= InputUtils::escapeAttribute($sType) ?>">
                        <div class="mb-3">
                            <label for="Class" class="form-label"><?= gettext('Type') ?><span class="text-danger">*</span></label>
                            <select class="form-select" id="Class" name="Class" required>
                                <option value=""><?= gettext('Select Property Type') ?></option>
                                <?php
                                while ($aRow = mysqli_fetch_array($rsPropertyTypes)) {
                                    extract($aRow);
                                    echo '<option value="' . (int)$prt_ID . '"';
                                    if ($iType == $prt_ID) {
                                        echo ' selected';
                                    }
                                    echo '>' . InputUtils::escapeHTML($prt_Name) . '</option>';
                                }
                                ?>
                            </select>
                            <?php if (!empty($sClassError)) { ?>
                                <div class="invalid-feedback d-block mt-2">
                                    <?= $sClassError ?>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="mb-3">
                            <label for="Name" class="form-label"><?= gettext('Name') ?><span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="Name" name="Name" value="<?= InputUtils::escapeAttribute($sName) ?>" placeholder="<?= gettext('Property name') ?>" required>
                            <?php if (!empty($sNameError)) { ?>
                                <div class="invalid-feedback d-block mt-2">
                                    <?= $sNameError ?>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="mb-3">
                            <label for="Description" class="form-label">
                                <?= gettext('A') ?> <?= $sTypeName ?> <?= gettext('with this property...') ?>
                            </label>
                            <textarea class="form-control" id="Description" name="Description" rows="3" placeholder="<?= gettext('Description for records with this property') ?>"><?= InputUtils::escapeAttribute($sDescription) ?></textarea>
                            <small class="form-text text-muted d-block mt-2">
                                <?= gettext('Example: Member has completed background check') ?>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="Prompt" class="form-label"><?= gettext('Prompt') ?></label>
                            <input type="text" class="form-control" id="Prompt" name="Prompt" value="<?= InputUtils::escapeAttribute($sPrompt) ?>" placeholder="<?= gettext('Prompt for entering values (optional)') ?>">
                            <small class="form-text text-muted d-block mt-2">
                                <?= gettext('If you enter a prompt, users can enter free-form values for this property.') ?>
                            </small>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="save" name="Submit" value="Save">
                                <i class="fa fa-save"></i> <?= gettext('Save') ?>
                            </button>
                            <button type="button" class="btn btn-secondary" name="Cancel" onclick="document.location='PropertyList.php?Type=<?= InputUtils::escapeAttribute($sType) ?>';">
                                <?= gettext('Cancel') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/Include/Footer.php';
