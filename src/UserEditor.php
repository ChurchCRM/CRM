<?php

/*******************************************************************************
 *
 *  filename    : UserEditor.php
 *  description : form for adding and editing users
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  Updated 2005-03-19 by Everette L Mills: Updated to remove error that could be created
 *  by use of duplicate usernames
 *
 *  Additional Contributors:
 *  2006 Ed Davis
 *


 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\users\NewAccountEmail;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserConfig;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use Propel\Runtime\ActiveQuery\Criteria;

// Security: User must be an Admin to access this page.
// Otherwise re-direct to the main menu.
AuthenticationManager::redirectHomeIfNotAdmin();

$iPersonID = -1;
$vNewUser = false;
$bShowPersonSelect = false;

// Get the PersonID out of either querystring or the form, depending and what we're doing
if (isset($_GET['PersonID'])) {
    $iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');
    $bNewUser = false;
} elseif (isset($_POST['PersonID'])) {
    $iPersonID = InputUtils::legacyFilterInput($_POST['PersonID'], 'int');
    $bNewUser = false;
} elseif (isset($_GET['NewPersonID'])) {
    $iPersonID = InputUtils::legacyFilterInput($_GET['NewPersonID'], 'int');
    $bNewUser = true;
}

if (isset($_GET['ErrorText'])) {
    $sErrorText = InputUtils::legacyFilterInput($_GET['ErrorText'], 'string');
} else {
    $sErrorText = '';
}

//Value to help determine correct return state on error
if (isset($_POST['NewUser'])) {
    $NewUser = InputUtils::legacyFilterInput($_POST['NewUser'], 'string');
}

// Has the form been submitted?
if (isset($_POST['save']) && $iPersonID > 0) {
    // Assign all variables locally
    $sAction = $_POST['Action'];

    $defaultFY = CurrentFY();
    $sUserName = InputUtils::legacyFilterInput($_POST['UserName']);

    if (strlen($sUserName) < 3) {
        if ($NewUser == false) {
            //Report error for current user creation
            RedirectUtils::redirect('UserEditor.php?PersonID=' . $iPersonID . '&ErrorText=Login must be a least 3 characters!');
        } else {
            //Report error for new user creation
            RedirectUtils::redirect('UserEditor.php?NewPersonID=' . $iPersonID . '&ErrorText=Login must be a least 3 characters!');
        }
    } else {
        if (isset($_POST['AddRecords'])) {
            $AddRecords = 1;
        } else {
            $AddRecords = 0;
        }
        if (isset($_POST['EditRecords'])) {
            $EditRecords = 1;
        } else {
            $EditRecords = 0;
        }
        if (isset($_POST['DeleteRecords'])) {
            $DeleteRecords = 1;
        } else {
            $DeleteRecords = 0;
        }
        if (isset($_POST['MenuOptions'])) {
            $MenuOptions = 1;
        } else {
            $MenuOptions = 0;
        }
        if (isset($_POST['ManageGroups'])) {
            $ManageGroups = 1;
        } else {
            $ManageGroups = 0;
        }
        if (isset($_POST['Finance'])) {
            $Finance = 1;
        } else {
            $Finance = 0;
        }
        if (isset($_POST['Notes'])) {
            $Notes = 1;
        } else {
            $Notes = 0;
        }
        if (isset($_POST['EditSelf'])) {
            $EditSelf = 1;
        } else {
            $EditSelf = 0;
        }
        if (isset($_POST['Canvasser'])) {
            $Canvasser = 1;
        } else {
            $Canvasser = 0;
        }

        if (isset($_POST['Admin'])) {
            $Admin = 1;
        } else {
            $Admin = 0;
        }
        $Style = InputUtils::legacyFilterInput($_POST['Style']);

        // Initialize error flag
        $bErrorFlag = false;

        // Were there any errors?
        if (!$bErrorFlag) {
            $undupCount = UserQuery::create()->filterByUserName($sUserName)->_and()->filterByPersonId($iPersonID, Criteria::NOT_EQUAL)->count();

            // Write the SQL depending on whether we're adding or editing
            if ($sAction == 'add') {
                if ($undupCount == 0) {
                    $rawPassword = User::randomPassword();
                    $sPasswordHashSha256 = hash('sha256', $rawPassword . $iPersonID);
                    $sSQL = 'INSERT INTO user_usr (usr_per_ID, usr_Password, usr_NeedPasswordChange, usr_LastLogin, usr_AddRecords, usr_EditRecords, usr_DeleteRecords, usr_MenuOptions, usr_ManageGroups, usr_Finance, usr_Notes, usr_Admin, usr_Style, usr_SearchLimit, usr_defaultFY, usr_UserName, usr_EditSelf, usr_Canvasser) VALUES (' . $iPersonID . ",'" . $sPasswordHashSha256 . "',1,'" . date('Y-m-d H:i:s') . "', " . $AddRecords . ', ' . $EditRecords . ', ' . $DeleteRecords . ', ' . $MenuOptions . ', ' . $ManageGroups . ', ' . $Finance . ', ' . $Notes . ', ' . $Admin . ", '" . $Style . "', 10," . $defaultFY . ',"' . $sUserName . '",' . $EditSelf . ',' . $Canvasser . ')';
                    // Execute the SQL
                    RunQuery($sSQL);
                    $newUser = UserQuery::create()->findPk($iPersonID);
                    $newUser->createTimeLineNote("created");
                    $email = new NewAccountEmail($newUser, $rawPassword);
                    $email->send();
                } else {
                    // Set the error text for duplicate when new user
                    RedirectUtils::redirect('UserEditor.php?NewPersonID=' . $PersonID . '&ErrorText=Login already in use, please select a different login!');
                }
            } else {
                if ($undupCount == 0) {
                    $user = UserQuery::create()->findOneByPersonId($iPersonID);
                    $user
                        ->setAddRecords($AddRecords)
                        ->setEditRecords($EditRecords)
                        ->setDeleteRecords($DeleteRecords)
                        ->setMenuOptions($MenuOptions)
                        ->setManageGroups($ManageGroups)
                        ->setFinance($Finance)
                        ->setNotes($Notes)
                        ->setAdmin($Admin)
                        ->setUserStyle($Style)
                        ->setUserName($sUserName)
                        ->setEditSelf($EditSelf)
                        ->setCanvasser($Canvasser);
                    $user->save();
                    $user->reload();

                    $user->createTimeLineNote("updated");
                } else {
                    // Set the error text for duplicate when currently existing
                    RedirectUtils::redirect('UserEditor.php?PersonID=' . $iPersonID . '&ErrorText=Login already in use, please select a different login!');
                }
            }
        }
    }
} else {
    // Do we know which person yet?
    if ($iPersonID > 0) {
        $usr_per_ID = $iPersonID;

        if (!$bNewUser) {
            // Get the data on this user
            $sSQL = 'SELECT * FROM user_usr INNER JOIN person_per ON person_per.per_ID = user_usr.usr_per_ID WHERE usr_per_ID = ' . $iPersonID;
            $rsUser = RunQuery($sSQL);
            $aUser = mysqli_fetch_array($rsUser);
            extract($aUser);
            $sUser = $per_LastName . ', ' . $per_FirstName;
            $sUserName = $usr_UserName;
            $sAction = 'edit';
        } else {
            $dbPerson = PersonQuery::create()->findPk($iPersonID);
            $sUser = $dbPerson->getFullName();
            if ($dbPerson->getEmail() != '') {
                $sUserName = $dbPerson->getEmail();
            } else {
                $sUserName = $dbPerson->getFirstName() . $dbPerson->getLastName();
            }
            $sAction = 'add';
            $vNewUser = 'true';

            $usr_AddRecords = 0;
            $usr_EditRecords = 0;
            $usr_DeleteRecords = 0;
            $usr_MenuOptions = 0;
            $usr_ManageGroups = 0;
            $usr_Finance = 0;
            $usr_Notes = 0;
            $usr_Admin = 0;
            $usr_EditSelf = 1;
            $usr_Canvasser = 0;
            $usr_Style = '';
        }

        // New user without person selected yet
    } else {
        $sAction = 'add';
        $bShowPersonSelect = true;

        $usr_AddRecords = 0;
        $usr_EditRecords = 0;
        $usr_DeleteRecords = 0;
        $usr_MenuOptions = 0;
        $usr_ManageGroups = 0;
        $usr_Finance = 0;
        $usr_Notes = 0;
        $usr_Admin = 0;
        $usr_EditSelf = 1;
        $usr_Canvasser = 0;
        $sUserName = '';
        $usr_Style = '';
        $vNewUser = 'true';

        // Get all the people who are NOT currently users
        $sSQL = 'SELECT * FROM person_per LEFT JOIN user_usr ON person_per.per_ID = user_usr.usr_per_ID WHERE usr_per_ID IS NULL ORDER BY per_LastName';
        $rsPeople = RunQuery($sSQL);
    }
}

// Style sheet (CSS) file selection options
function StyleSheetOptions($currentStyle)
{
    foreach (['skin-blue', 'skin-blue-light', 'skin-yellow', 'skin-yellow-light', 'skin-green', 'skin-green-light', 'skin-purple', 'skin-purple-light', 'skin-red', 'skin-red-light', 'skin-black', 'skin-black-light'] as $stylename) {
        echo '<option value="' . $stylename . '"';
        if ($stylename == $currentStyle) {
            echo ' selected';
        }
        echo '>' . $stylename . '</option>';
    }
}

// Save Settings
if (isset($_POST['save']) && ($iPersonID > 0)) {
    $new_value = $_POST['new_value'];
    $new_permission = $_POST['new_permission'];
    $type = $_POST['type'];
    ksort($type);
    reset($type);
    while ($current_type = current($type)) {
        $id = key($type);
        // Filter Input
        if ($current_type == 'text' || $current_type == 'textarea') {
            $value = InputUtils::legacyFilterInput($new_value[$id]);
        } elseif ($current_type == 'number') {
            $value = InputUtils::legacyFilterInput($new_value[$id], 'float');
        } elseif ($current_type == 'date') {
            $value = InputUtils::legacyFilterInput($new_value[$id], 'date');
        } elseif ($current_type == 'boolean') {
            if ($new_value[$id] != '1') {
                $value = '';
            } else {
                $value = '1';
            }
        }

        if ($new_permission[$id] != 'TRUE') {
            $permission = 'FALSE';
        } else {
            $permission = 'TRUE';
        }

        // We can't update unless values already exist.
        $sSQL = 'SELECT * FROM userconfig_ucfg '
            . "WHERE ucfg_id=$id AND ucfg_per_id=$iPersonID ";
        $bRowExists = true;
        $iNumRows = mysqli_num_rows(RunQuery($sSQL));
        if ($iNumRows == 0) {
            $bRowExists = false;
        }

        if (!$bRowExists) { // If Row does not exist then insert default values.
            // Defaults will be replaced in the following Update
            $sSQL = 'SELECT * FROM userconfig_ucfg '
                . "WHERE ucfg_id=$id AND ucfg_per_id=0 ";
            $rsDefault = RunQuery($sSQL);
            $aDefaultRow = mysqli_fetch_row($rsDefault);
            if ($aDefaultRow) {
                list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type,
                    $ucfg_tooltip, $ucfg_permission, $ucfg_cat) = $aDefaultRow;

                $userConfig = new UserConfig();
                $userConfig
                    ->setPeronId($iPersonID)
                    ->setId($id)
                    ->setName($ucfg_name)
                    ->setValue($ucfg_value)
                    ->setType($ucfg_type)
                    ->setTooltip($ucfg_tooltip)
                    ->setPermission($ucfg_permission)
                    ->setCat($ucfg_cat);
                $userConfig->save();
            } else {
                echo '<br> Error on line ' . __LINE__ . ' of file ' . __FILE__;
                exit;
            }
        }

        // Save new setting
        $sSQL = 'UPDATE userconfig_ucfg '
            . "SET ucfg_value='$value', ucfg_permission='$permission' "
            . "WHERE ucfg_id='$id' AND ucfg_per_id=$iPersonID ";
        $rsUpdate = RunQuery($sSQL);
        next($type);
    }

    RedirectUtils::redirect('UserList.php');
}

// Set the page title and include HTML header
$sPageTitle = gettext('User Editor');
require 'Include/Header.php';

?>
<!-- Default box -->
<div class="card">
    <div class="card-body">
        <div class="callout callout-info">
            <?= gettext('Note: Changes will not take effect until next logon.') ?>
        </div>
        <form method="post" action="UserEditor.php">
            <input type="hidden" name="Action" value="<?= $sAction ?>">
            <input type="hidden" name="NewUser" value="<?= $vNewUser ?>">
            <div class="table-responsive">
                <table class="table table-hover">
                    <?php

                    // Are we adding?
                    if ($bShowPersonSelect) {
                        //Yes, so display the people drop-down
                        ?>
                        <tr>
                            <td><?= gettext('Person to Make User') ?>:</td>
                            <td>
                                <select name="PersonID" size="30" id="personSelect" class="form-control">
                                    <?php
                                    // Loop through all the people
                                    while ($aRow = mysqli_fetch_array($rsPeople)) {
                                        extract($aRow); ?>
                                        <option value="<?= $per_ID ?>"<?php if ($per_ID == $iPersonID) {
                                            echo ' selected';
                                                       } ?>><?= $per_LastName . ', ' . $per_FirstName ?></option>
                                        <?php
                                    } ?>
                                </select>
                            </td>
                        </tr>

                        <?php
                    } else { // No, just display the user name?>
                        <input type="hidden" name="PersonID" value="<?= $iPersonID ?>">
                        <tr>
                            <td><?= gettext('User') ?>:</td>
                            <td><?= $sUser ?></td>
                        </tr>
                        <?php
                    } ?>

                    <?php if (isset($sErrorText) != '') {
                        ?>
                        <tr>
                            <td align="center" colspan="2">
                                <span style="color:red;" id="PasswordError"><?= $sErrorText ?></span>
                            </td>
                        </tr>
                        <?php
                    } ?>
                    <tr>
                        <td><?= gettext('Login Name') ?>:</td>
                        <td><input type="text" name="UserName" value="<?= $sUserName ?>" class="form-control" width="32"></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Admin') ?>:</td>
                        <td><input type="checkbox" name="Admin" value="1"<?php if ($usr_Admin) {
                            echo ' checked';
                                                                         } ?>>&nbsp;<span class="SmallText"><?= gettext('(Grants all privileges.)') ?></span></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Add Records') ?>:</td>
                        <td><input type="checkbox" name="AddRecords" value="1"<?php if ($usr_AddRecords) {
                            echo ' checked';
                                                                              } ?>></td>
                    </tr>

                    <tr>
                        <td><?= gettext('Edit Records') ?>:</td>
                        <td><input type="checkbox" name="EditRecords" value="1"<?php if ($usr_EditRecords) {
                            echo ' checked';
                                                                               } ?>></td>
                    </tr>

                    <tr>
                        <td><?= gettext('Delete Records') ?>:</td>
                        <td><input type="checkbox" name="DeleteRecords" value="1"<?php if ($usr_DeleteRecords) {
                            echo ' checked';
                                                                                 } ?>></td>
                    </tr>

                    <tr>
                        <td><?= gettext('Manage Properties and Classifications') ?>:</td>
                        <td><input type="checkbox" name="MenuOptions" value="1"<?php if ($usr_MenuOptions) {
                            echo ' checked';
                                                                               } ?>></td>
                    </tr>

                    <tr>
                        <td><?= gettext('Manage Groups and Roles') ?>:</td>
                        <td><input type="checkbox" name="ManageGroups" value="1"<?php if ($usr_ManageGroups) {
                            echo ' checked';
                                                                                } ?>></td>
                    </tr>

                    <tr>
                        <td><?= gettext('Manage Donations and Finance') ?>:</td>
                        <td><input type="checkbox" name="Finance" value="1"<?php if ($usr_Finance) {
                            echo ' checked';
                                                                           } ?>></td>
                    </tr>

                    <tr>
                        <td><?= gettext('View, Add and Edit Notes') ?>:</td>
                        <td><input type="checkbox" name="Notes" value="1"<?php if ($usr_Notes) {
                            echo ' checked';
                                                                         } ?>></td>
                    </tr>

                    <tr>
                        <td><?= gettext('Edit Self') ?>:</td>
                        <td><input type="checkbox" name="EditSelf" value="1"<?php if ($usr_EditSelf) {
                            echo ' checked';
                                                                            } ?>>&nbsp;<span class="SmallText"><?= gettext('(Edit own family only.)') ?></span></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Canvasser') ?>:</td>
                        <td><input type="checkbox" name="Canvasser" value="1"<?php if ($usr_Canvasser) {
                            echo ' checked';
                                                                             } ?>>&nbsp;<span class="SmallText"><?= gettext('(Canvass volunteer.)') ?></span></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Style') ?>:</td>
                        <td class="TextColumnWithBottomBorder"><select
                                name="Style"><?php StyleSheetOptions($usr_Style); ?></select></td>
                    </tr>
                    <tr>
                        <td colspan="2" align="center">
                            <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="save">&nbsp;<input
                                type="button" class="btn btn-default" name="Cancel" value="<?= gettext('Cancel') ?>"
                                onclick="javascript:document.location='UserList.php';">
                        </td>
                    </tr>
                </table>
            </div>
    </div>
    <!-- /.box-body -->
</div>
<!-- /.box -->
<!-- Default box -->
<div class="card">
    <div class="card-body box-danger">
        <div
            class="callout callout-info"><?= gettext('Set Permission True to give this user the ability to change their current value.') ?></div>
        <div class="table-responsive">
            <table class="table">
                <tr>
                    <th><?= gettext('Permission') ?></h3></th>
                    <th><?= gettext('Variable name') ?></th>
                    <th><?= gettext('Current Value') ?></h3></th>
                    <th><?= gettext('Notes') ?></th>
                </tr>
                <?php


                //First get default settings, then overwrite with settings from this user

                // Get default settings
                $sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id='0' ORDER BY ucfg_id";
                $rsDefault = RunQuery($sSQL);
                $r = 1;
                // List Default Settings
                while ($aDefaultRow = mysqli_fetch_row($rsDefault)) {
                    list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type,
                        $ucfg_tooltip, $ucfg_permission) = $aDefaultRow;

                    // Overwrite with user settings if they already exist
                    $sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id='$usr_per_ID' "
                        . "AND ucfg_id='$ucfg_id' ";
                    $rsUser = RunQuery($sSQL);
                    while ($aUserRow = mysqli_fetch_row($rsUser)) {
                        list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type,
                            $ucfg_tooltip, $ucfg_permission) = $aUserRow;
                    }

                    // Default Permissions
                    if ($ucfg_permission == 'TRUE') {
                        $sel2 = 'SELECTED';
                        $sel1 = '';
                    } else {
                        $sel1 = 'SELECTED';
                        $sel2 = '';
                    }
                    echo "\n<tr>";
                    echo "<td><select name=\"new_permission[$ucfg_id]\">";
                    echo "<option value=\"FALSE\" $sel1>" . gettext('False');
                    echo "<option value=\"TRUE\" $sel2>" . gettext('True');
                    echo '</select></td>';

                    // Variable Name & Type
                    echo "<td>$ucfg_name</td>";

                    // Current Value
                    if ($ucfg_type == 'text') {
                        echo "<td>
            <input type=\"text\" size=\"30\" maxlength=\"255\" name=\"new_value[$ucfg_id]\"
            value=\"" . htmlspecialchars($ucfg_value, ENT_QUOTES) . '"></td>';
                    } elseif ($ucfg_type == 'textarea') {
                        echo "<td>
            <textarea rows=\"4\" cols=\"30\" name=\"new_value[$ucfg_id]\">"
                            . htmlspecialchars($ucfg_value, ENT_QUOTES) . '</textarea></td>';
                    } elseif ($ucfg_type == 'number' || $ucfg_type == 'date') {
                        echo '<td><input type="text" size="15"'
                            . " maxlength=\"15\" name=\"new_value[$ucfg_id]\" value=\"$ucfg_value\"></td>";
                    } elseif ($ucfg_type == 'boolean') {
                        if ($ucfg_value) {
                            $sel2 = 'SELECTED';
                            $sel1 = '';
                        } else {
                            $sel1 = 'SELECTED';
                            $sel2 = '';
                        }
                        echo "<td><select name=\"new_value[$ucfg_id]\">";
                        echo "<option value=\"\" $sel1>" . gettext('False');
                        echo "<option value=\"1\" $sel2>" . gettext('True');
                        echo '</select></td>';
                    }

                    // Notes
                    echo "<td><input type=\"hidden\" name=\"type[$ucfg_id]\" value=\"$ucfg_type\">
            " . gettext($ucfg_tooltip) . '</td></tr>';

                    $r++;
                }

                // Cancel, Save Buttons
                ?>

                <tr>
                    <td colspan="3" class="text-center">
                        <input type="submit" class="btn btn-primary" name="save"
                               value="<?= gettext('Save Settings') ?>">
                        <input type="submit" class="btn btn-default" name="cancel" value="<?= gettext('Cancel') ?>">
                    </td>
                </tr>
            </table>
        </div>
        </form>
    </div>
    <!-- /.box-body -->
</div>
<!-- /.box -->

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
    $(document).ready(function () {
        $("#personSelect").select2();
    });
</script>

<?php require 'Include/Footer.php' ?>
