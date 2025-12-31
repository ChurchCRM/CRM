<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be allowed to edit records to use this page.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isEditRecordsEnabled(), 'EditRecords');

// Initialize logger for error tracking
$logger = LoggerUtils::getAppLogger();

$sPageTitle = gettext('Group Member Properties Editor');

// Get the Group and Person IDs from the querystring
$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');
$iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');

// Get some info about this person.  per_Country is needed in case there are phone numbers.
$sSQL = 'SELECT per_FirstName, per_LastName, per_Country, per_fam_ID FROM person_per WHERE per_ID = ' . $iPersonID;
$rsPersonInfo = RunQuery($sSQL);
extract(mysqli_fetch_array($rsPersonInfo));

$fam_Country = '';

$sPhoneCountry = $per_Country ?? '';

// Get the name of this group.
$sSQL = 'SELECT grp_Name FROM group_grp WHERE grp_ID = ' . $iGroupID;
$rsGroupInfo = RunQuery($sSQL);
extract(mysqli_fetch_array($rsGroupInfo));

// We assume that the group selected has a special properties table and that it is populated
// with values for each group member.

// Get the properties list for this group: names, descriptions, types and prop_ID for ordering;  will process later..

$sSQL = 'SELECT groupprop_master.* FROM groupprop_master
            WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';
$rsPropList = RunQuery($sSQL);

$aPropErrors = [];

// Is this the second pass?
if (isset($_POST['GroupPropSubmit'])) {
    // Process all HTTP post data based upon the list of properties data we are expecting
    // If there is an error message, it gets assigned to an array of strings, $aPropErrors, for use in the form.

    $bErrorFlag = false;

    while ($rowPropList = mysqli_fetch_array($rsPropList, MYSQLI_BOTH)) {
        extract($rowPropList);

        $currentFieldData = InputUtils::legacyFilterInput($_POST[$prop_Field]);

        $bErrorFlag |= !validateCustomField($type_ID, $currentFieldData, $prop_Field, $aPropErrors);

        // assign processed value locally to $aPersonProps so we can use it to generate the form later
        $aPersonProps[$prop_Field] = $currentFieldData;
    }

    // If no errors, then update.
    if (!$bErrorFlag) {
        mysqli_data_seek($rsPropList, 0);

        $sSQL = 'UPDATE groupprop_' . $iGroupID . ' SET ';

        while ($rowPropList = mysqli_fetch_array($rsPropList, MYSQLI_BOTH)) {
            extract($rowPropList);
            $currentFieldData = trim($aPersonProps[$prop_Field]);

            sqlCustomField($sSQL, $type_ID, $currentFieldData, $prop_Field, $sPhoneCountry);
        }

        // chop off the last 2 characters (comma and space) added in the last while loop iteration.
        $sSQL = mb_substr($sSQL, 0, -2);

        $sSQL .= ' WHERE per_ID = ' . $iPersonID;

        //Execute the SQL
        $updateResult = RunQuery($sSQL);
        
        if (!$updateResult) {
            $logger->error('Failed to update group properties', [
                'person_id' => $iPersonID,
                'group_id' => $iGroupID,
            ]);
            $bErrorFlag = true;
        } else {
            // Return to the Person View
            RedirectUtils::redirect('PersonView.php?PersonID=' . $iPersonID);
        }
    }
} else {
    // First Pass
    // Verify that the groupprop_X table exists
    $checkTableSQL = 'SHOW TABLES LIKE "groupprop_' . $iGroupID . '"';
    $tableCheckResult = RunQuery($checkTableSQL);
    
    if (mysqli_num_rows($tableCheckResult) === 0) {
        // Table does not exist - create it with initial per_ID column
        $createTableSQL = 'CREATE TABLE IF NOT EXISTS groupprop_' . $iGroupID . ' (
            per_ID mediumint(8) unsigned NOT NULL default "0",
            PRIMARY KEY (per_ID),
            UNIQUE KEY per_ID (per_ID)
        ) ENGINE=InnoDB';
        $createResult = RunQuery($createTableSQL);
        
        if (!$createResult) {
            $logger->error('Failed to create group properties table', ['group_id' => $iGroupID]);
            $bErrorFlag = true;
        }
    }
    
    // Get the existing data for this group member
    $sSQL = 'SELECT * FROM groupprop_' . $iGroupID . ' WHERE per_ID = ' . $iPersonID;
    $rsPersonProps = RunQuery($sSQL);
    
    // Check if a record exists for this person in the group properties table
    if (mysqli_num_rows($rsPersonProps) === 0) {
        // No record exists - insert one with just the per_ID
        // This handles cases where the person was added before properties were enabled
        $sSQL = 'INSERT INTO groupprop_' . $iGroupID . ' (per_ID) VALUES (' . $iPersonID . ')';
        RunQuery($sSQL);
        
        // Now fetch the newly created record
        $sSQL = 'SELECT * FROM groupprop_' . $iGroupID . ' WHERE per_ID = ' . $iPersonID;
        $rsPersonProps = RunQuery($sSQL);
    }
    
    $aPersonProps = mysqli_fetch_array($rsPersonProps, MYSQLI_BOTH);
}

