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
$sNameError = '';
$sClassError = '';

// Was the form submitted?
if (isset($_POST['Submit'])) {
    $sName = addslashes(InputUtils::legacyFilterInput($_POST['Name']));
    $sDescription = addslashes(InputUtils::legacyFilterInput($_POST['Description']));
    $iClass = InputUtils::legacyFilterInput($_POST['Class'], 'int');
    $sPrompt = InputUtils::legacyFilterInput($_POST['Prompt']);

    // Did they enter a name?
    if (strlen($sName) < 1) {
        $sNameError = '<small class="text-danger d-block mt-1"><i class="fa-solid fa-circle-exclamation"></i> ' . gettext('You must enter a name') . '</small>';
        $bError = true;
    }

    // Did they select a Type
    if (strlen($iClass) < 1) {
        $sClassError = '<small class="text-danger d-block mt-1"><i class="fa-solid fa-circle-exclamation"></i> ' . gettext('You must select a type') . '</small>';
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
<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-edit"></i>
                        <?= gettext('Property Editor') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="PropertyEditor.php?PropertyID=<?= InputUtils::escapeAttribute($iPropertyID) ?>&Type=<?= InputUtils::escapeAttribute($sType) ?>">
                        <div class="mb-3">
                            <label for="Class" class="form-label"><?= gettext('Type') ?>:</label>
                            <select class="form-control" name="Class">
                                <option value=""><?= gettext('Select Property Type') ?></option>
                                <?php
                                while ($aRow = mysqli_fetch_array($rsPropertyTypes)) {
                                    extract($aRow);

                                    echo '<option value="' . InputUtils::escapeAttribute($prt_ID) . '"';
                                    if ($iType == $prt_ID) {
                                        echo ' selected';
                                    }
                                    echo '>' . InputUtils::escapeHTML($prt_Name) . '</option>';
                                }
                                ?>
                            </select>
                            <?= $sClassError ?>
                        </div>
                        <div class="mb-3">
                            <label for="Name" class="form-label"><?= gettext('Name') ?>:</label>
                            <input class="form-control" type="text" name="Name" value="<?= InputUtils::escapeAttribute($sName) ?>" maxlength="100">
                            <?php echo $sNameError ?>
                        </div>
                        <div class="mb-3">
                            <label for="Description" class="form-label"><?= gettext('A') ?> <?= InputUtils::escapeHTML($sTypeName) ?> <?= gettext('with this property...') ?>:</label>
                            <textarea class="form-control" name="Description" rows="3"><?= InputUtils::escapeAttribute($sDescription) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="Prompt" class="form-label"><?= gettext('Prompt') ?>:</label>
                            <input class="form-control" type="text" name="Prompt" value="<?= InputUtils::escapeAttribute($sPrompt) ?>" maxlength="50">
                            <small class="form-text text-muted d-block mt-1"><?= gettext('Entering a Prompt value will allow the association of a free-form value.') ?></small>
                        </div>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-success mr-2" name="Submit">
                                <i class="fa-solid fa-save"></i>
                                <?= gettext('Save') ?>
                            </button>
                            <button type="button" class="btn btn-secondary" name="Cancel" onclick="document.location='PropertyList.php?Type=<?= InputUtils::escapeAttribute($sType) ?>';">
                                <i class="fa-solid fa-ban"></i>
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
