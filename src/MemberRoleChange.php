<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security: User must have Manage Groups & Roles permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(), 'ManageGroups');

$sPageTitle = gettext('Member Role Change');
$sPageSubtitle = gettext('Modify family roles for group members');

// Get the GroupID from the querystring
$iGroupID = (int)InputUtils::legacyFilterInput($_GET['GroupID'], 'int');

// Get the PersonID from the querystring
$iPersonID = (int)InputUtils::legacyFilterInput($_GET['PersonID'], 'int');

// Get the return location flag from the querystring
$iReturn = $_GET['Return'];

// Was the form submitted?
if (isset($_POST['Submit'])) {
    //Get the new role
    $iNewRole = (int)InputUtils::legacyFilterInput($_POST['NewRole']);

    //Update the database
    $p2g2r = Person2group2roleP2g2rQuery::create()
        ->filterByPersonId($iPersonID)
        ->filterByGroupId($iGroupID)
        ->findOne();
    if ($p2g2r !== null) {
        $p2g2r->setRoleId($iNewRole);
        $p2g2r->save();
    }

    //Reroute back to the proper location
    if ($iReturn) {
        RedirectUtils::redirect('groups/view/' . $iGroupID);
    } else {
        RedirectUtils::redirect(Person::getViewURIForId($iPersonID));
    }
}

// Get person, group, and current role via ORM
$person = PersonQuery::create()->findOneById($iPersonID);
$group = GroupQuery::create()->findOneById($iGroupID);
$p2g2r = Person2group2roleP2g2rQuery::create()
    ->filterByPersonId($iPersonID)
    ->filterByGroupId($iGroupID)
    ->findOne();

$per_FirstName = $person ? $person->getFirstName() : '';
$per_LastName = $person ? $person->getLastName() : '';
$grp_Name = $group ? $group->getName() : '';
$grp_RoleListID = $group ? $group->getRoleListId() : 0;
$iRoleID = $p2g2r ? $p2g2r->getRoleId() : 0;

// Get current role name
$currentRole = ListOptionQuery::create()
    ->filterById($grp_RoleListID)
    ->filterByOptionId($iRoleID)
    ->findOne();
$sRoleName = $currentRole ? $currentRole->getOptionName() : '';

// Get all the possible roles
$allRoles = ListOptionQuery::create()
    ->filterById($grp_RoleListID)
    ->orderByOptionSequence()
    ->find();

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Groups'), '/groups/dashboard'],
    [gettext('Member Role Change')],
]);
require_once __DIR__ . '/Include/Header.php'

?>

<div class="card-body">
    <form method="post" action="MemberRoleChange.php?GroupID=<?= $iGroupID ?>&PersonID=<?= $iPersonID ?>&Return=<?= $iReturn ?>">

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label fw-bold text-end"><?= gettext('Group Name') ?>:</label>
            <div class="col-sm-9 col-form-label"><?= InputUtils::escapeHTML($grp_Name) ?></div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label fw-bold text-end"><?= gettext("Member's Name") ?>:</label>
            <div class="col-sm-9 col-form-label"><?= InputUtils::escapeHTML($per_LastName) . ', ' . InputUtils::escapeHTML($per_FirstName) ?></div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label fw-bold text-end"><?= gettext('Current Role') ?>:</label>
            <div class="col-sm-9 col-form-label"><?= gettext($sRoleName) ?></div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label fw-bold text-end" for="NewRole"><?= gettext('New Role') ?>:</label>
            <div class="col-sm-4">
                <select name="NewRole" id="NewRole" class="form-select">
                    <?php
                    // Loop through all the possible roles
                    foreach ($allRoles as $role) {
                        $sSelected = ($iRoleID == $role->getOptionId()) ? 'selected' : '';
                        echo '<option value="' . (int)$role->getOptionId() . '" ' . $sSelected . '>' . InputUtils::escapeHTML(gettext($role->getOptionName())) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Update') ?>">
            <?php
            if ($iReturn) {
                echo ' <a href="' . SystemURLs::getRootPath() . '/groups/view/' . $iGroupID . '" class="btn btn-secondary ms-2">' . gettext('Cancel') . '</a>';
            } else {
                echo ' <a href="' . Person::getViewURIForId($iPersonID) . '" class="btn btn-secondary ms-2">' . gettext('Cancel') . '</a>';
            }
            ?>
        </div>

    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
