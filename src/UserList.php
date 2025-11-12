<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
AuthenticationManager::redirectHomeIfNotAdmin();

// Get all the User records
$rsUsers = UserQuery::create()->find();

$sPageTitle = gettext('System Users');
require_once 'Include/Header.php';

?>
<!-- Default box -->
<div class="card">
    <div class="card-header">
        <a href="UserEditor.php" class="btn btn-app bg-success"><i class="fa-solid fa-user-plus fa-3x"></i><br><?= gettext('New User') ?></a>
        <a href="SettingsUser.php" class="btn btn-app bg-primary"><i class="fa-solid fa-wrench fa-3x"></i><br><?= gettext('User Settings') ?></a>
    </div>
</div>
<div class="card collapsed-card">
    <div class="card-header">
        <b class="card-title"><?= _("Global User Settings") ?></b>
        <div class="card-tools pull-right">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-plus"></i></button>
        </div>
        </b>
    </div>
    <div class="card-body">
        <!-- Custom Tabs (migrated from AdminLTE .nav-tabs-custom to Bootstrap 4 markup) -->
        <div>
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab_1-tab" data-toggle="tab" href="#tab_1" role="tab" aria-controls="tab_1" aria-selected="true"><?= _("General") ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab_2-tab" data-toggle="tab" href="#tab_2" role="tab" aria-controls="tab_2" aria-selected="false"><?= _("Passwords") ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab_3-tab" data-toggle="tab" href="#tab_3" role="tab" aria-controls="tab_3" aria-selected="false"><?= _("2FA") ?></a>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_1" role="tabpanel" aria-labelledby="tab_1-tab">
                    <table class="table table-hover">
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("iSessionTimeout"); ?>
                            <td width="350px"><b><?= _("Session Timeout") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" value="<?= $config->getValue() ?>">
                            </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("iMaxFailedLogins"); ?>
                            <td width="350px">
                                <b><?= _("Max Failed Login") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" value="<?= $config->getValue() ?>">
                            </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("bEnableLostPassword"); ?>
                            <td width="350px"><b><?= _("Enable Password Reset") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="checkbox" class="system-setting " data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" <?= $config->getBooleanValue() ? "checked" : "" ?>>
                            </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("bSendUserDeletedEmail"); ?>
                            <td width="350px"><b><?= _("Send email to Deleted Users") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="checkbox" class="system-setting " data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" <?= $config->getBooleanValue() ? "checked" : "" ?>>
                            </td>
                        </tr>
                    </table>
                </div>
                <!-- /.tab-pane -->
                <div class="tab-pane fade" id="tab_2" role="tabpanel" aria-labelledby="tab_2-tab">
                    <table class="table table-hover">
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("iMinPasswordLength"); ?>
                            <td width="350px"><b><?= _("Min Password Length") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" value="<?= $config->getValue() ?>">
                            </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("iMinPasswordChange"); ?>
                            <td>
                                <b><?= _("Min Password Characters Delta") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" value="<?= $config->getValue() ?>">
                            </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("aDisallowedPasswords"); ?>
                            <td>
                                <b><?= _("Disallowed Passwords") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" value="<?= $config->getValue() ?>" width="300px">
                            </td>
                        </tr>

                    </table>
                </div>
                <!-- /.tab-pane -->
                <div class="tab-pane fade" id="tab_3" role="tabpanel" aria-labelledby="tab_3-tab">
                    <table class="table table-hover">
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("bEnable2FA"); ?>
                            <td width="350px">
                                <b><?= _("Enable 2FA") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="checkbox" class="system-setting " data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" <?= $config->getBooleanValue() ? "checked" : "" ?>
                                    </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("bRequire2FA"); ?>
                            <td>
                                <b><?= _("Require 2FA") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="checkbox" class="system-setting " data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" <?= $config->getBooleanValue() ? "checked" : "" ?>
                                    </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("s2FAApplicationName"); ?>
                            <td>
                                <b><?= _("2FA Application Name") ?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa-solid fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName() ?>" data-default-value="<?= $config->getDefault() ?>" value="<?= $config->getValue() ?>">
                            </td>
                        </tr>

                    </table>
                </div>
                <!-- /.tab-pane -->
            </div>
            <!-- /.tab-content -->
        </div>
        <!-- nav-tabs (converted from nav-tabs-custom) -->
    </div>

</div>

<div class="card">
    <div class="card-header">
        <b class="card-title"><?= _("User Listing") ?></b>
        <div class="card-tools pull-right">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
        </div>
        </h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover w-100" id="user-listing-table">
                <thead>
                    <tr>
                        <th><?= gettext('Actions') ?></th>
                        <th><?= gettext('Name') ?></th>
                        <th class="text-center"><?= gettext('Last Login') ?></th>
                        <th class="text-center"><?= gettext('Total Logins') ?></th>
                        <th class="text-center"><?= gettext('Failed Logins') ?></th>
                        <th class="text-center"><?= gettext('Password') ?></th>
                        <th class="text-center"><?= gettext('Two Factor Status') ?></th>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rsUsers as $user) { //Loop through the users
                    ?>
                        <tr>
                            <td>
                                <a href="UserEditor.php?PersonID=<?= $user->getId() ?>">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                </a>&nbsp;&nbsp;
                                <a href="v2/user/<?= $user->getId() ?>">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>&nbsp;&nbsp;
                                <?php if ($user->getId() != AuthenticationManager::getCurrentUser()->getId()) { ?>
                                    <a href="#" onclick="deleteUser(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                    </a>
                                <?php } ?>
                            </td>
                            <td>
                                <a href="PersonView.php?PersonID=<?= $user->getId() ?>"> <?= $user->getPerson()->getFullName() ?></a>
                            </td>
                            <td class="text-center"><?= $user->getLastLogin(SystemConfig::getValue('sDateTimeFormat')) ?></td>
                            <td class="text-center"><?= $user->getLoginCount() ?></td>
                            <td class="text-center">
                                <?php if ($user->isLocked()) { ?>
                                    <span class="text-red"><?= $user->getFailedLogins() ?></span>
                                <?php } else {
                                    echo $user->getFailedLogins();
                                }
                                if ($user->getFailedLogins() > 0) { ?>
                                    <a onclick="restUserLoginCount(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')">
                                        <i class="fa-solid fa-eraser" aria-hidden="true"></i>
                                    </a>
                                <?php } ?>
                            </td>
                            <td>
                                <a href="v2/user/<?= $user->getId() ?>/changePassword"><i class="fa-solid fa-wrench"></i></a>&nbsp;&nbsp;
                                <?php if ($user->getId() != AuthenticationManager::getCurrentUser()->getId() && !empty($user->getEmail())) {
                                ?>
                                    <a href="#" onclick="resetUserPassword(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')">
                                        <i class="fa-solid fa-paper-plane"></i></a>
                                <?php
                                } ?>
                            </td>
                            <td>
                                <?= $user->is2FactorAuthEnabled() ? gettext("Enabled") : gettext("Disabled") ?>
                                <?php
                                if ($user->is2FactorAuthEnabled()) {
                                ?>
                                    <a onclick="disableUserTwoFactorAuth(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')">Disable</a>
                                <?php
                                }
                                ?>
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

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/users.js"></script>
<?php
require_once 'Include/Footer.php';
