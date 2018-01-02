<?php
/*******************************************************************************
 *
 *  filename    : UserList.php
 *  last change : 2003-01-07
 *  description : displays a list of all users
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *




 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\UserQuery;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bAdmin']) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Get all the User records
$rsUsers = UserQuery::create()->find();

// Set the page title and include HTML header
$sPageTitle = gettext('User Listing');
require 'Include/Header.php';

?>
<!-- Default box -->
<div class="box">
    <div class="box-header">
        <a href="UserEditor.php" class="btn btn-app"><i class="fa fa-user-plus"></i><?= gettext('New User') ?></a>
        <a href="SettingsUser.php" class="btn btn-app"><i class="fa fa-wrench"></i><?= gettext('User Settings') ?></a>
    </div>
</div>
<div class="box">
    <div class="box-body">
        <table class="table table-hover dt-responsive" id="user-listing-table" style="width:100%;">
            <thead>
            <tr>
                <th><?= gettext('Actions') ?></th>
                <th><?= gettext('Name') ?></th>
                <th align="center"><?= gettext('Last Login') ?></th>
                <th align="center"><?= gettext('Total Logins') ?></th>
                <th align="center"><?= gettext('Failed Logins') ?></th>
                <th align="center"><?= gettext('Password') ?></th>

            </tr>
            </thead>
            <tbody>
            <?php foreach ($rsUsers as $user) { //Loop through the person?>
                <tr>
                    <td>
                        <a href="UserEditor.php?PersonID=<?= $user->getId() ?>"><i class="fa fa-pencil"
                                                                                   aria-hidden="true"></i></a>&nbsp;&nbsp;
                        <?php if ($user->getId() != $_SESSION['user']->getId()) {
    ?>
                            <a onclick="deleteUser(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')"><i
                                        class="fa fa-trash-o" aria-hidden="true"></i></a>
                            <?php
} ?>
                    </td>
                    <td>
                        <a href="PersonView.php?PersonID=<?= $user->getId() ?>"> <?= $user->getPerson()->getFullName() ?></a>
                    </td>
                    <td align="center"><?= $user->getLastLogin(SystemConfig::getValue('sDateFormatShort')) ?></td>
                    <td align="center"><?= $user->getLoginCount() ?></td>
                    <td align="center">
                        <?php if ($user->isLocked()) {
        ?>
                            <span class="text-red"><?= $user->getFailedLogins() ?></span>
                            <?php
    } else {
        echo $user->getFailedLogins();
    }
    if ($user->getFailedLogins() > 0) {
        ?>
                            <a onclick="restUserLoginCount(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')"><i
                                        class="fa fa-eraser" aria-hidden="true"></i></a>
                            <?php
    } ?>
                    </td>
                    <td>
                        <a href="UserPasswordChange.php?PersonID=<?= $user->getId() ?>&FromUserList=True"><i
                                    class="fa fa-wrench" aria-hidden="true"></i></a>&nbsp;&nbsp;
                        <?php if ($user->getId() != $_SESSION['user']->getId() && !empty($user->getEmail())) {
        ?>
                            <a onclick="resetUserPassword(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')"><i
                                        class="fa fa-send-o" aria-hidden="true"></i></a>
                            <?php
    } ?>
                    </td>

                </tr>
                <?php
} ?>
            </tbody>
        </table>
    </div>
    <!-- /.box-body -->
</div>
<!-- /.box -->

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
    $(document).ready(function () {
        $("#user-listing-table").DataTable(window.CRM.plugin.dataTable);
    });

    function deleteUser(userId, userName) {
        bootbox.confirm({
            title: "<?= gettext("User Delete Confirmation") ?>",
            message: '<p style="color: red">' +
            '<?= gettext("Please confirm removal of user status from:") ?> <b>' + userName + '</b></p>',
            callback: function (result) {
                if (result) {
                    $.ajax({
                        method: "POST",
                        url: window.CRM.root + "/api/users/" + userId,
                        dataType: "json",
                        encode: true,
                        data: {"_METHOD": "DELETE"}
                    }).done(function (data) {
                        if (data.status == "success")
                            window.location.href = window.CRM.root + "/UserList.php";
                    });
                }
            }
        });
    }

    function restUserLoginCount(userId, userName) {
        bootbox.confirm({
            title: "<?= gettext("Action Confirmation") ?>",
            message: '<p style="color: red">' +
            "<?= gettext("Please confirm reset failed login count") ?>: <b>" + userName + "</b></p>",
            callback: function (result) {
                if (result) {
                    $.ajax({
                        method: "POST",
                        url: window.CRM.root + "/api/users/" + userId + "/login/reset",
                        dataType: "json",
                        encode: true,
                    }).done(function (data) {
                        if (data.status == "success")
                            window.location.href = window.CRM.root + "/UserList.php";
                    });
                }
            }
        });
    }

    function resetUserPassword(userId, userName) {
        bootbox.confirm({
            title: "<?= gettext("Action Confirmation") ?>",
            message: '<p style="color: red">' +
            "<?= gettext("Please confirm the password reset of this user") ?>: <b>" + userName + "</b></p>",
            callback: function (result) {
                if (result) {
                    $.ajax({
                        method: "POST",
                        url: window.CRM.root + "/api/users/" + userId + "/password/reset",
                        dataType: "json",
                        encode: true,
                    }).done(function (data) {
                        if (data.status == "success")
                            showGlobalMessage('<?= gettext("Password reset for") ?> ' + userName, "success");
                    });
                }
            }
        });
    }
</script>

<?php require 'Include/Footer.php' ?>