require_once __DIR__ . '/Include/Header.php';

if (mysqli_num_rows($rsPropList) === 0) {
?>
    <form>
        <h3><?= gettext('This group currently has no properties!  You can add them in the Group Editor.') ?></h3>
        <BR>
        <input type="button" class="btn btn-secondary" value="<?= gettext('Return to Person Record') ?>" Name="Cancel" onclick="javascript:document.location='PersonView.php?PersonID=<?= $iPersonID ?>';">
    </form>
<?php
} else {
?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Editing') ?> <i> <?= $grp_Name ?> </i> <?= gettext('data for member') ?> <i> <?= $per_FirstName . ' ' . $per_LastName ?> </i></h3>
        </div>
        <div class="card-body">
            <form method="post" action="GroupPropsEditor.php?<?= 'PersonID=' . $iPersonID . '&GroupID=' . $iGroupID ?>" name="GroupPropEditor">

                <table class="table">
                    <?php

                    // Make sure we're at the beginning of the properties list resource (2nd pass code used it)
                    mysqli_data_seek($rsPropList, 0);

                    while ($rowPropList = mysqli_fetch_array($rsPropList, MYSQLI_BOTH)) {
                        extract($rowPropList); ?>
                        <tr>
                            <td><?= $prop_Name ?>: </td>
                            <td>
                                <?php
                                $currentFieldData = trim($aPersonProps[$prop_Field]);

                                if ($type_ID == 11) {
                                    $prop_Special = null;
                                }  // ugh.. an argument with special cases!

                                formCustomField($type_ID, $prop_Field, $currentFieldData, $prop_Special, !isset($_POST['GroupPropSubmit']));

                                if (array_key_exists($prop_Field, $aPropErrors)) {
                                    echo '<span class="text-error">' . $aPropErrors[$prop_Field] . '</span>';
                                } ?>
                            </td>
                            <td><?= $prop_Description ?></td>
                        </tr>
                    <?php
                    } ?>
                    <tr>
                        <td class="text-center" colspan="3">
                            <br><br>
                            <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" Name="GroupPropSubmit">
                            &nbsp;
                            <input type="button" class="btn btn-secondary" value="<?= gettext('Cancel') ?>" Name="Cancel" onclick="javascript:document.location='PersonView.php?PersonID=<?= $iPersonID ?>';">
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>
<?php
}
<script>
    // Initialize all phone mask toggles for custom fields (guarded)
    document.addEventListener('DOMContentLoaded', function() {
        if (window.CRM && window.CRM.formUtils && typeof window.CRM.formUtils.initializeAllPhoneMaskToggles === 'function') {
            try {
                window.CRM.formUtils.initializeAllPhoneMaskToggles();
            } catch (e) {
                // silent
            }
        }
    });
</script>
<?php
require_once __DIR__ . '/Include/Footer.php';?>