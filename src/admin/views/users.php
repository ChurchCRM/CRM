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
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-solid fa-users"></i>
                        </span>
                            <span class="bg-info text-white avatar rounded-circle">
                                <i class="fa-solid fa-users icon"></i>
                            </span>
                    </div>
                    <div class="col">
                        <div class="h3 m-0"><?= $userStats['total'] ?></div>
                        <div class="text-muted"><?= gettext('Total Users') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-check"></i>
                        </span>
                            <span class="bg-success text-white avatar rounded-circle">
                                <i class="fa-solid fa-user-check icon"></i>
                            </span>
                    </div>
                    <div class="col">
                        <div class="h3 m-0"><?= $userStats['active'] ?></div>
                        <div class="text-muted"><?= gettext('Active Users') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                            <span class="bg-warning text-white avatar rounded-circle">
                                <i class="fa-solid fa-lock icon"></i>
                            </span>
                    </div>
                    <div class="col">
                        <div class="h3 m-0"><?= $userStats['locked'] ?></div>
                        <div class="text-muted"><?= gettext('Locked Users') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-shield-alt"></i>
                        </span>
                            <span class="bg-primary text-white avatar rounded-circle">
                                <i class="fa-solid fa-shield-alt icon"></i>
                            </span>
                    </div>
                    <div class="col">
                        <div class="h3 m-0"><?= $userStats['twoFactor'] ?></div>
                        <div class="text-muted"><?= gettext('2FA Enabled') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <a href="<?= SystemURLs::getRootPath() ?>/UserEditor.php" class="btn btn-success me-2">
                    <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add New') . ' ' . gettext('User') ?>
                </a>
                <a href="<?= SystemURLs::getRootPath() ?>/SettingsUser.php" class="btn btn-primary">
                    <i class="fa-solid fa-cog me-1"></i><?= gettext('User Settings') ?>
                </a>
                <button type="button" class="btn btn-info" data-bs-toggle="collapse" data-bs-target="#userSettingsPanel" aria-expanded="false">
                    <i class="fa-solid fa-sliders me-1"></i><?= gettext('Quick Settings') ?>
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
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-list me-2"></i><?= _('User Management') ?></h3>
        <div class="card-tools ms-auto">
            <span class="badge bg-info"><?= $userStats['total'] ?> <?= gettext('total') ?></span>
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
                        <th class="text-center"><?= gettext('Failed Logins') ?></th>
                        <th class="text-center"><?= gettext('2FA') ?></th>
                        <th class="text-center"><?= gettext('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rsUsers as $user) { ?>
                        <tr>
                            <td>
                                <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $user->getId() ?>"><?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?></a>
                            </td>
                            <td>
                                <code><?= InputUtils::escapeHTML($user->getUserName()) ?></code>
                            </td>
                            <td class="text-center"><?= $user->getLastLogin(SystemConfig::getValue('sDateTimeFormat')) ?></td>
                            <td class="text-center">
                                <?php if ($user->getFailedLogins() > 0) { ?>
                                    <span class="badge <?= $user->isLocked() ? 'bg-danger' : 'bg-warning' ?>"><?= $user->getFailedLogins() ?></span>
                                <?php } else { ?>
                                    <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                            <td class="text-center">
                                <?php if ($user->is2FactorAuthEnabled()) { ?>
                                    <span class="badge bg-success"><?= gettext('Enabled') ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary"><?= gettext('Not enrolled') ?></span>
                                <?php } ?>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fa-solid fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
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
</div>
<!-- /.box -->

<script src="<?= SystemURLs::assetVersioned('/skin/js/users.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    // Initialize the user settings panel
    window.CRM.settingsPanel.init({
        container: '#userSettingsPanel',
        title: i18next.t('Quick Settings'),
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
