<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\UserService;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Initialize UserService
$userService = new UserService();

// Get all users and statistics
$rsUsers = $userService->getAllUsers();
$userStats = $userService->getUserStats();
$userSettingsConfig = $userService->getUserSettingsConfig();

?>
<!-- Dashboard Overview -->
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= $userStats['total'] ?></h3>
                <p><?= gettext('Total Users') ?></p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= $userStats['active'] ?></h3>
                <p><?= gettext('Active Users') ?></p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-user-check"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= $userStats['locked'] ?></h3>
                <p><?= gettext('Locked Users') ?></p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-lock"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= $userStats['twoFactor'] ?></h3>
                <p><?= gettext('2FA Enabled') ?></p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-shield-alt"></i>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <a href="<?= SystemURLs::getRootPath() ?>/UserEditor.php" class="btn btn-success mr-2">
                    <i class="fa-solid fa-user-plus mr-1"></i><?= gettext('Add New') . ' ' . gettext('User') ?>
                </a>
                <a href="<?= SystemURLs::getRootPath() ?>/SettingsUser.php" class="btn btn-primary">
                    <i class="fa-solid fa-cog mr-1"></i><?= gettext('User Settings') ?>
                </a>
                <button type="button" class="btn btn-info" data-toggle="collapse" data-target="#userSettingsPanel" aria-expanded="false">
                    <i class="fa-solid fa-sliders mr-1"></i><?= gettext('Quick Settings') ?>
                </button>
            </div>
        </div>
    </div>
</div>
<!-- User Settings Panel -->
<div class="collapse" id="userSettingsPanel"></div>

<!-- System Settings Panel Component -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-list mr-2"></i><?= _('User Management') ?></h3>
        <div class="card-tools">
            <span class="badge badge-info"><?= $userStats['total'] ?> <?= gettext('total') ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover w-100" id="user-listing-table">
                <thead>
                    <tr>
                        <th><?= gettext('Name') ?></th>
                        <th><?= gettext('Login Name') ?></th>
                        <th class="text-center"><?= gettext('Last Login') ?></th>
                        <th class="text-center"><?= gettext('Total Logins') ?></th>
                        <th class="text-center"><?= gettext('Failed Logins') ?></th>
                        <th class="text-center"><?= gettext('Two Factor Status') ?></th>
                        <th class="text-center"><?= gettext('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rsUsers as $user) { //Loop through the users
                    ?>
                        <tr>
                            <td>
                                <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $user->getId() ?>"> <?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?></a>
                            </td>
                            <td>
                                <code class="text-muted"><?= InputUtils::escapeHTML($user->getUserName()) ?></code>
                            </td>
                            <td class="text-center"><?= $user->getLastLogin(SystemConfig::getValue('sDateTimeFormat')) ?></td>
                            <td class="text-center"><?= $user->getLoginCount() ?></td>
                            <td class="text-center">
                                <?php if ($user->isLocked()) { ?>
                                    <span class="text-red"><?= $user->getFailedLogins() ?></span>
                                <?php } else {
                                    echo $user->getFailedLogins();
                                } ?>
                            </td>
                            <td class="text-center">
                                <?= $user->is2FactorAuthEnabled() ? gettext("Enabled") : gettext("Disabled") ?>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fa-solid fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/UserEditor.php?PersonID=<?= $user->getId() ?>">
                                            <i class="fa-solid fa-pen"></i> <?= gettext('Edit User') ?>
                                        </a>
                                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $user->getId() ?>">
                                            <i class="fa-solid fa-eye"></i> <?= gettext('View Details') ?>
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $user->getId() ?>/changePassword">
                                            <i class="fa-solid fa-wrench"></i> <?= gettext('Change Password') ?>
                                        </a>
                                        <?php if ($user->getId() != AuthenticationManager::getCurrentUser()->getId() && !empty($user->getEmail())) { ?>
                                            <a class="dropdown-item" href="#" onclick="resetUserPassword(<?= $user->getId() ?>, '<?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?>')">
                                                <i class="fa-solid fa-paper-plane"></i> <?= gettext('Reset Password via Email') ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($user->getFailedLogins() > 0) { ?>
                                            <a class="dropdown-item" onclick="restUserLoginCount(<?= $user->getId() ?>, '<?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?>')">
                                                <i class="fa-solid fa-eraser"></i> <?= gettext('Reset Failed Logins') ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($user->is2FactorAuthEnabled()) { ?>
                                            <a class="dropdown-item" onclick="disableUserTwoFactorAuth(<?= $user->getId() ?>, '<?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?>')">
                                                <i class="fa-solid fa-shield-alt"></i> <?= gettext('Disable 2FA') ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($user->getId() != AuthenticationManager::getCurrentUser()->getId()) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?= $user->getId() ?>, '<?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?>')">
                                                <i class="fa-solid fa-trash-can"></i> <?= gettext('Delete User') ?>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php
                    } ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- /.box-body -->
</div>
<!-- /.box -->

<script src="<?= SystemURLs::assetVersioned('/skin/js/users.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    // Initialize the user settings panel
    window.CRM.settingsPanel.init({
        container: '#userSettingsPanel',
        title: i18next.t('User Settings'),
        icon: 'fa-solid fa-user-cog',
        headerClass: 'bg-primary',
        settings: <?= json_encode($userSettingsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        onSave: function() {
            // Reload page after settings save to reflect changes
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        }
    });
});
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
