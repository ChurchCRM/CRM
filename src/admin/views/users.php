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
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="mb-1">
                <i class="fa-solid fa-user-shield text-primary"></i> <?= gettext('System Users') ?>
            </h2>
            <p class="text-muted mb-0"><?= gettext('Manage system users, permissions, and two-factor authentication settings') ?></p>
        </div>
    </div>

    <!-- Stat Cards Row -->
<div class="row mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $userStats['total'] ?></div>
                        <div class="text-muted"><?= gettext('Total Users') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-check icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $userStats['active'] ?></div>
                        <div class="text-muted"><?= gettext('Active Users') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-lock icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $userStats['locked'] ?></div>
                        <div class="text-muted"><?= gettext('Locked Users') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-shield-alt icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $userStats['twoFactor'] ?></div>
                        <div class="text-muted"><?= gettext('2FA Enabled') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Settings Panel Component -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<!-- Quick Actions -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-bolt me-2"></i><?= gettext('Quick Actions') ?></h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap" style="gap: .5rem;">
            <a href="<?= SystemURLs::getRootPath() ?>/UserEditor.php" class="btn btn-success">
                <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add User') ?>
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

<!-- User Settings Panel (collapsible) -->
<div class="collapse mb-3" id="userSettingsPanel"></div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-list me-2"></i><?= gettext('Users') ?></h3>
        <div class="card-tools ms-auto">
            <span class="badge bg-info text-white"><?= $userStats['total'] ?> <?= gettext('total') ?></span>
        </div>
    </div>
    <div class="card-body" style="overflow: visible;">
        <table class="table table-hover w-100" id="user-listing-table">
                <thead>
                    <tr>
                        <th><?= gettext('Name') ?></th>
                        <th><?= gettext('Login Name') ?></th>
                        <th class="text-center"><?= gettext('Last Login') ?></th>
                        <th class="text-center"><?= gettext('Failed Logins') ?></th>
                        <th class="text-center"><?= gettext('2FA') ?></th>
                        <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
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
                                    <span class="badge text-white <?= $user->isLocked() ? 'bg-danger' : 'bg-warning' ?>"><?= $user->getFailedLogins() ?></span>
                                <?php } else { ?>
                                    <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                            <td class="text-center">
                                <?php if ($user->is2FactorAuthEnabled()) { ?>
                                    <span class="badge rounded-pill bg-success text-white"><i class="fa-solid fa-shield-check me-1"></i><?= gettext('Enabled') ?></span>
                                <?php } else { ?>
                                    <span class="badge rounded-pill bg-danger text-white"><i class="fa-solid fa-shield-slash me-1"></i><?= gettext('Disabled') ?></span>
                                <?php } ?>
                            </td>
                            <td class="w-1">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/UserEditor.php?PersonID=<?= $user->getId() ?>">
                                            <i class="ti ti-pencil me-2"></i><?= gettext('Edit User') ?>
                                        </a>
                                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $user->getId() ?>">
                                            <i class="ti ti-eye me-2"></i><?= gettext('View Details') ?>
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $user->getId() ?>/changePassword">
                                            <i class="ti ti-tool me-2"></i><?= gettext('Change Password') ?>
                                        </a>
                                        <?php if ($user->getId() != AuthenticationManager::getCurrentUser()->getId() && !empty($user->getEmail())) { ?>
                                            <a class="dropdown-item" href="#" onclick="resetUserPassword(<?= $user->getId() ?>, '<?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?>')">
                                                <i class="ti ti-send me-2"></i><?= gettext('Reset Password via Email') ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($user->getFailedLogins() > 0) { ?>
                                            <a class="dropdown-item" onclick="restUserLoginCount(<?= $user->getId() ?>, '<?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?>')">
                                                <i class="ti ti-eraser me-2"></i><?= gettext('Reset Failed Logins') ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($user->is2FactorAuthEnabled()) { ?>
                                            <a class="dropdown-item" onclick="disableUserTwoFactorAuth(<?= $user->getId() ?>, '<?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?>')">
                                                <i class="ti ti-shield-off me-2"></i><?= gettext('Disable 2FA') ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($user->getId() != AuthenticationManager::getCurrentUser()->getId()) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?= $user->getId() ?>, '<?= InputUtils::escapeHTML($user->getPerson()->getFullName()) ?>')">
                                                <i class="ti ti-trash me-2"></i><?= gettext('Delete User') ?>
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
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
