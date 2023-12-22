<?php

/*******************************************************************************
 *
 *  filename    : UserList.php
 *  last change : 2003-01-07
 *  description : displays a list of all users
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *




 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::securityRedirect("Admin");
}

// Get all the User records
$rsUsers = UserQuery::create()->find();

// Set the page title and include HTML header
$sPageTitle = gettext('System Users');
require 'Include/Header.php';

?>
<!-- Default box -->
<div class="card">
    <div class="card-header">
        <a href="UserEditor.php" class="btn btn-app"><i class="fa fa-user-plus"></i><?= gettext('New User') ?></a>
        <a href="SettingsUser.php" class="btn btn-app"><i class="fa fa-wrench"></i><?= gettext('User Settings') ?></a>
    </div>
</div>
<div class="card collapsed-card">
    <div class="card-header">
        <b class="card-title"><?= _("Global User Settings")?></b>
            <div class="card-tools pull-right">
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i></button>
            </div>
        </b>
    </div>
    <div class="card-body">
        <!-- Custom Tabs -->
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="active"><a href="#tab_1" data-toggle="tab"><?= _("General")?></a></li>
                <li><a href="#tab_2" data-toggle="tab"><?= _("Passwords")?></a></li>
                <li><a href="#tab_3" data-toggle="tab"><?= _("2FA")?></a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane active" id="tab_1">
                    <table class="table table-hover">
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("iSessionTimeout"); ?>
                            <td width="350px"><b><?= _("Session Timeout")?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" value="<?= $config->getValue()?>">
                            </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("iMaxFailedLogins"); ?>
                            <td width="350px">
                                <b><?= _("Max Failed Login")?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" value="<?= $config->getValue()?>">
                            </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("bEnableLostPassword"); ?>
                            <td width="350px"><b><?= _("Enable Password Reset")?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="checkbox" class="system-setting " data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" <?= $config->getBooleanValue() ? "checked" : "" ?>>
                            </td>
                        </tr>
                        <tr>
                            <?php $config = SystemConfig::getConfigItem("bSendUserDeletedEmail"); ?>
                            <td width="350px"><b><?= _("Send email to Deleted Users")?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="checkbox" class="system-setting " data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" <?= $config->getBooleanValue() ? "checked" : "" ?>>
                            </td>
                        </tr>
                    </table>
                </div>
                <!-- /.tab-pane -->
                <div class="tab-pane" id="tab_2">
                    <table class="table table-hover">
                        <tr>
                        <?php $config = SystemConfig::getConfigItem("iMinPasswordLength"); ?>
                            <td width="350px"><b><?= _("Min Password Length")?></b>:
                                <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                            </td>
                            <td>
                                <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" value="<?= $config->getValue()?>">
                            </td>
                        </tr>
                        <tr>
                        <?php $config = SystemConfig::getConfigItem("iMinPasswordChange"); ?>
                        <td>
                            <b><?= _("Min Password Characters Delta")?></b>:
                            <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                        </td>
                        <td>
                            <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" value="<?= $config->getValue()?>">
                        </td>
                    </tr>
                    <tr>
                        <?php $config = SystemConfig::getConfigItem("aDisallowedPasswords"); ?>
                        <td>
                            <b><?= _("Disallowed Passwords")?></b>:
                            <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                        </td>
                        <td>
                            <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" value="<?= $config->getValue()?>" width="300px">
                        </td>
                    </tr>

                    </table>
                </div>
                <!-- /.tab-pane -->
                <div class="tab-pane" id="tab_3">
                    <table class="table table-hover">
                    <tr>
                        <?php $config = SystemConfig::getConfigItem("bEnable2FA"); ?>
                        <td width="350px">
                            <b><?= _("Enable 2FA")?></b>:
                            <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                        </td>
                        <td>
                            <input disabled type="checkbox" class="system-setting " data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" <?= $config->getBooleanValue() ? "checked" : "" ?>
                        </td>
                    </tr>
                    <tr>
                        <?php $config = SystemConfig::getConfigItem("bRequire2FA"); ?>
                        <td>
                            <b><?= _("Require 2FA")?></b>:
                            <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                        </td>
                        <td>
                            <input disabled type="checkbox" class="system-setting " data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" <?= $config->getBooleanValue() ? "checked" : "" ?>
                        </td>
                    </tr>
                    <tr>
                        <?php $config = SystemConfig::getConfigItem("s2FAApplicationName"); ?>
                        <td>
                            <b><?= _("2FA Application Name")?></b>:
                            <a class="setting-tip" data-tip="<?= $config->getTooltip() ?>"><i class="fa fa-fw fa-question-circle"></i></a>
                        </td>
                        <td>
                            <input disabled type="text" class="system-setting form-control" data-setting="<?= $config->getName()?>" data-default-value="<?= $config->getDefault()?>" value="<?= $config->getValue()?>">
                        </td>
                    </tr>

                    </table>
                </div>
                <!-- /.tab-pane -->
            </div>
            <!-- /.tab-content -->
        </div>
        <!-- nav-tabs-custom -->
    </div>

</div>

<div class="card">
    <div class="card-header">
        <b class="card-title"><?= _("User Listing")?></b>
        <div class="card-tools pull-right">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-minus"></i></button>
        </div>
        </h3>
    </div>
    <div class="card-body">
        <table class="table table-hover dt-responsive" id="user-listing-table" style="width:100%;">
            <thead>
            <tr>
                <th><?= gettext('Actions') ?></th>
                <th><?= gettext('Name') ?></th>
                <th align="center"><?= gettext('Last Login') ?></th>
                <th align="center"><?= gettext('Total Logins') ?></th>
                <th align="center"><?= gettext('Failed Logins') ?></th>
                <th align="center"><?= gettext('Password') ?></th>
                <th align="center"><?= gettext('Two Factor Status') ?></th>

            </tr>
            </thead>
            <tbody>
            <?php foreach ($rsUsers as $user) { //Loop through the users?>
                <tr>
                    <td>
                        <a href="UserEditor.php?PersonID=<?= $user->getId() ?>">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </a>&nbsp;&nbsp;
                        <a href="v2/user/<?= $user->getId() ?>">
                            <i class="fa fa-eye" aria-hidden="true"></i>
                        </a>&nbsp;&nbsp;
                        <?php if ($user->getId() != AuthenticationManager::getCurrentUser()->getId()) { ?>
                            <a href="#" onclick="deleteUser(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')">
                                <i class="fa fa-trash-can" aria-hidden="true"></i>
                            </a>
                        <?php } ?>
                    </td>
                    <td>
                        <a href="PersonView.php?PersonID=<?= $user->getId() ?>"> <?= $user->getPerson()->getFullName() ?></a>
                    </td>
                    <td align="center"><?= $user->getLastLogin(SystemConfig::getValue('sDateTimeFormat')) ?></td>
                    <td align="center"><?= $user->getLoginCount() ?></td>
                    <td align="center">
                        <?php if ($user->isLocked()) { ?>
                            <span class="text-red"><?= $user->getFailedLogins() ?></span>
                        <?php } else {
                            echo $user->getFailedLogins();
                        }
                        if ($user->getFailedLogins() > 0) { ?>
                                <a onclick="restUserLoginCount(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')">
                                    <i class="fa fa-eraser" aria-hidden="true"></i>
                                </a>
                        <?php } ?>
                    </td>
                    <td>
                        <a href="v2/user/<?= $user->getId() ?>/changePassword"><i class="fa fa-wrench"></i></a
                        >&nbsp;&nbsp;
                        <?php if ($user->getId() != AuthenticationManager::getCurrentUser()->getId() && !empty($user->getEmail())) {
                            ?>
                            <a href="#" onclick="resetUserPassword(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')">
                                <i class="fa fa-paper-plane"></i></a>
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
    <!-- /.box-body -->
</div>
<!-- /.box -->

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/users.js" ></script>
<?php require 'Include/Footer.php' ?>
