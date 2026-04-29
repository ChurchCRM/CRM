<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\users\NewAccountEmail;
use ChurchCRM\view\PageHeader;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserConfig;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\FiscalYearUtils;
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

// Value to help determine correct return state on error
if (isset($_POST['NewUser'])) {
    $NewUser = InputUtils::legacyFilterInput($_POST['NewUser'], 'string');
}

// Has the form been submitted?
if (isset($_POST['save']) && $iPersonID > 0) {
    // Security: CSRF token validation (GHSA-3xq9-c86x-cwpp)
    if (!CSRFUtils::verifyRequest($_POST, 'user_editor')) {
        // Preserve add-vs-edit context — NewUser is "true"/"false" string from form
        $idParam = ($NewUser ?? 'false') === 'true' ? 'NewPersonID' : 'PersonID';
        RedirectUtils::redirect('UserEditor.php?' . $idParam . '=' . $iPersonID . '&ErrorText=Invalid+security+token.+Please+try+again.');
    }

    // Assign all variables locally
    $sAction = $_POST['Action'];

    $defaultFY = FiscalYearUtils::getCurrentFiscalYearId();
    $sUserName = InputUtils::legacyFilterInput($_POST['UserName']);

    if (strlen($sUserName) < 3) {
        if ($NewUser === false) {
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

        if (isset($_POST['Admin'])) {
            $Admin = 1;
        } else {
            $Admin = 0;
        }
        // Initialize error flag
        $bErrorFlag = false;

        // Were there any errors?
        if (!$bErrorFlag) {
            $undupCount = UserQuery::create()->filterByUserName($sUserName)->_and()->filterByPersonId($iPersonID, Criteria::NOT_EQUAL)->count();

            // Write the SQL depending on whether we're adding or editing
            if ($sAction === 'add') {
                if ($undupCount === 0) {
                    $rawPassword = User::randomPassword();

                    // Use ORM to create new user
                    $newUser = new User();
                    $newUser->setPersonId((int)$iPersonID)
                        ->setNeedPasswordChange(true)
                        ->setLastLogin(date('Y-m-d H:i:s'))
                        ->setAddRecords($AddRecords)
                        ->setEditRecords($EditRecords)
                        ->setDeleteRecords($DeleteRecords)
                        ->setMenuOptions($MenuOptions)
                        ->setManageGroups($ManageGroups)
                        ->setFinance($Finance)
                        ->setNotes($Notes)
                        ->setAdmin($Admin)
                        ->setDefaultFY($defaultFY)
                        ->setUserName($sUserName)
                        ->setEditSelf($EditSelf);
                    // Use the User model's hashPassword method for secure bcrypt hashing
                    $newUser->updatePassword($rawPassword);
                    $newUser->save();

                    $newUser->createTimeLineNote("created");
                    if (SystemConfig::isEmailEnabled()) {
                        $email = new NewAccountEmail($newUser, $rawPassword);
                        $email->send();
                    }

                    RedirectUtils::redirect('admin/system/users');
                } else {
                    // Set the error text for duplicate when new user
                    RedirectUtils::redirect('UserEditor.php?NewPersonID=' . $iPersonID . '&ErrorText=Login already in use, please select a different login!');
                }
            } else {
                if ($undupCount === 0) {
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
                        ->setUserName($sUserName)
                        ->setEditSelf($EditSelf);
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
            // Get the data on this user using ORM
            $user = UserQuery::create()->findPk($iPersonID);
            if ($user !== null) {
                $person = $user->getPerson();
                $sUser = $person->getLastName() . ', ' . $person->getFirstName();
                $sUserName = $user->getUserName();
                $usr_AddRecords = $user->getAddRecords();
                $usr_EditRecords = $user->getEditRecords();
                $usr_DeleteRecords = $user->getDeleteRecords();
                $usr_MenuOptions = $user->getMenuOptions();
                $usr_ManageGroups = $user->getManageGroups();
                $usr_Finance = $user->getFinance();
                $usr_Notes = $user->getNotes();
                $usr_Admin = $user->getAdmin();
                $usr_EditSelf = $user->getEditSelf();
                $sAction = 'edit';
            }
        } else {
            $dbPerson = PersonQuery::create()->findPk($iPersonID);
            $sUser = $dbPerson->getFullName();
            if ($dbPerson->getEmail() !== '') {
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
        $sUserName = '';
        $vNewUser = 'true';

        // Get all the people who are NOT currently users
        $sSQL = 'SELECT * FROM person_per LEFT JOIN user_usr ON person_per.per_ID = user_usr.usr_per_ID WHERE usr_per_ID IS NULL ORDER BY per_LastName';
        $rsPeople = RunQuery($sSQL);
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
        // Sanitize the array key to prevent SQL injection
        $id = InputUtils::filterInt(key($type));
        // Filter Input
        if ($current_type === 'text' || $current_type === 'textarea') {
            $value = InputUtils::legacyFilterInput($new_value[$id]);
        } elseif ($current_type === 'number') {
            $value = InputUtils::legacyFilterInput($new_value[$id], 'float');
        } elseif ($current_type === 'date') {
            $value = InputUtils::legacyFilterInput($new_value[$id], 'date');
        } elseif ($current_type === 'boolean') {
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

        // Check if user config exists using Propel ORM
        $userConfig = UserConfigQuery::create()
            ->filterById($id)
            ->filterByPeronId($iPersonID)
            ->findOne();

        if ($userConfig === null) {
            // Row doesn't exist - get default values and create new config
            $defaultConfig = UserConfigQuery::create()
                ->filterById($id)
                ->filterByPeronId(0)
                ->findOne();

            if ($defaultConfig !== null) {
                $userConfig = new UserConfig();
                $userConfig
                    ->setPeronId($iPersonID)
                    ->setId($id)
                    ->setName($defaultConfig->getName())
                    ->setValue($value)
                    ->setType($defaultConfig->getType())
                    ->setTooltip($defaultConfig->getTooltip())
                    ->setPermission($permission)
                    ->setCat($defaultConfig->getCat());
                $userConfig->save();
            } else {
                echo '<br> Error on line ' . __LINE__ . ' of file ' . __FILE__;
                exit;
            }
        } else {
            // Update existing config
            $userConfig->setValue($value);
            $userConfig->setPermission($permission);
            $userConfig->save();
        }

        next($type);
    }

    RedirectUtils::redirect('admin/system/users');
}

$sPageTitle = gettext('User Editor');
$sPageSubtitle = gettext('Manage user account details and permissions');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Admin'), '/admin/'],
    [gettext('Users'), '/admin/system/users'],
    [gettext('Edit User')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<form method="post" action="UserEditor.php">
<?= CSRFUtils::getTokenInputField('user_editor') ?>
<input type="hidden" name="Action" value="<?= $sAction ?>">
<input type="hidden" name="NewUser" value="<?= $vNewUser ?>">

<?php if (!empty($sErrorText)): ?>
<div class="alert alert-danger alert-dismissible" role="alert">
    <i class="ti ti-alert-circle me-2"></i><?= InputUtils::escapeHTML($sErrorText) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!SystemConfig::isEmailEnabled()): ?>
<div class="alert alert-warning d-flex align-items-center" role="alert">
    <i class="fa-solid fa-triangle-exclamation me-2 fs-3"></i>
    <div class="flex-grow-1">
        <strong><?= gettext('Email is disabled') ?></strong>
        <div class="text-secondary"><?= gettext('New users will not receive a welcome email with their credentials. Share the password with them manually, or configure email first.') ?></div>
    </div>
    <a href="<?= SystemURLs::getRootPath() ?>/v2/email/dashboard?settings=open" class="btn btn-warning ms-3">
        <i class="fa-solid fa-envelope me-1"></i><?= gettext('Set up Email') ?>
    </a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Account') ?></h3>
    </div>
    <div class="card-body">
        <?php if ($bShowPersonSelect): ?>
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label"><?= gettext('Person') ?></label>
            <div class="col-sm-9">
                <select name="PersonID" id="personSelect" class="form-select">
                    <?php while ($aRow = mysqli_fetch_array($rsPeople)): extract($aRow); ?>
                    <option value="<?= $per_ID ?>"<?= $per_ID == $iPersonID ? ' selected' : '' ?>><?= InputUtils::escapeHTML($per_LastName . ', ' . $per_FirstName) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="PersonID" value="<?= $iPersonID ?>">
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label"><?= gettext('User') ?></label>
            <div class="col-sm-9">
                <div class="form-control-plaintext"><?= InputUtils::escapeHTML($sUser) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row mb-3">
            <label class="col-sm-3 col-form-label" for="UserName"><?= gettext('Login Name') ?></label>
            <div class="col-sm-9">
                <input type="text" name="UserName" id="UserName" value="<?= InputUtils::escapeAttribute($sUserName) ?>" class="form-control">
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Permissions') ?></h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <i class="ti ti-info-circle me-2"></i><?= gettext('Changes will not take effect until next logon.') ?>
        </div>

        <div class="row mb-3">
            <label class="col-sm-5 col-form-label" for="Admin"><?= gettext('Administrator') ?></label>
            <div class="col-sm-7">
                <label class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="Admin" id="Admin" value="1"<?= $usr_Admin ? ' checked' : '' ?>>
                    <span class="form-check-label"><?= gettext('Grants all privileges') ?></span>
                </label>
            </div>
        </div>

        <hr>

        <?php
        $permissions = [
            ['name' => 'AddRecords', 'label' => gettext('Add Records'), 'checked' => $usr_AddRecords, 'hint' => ''],
            ['name' => 'EditRecords', 'label' => gettext('Edit Records'), 'checked' => $usr_EditRecords, 'hint' => ''],
            ['name' => 'DeleteRecords', 'label' => gettext('Delete Records'), 'checked' => $usr_DeleteRecords, 'hint' => ''],
            ['name' => 'MenuOptions', 'label' => gettext('Manage Properties and Classifications'), 'checked' => $usr_MenuOptions, 'hint' => ''],
            ['name' => 'ManageGroups', 'label' => gettext('Manage Groups and Roles'), 'checked' => $usr_ManageGroups, 'hint' => ''],
            ['name' => 'Finance', 'label' => gettext('Manage Donations and Finance'), 'checked' => $usr_Finance, 'hint' => ''],
            ['name' => 'Notes', 'label' => gettext('View, Add and Edit Notes'), 'checked' => $usr_Notes, 'hint' => ''],
            ['name' => 'EditSelf', 'label' => gettext('Edit Self'), 'checked' => $usr_EditSelf, 'hint' => gettext('Edit own family only')],
        ];
        foreach ($permissions as $perm):
        ?>
        <div class="row mb-2 permission-row">
            <label class="col-sm-5 col-form-label" for="<?= $perm['name'] ?>"><?= $perm['label'] ?></label>
            <div class="col-sm-7">
                <label class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="<?= $perm['name'] ?>" id="<?= $perm['name'] ?>" value="1"<?= $perm['checked'] ? ' checked' : '' ?>>
                    <?php if ($perm['hint']): ?>
                    <span class="form-check-label text-body-secondary"><?= $perm['hint'] ?></span>
                    <?php endif; ?>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="card-footer text-end">
        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/users" class="btn btn-secondary me-2"><?= gettext('Cancel') ?></a>
        <button type="submit" class="btn btn-primary" id="SaveButton" name="save"><?= gettext('Save') ?></button>
    </div>
</div>
<?php if (!$bShowPersonSelect && !$bNewUser): ?>
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('User Config') ?></h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <i class="ti ti-info-circle me-2"></i><?= gettext('Set Permission to True to allow this user to change the setting themselves.') ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th><?= gettext('Permission') ?></th>
                    <th><?= gettext('Variable name') ?></th>
                    <th><?= gettext('Current Value') ?></th>
                    <th><?= gettext('Notes') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id='0' ORDER BY ucfg_id";
                $rsDefault = RunQuery($sSQL);
                while ($aDefaultRow = mysqli_fetch_row($rsDefault)) {
                    list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, $ucfg_tooltip, $ucfg_permission) = $aDefaultRow;

                    $sSQL = "SELECT * FROM userconfig_ucfg WHERE ucfg_per_id='" . (int)$usr_per_ID . "' AND ucfg_id='" . (int)$ucfg_id . "'";
                    $rsUser = RunQuery($sSQL);
                    while ($aUserRow = mysqli_fetch_row($rsUser)) {
                        list($ucfg_per_id, $ucfg_id, $ucfg_name, $ucfg_value, $ucfg_type, $ucfg_tooltip, $ucfg_permission) = $aUserRow;
                    }
                ?>
                <tr>
                    <td>
                        <select class="form-select form-select-sm" name="new_permission[<?= (int)$ucfg_id ?>]">
                            <option value="FALSE"<?= $ucfg_permission !== 'TRUE' ? ' selected' : '' ?>><?= gettext('False') ?></option>
                            <option value="TRUE"<?= $ucfg_permission === 'TRUE' ? ' selected' : '' ?>><?= gettext('True') ?></option>
                        </select>
                    </td>
                    <td><?= InputUtils::escapeHTML($ucfg_name) ?></td>
                    <td>
                        <?php if ($ucfg_type === 'text'): ?>
                        <input type="text" class="form-control form-control-sm" maxlength="255" name="new_value[<?= (int)$ucfg_id ?>]" value="<?= InputUtils::escapeAttribute($ucfg_value) ?>">
                        <?php elseif ($ucfg_type === 'textarea'): ?>
                        <textarea class="form-control form-control-sm" rows="3" name="new_value[<?= (int)$ucfg_id ?>]"><?= InputUtils::escapeHTML($ucfg_value) ?></textarea>
                        <?php elseif ($ucfg_type === 'number' || $ucfg_type === 'date'): ?>
                        <input type="text" class="form-control form-control-sm" maxlength="15" name="new_value[<?= (int)$ucfg_id ?>]" value="<?= InputUtils::escapeAttribute($ucfg_value) ?>">
                        <?php elseif ($ucfg_type === 'boolean'): ?>
                        <select class="form-select form-select-sm" name="new_value[<?= (int)$ucfg_id ?>]">
                            <option value=""<?= !$ucfg_value ? ' selected' : '' ?>><?= gettext('False') ?></option>
                            <option value="1"<?= $ucfg_value ? ' selected' : '' ?>><?= gettext('True') ?></option>
                        </select>
                        <?php endif; ?>
                        <input type="hidden" name="type[<?= (int)$ucfg_id ?>]" value="<?= InputUtils::escapeAttribute($ucfg_type) ?>">
                    </td>
                    <td class="text-body-secondary"><?= gettext($ucfg_tooltip) ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/users" class="btn btn-secondary me-2"><?= gettext('Cancel') ?></a>
        <button type="submit" class="btn btn-primary" name="save"><?= gettext('Save') ?></button>
    </div>
</div>
<?php endif; ?>
</form>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function() {
        var personSelectEl = document.getElementById("personSelect");
        if (personSelectEl && !personSelectEl.tomselect) new TomSelect(personSelectEl);
    });
</script>
<?php
require_once __DIR__ . '/Include/Footer.php';
