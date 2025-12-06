<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("User") . " - " . $user->getFullName();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Login Info") ?></h3>
                <div class="card-tools">
                    <a id="editSettings" href="<?= SystemURLs::getRootPath() ?>/SettingsIndividual.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-pen"></i> <?= _("Edit") ?></a>
                </div>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><strong><?= gettext("Username") ?>:</strong> <?= $user->getUserName() ?></li>
                    <li><strong><?= gettext("Login Count") ?>:</strong> <?= $user->getLoginCount() ?></li>
                    <li><strong><?= gettext("Failed Login") ?>:</strong> <?= $user->getFailedLogins() ?></li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Api Key") ?></h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <input id="apiKey" class="form-control" type="text" readonly value="<?= $user->getApiKey() ?>"/>
                </div>
                <a id="regenApiKey" class="btn btn-warning"><i class="fa-solid fa-repeat"></i> <?= _("Regen API Key") ?></a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("User Interface") ?></h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input user-setting-checkbox" id="boxedLayout" data-layout="layout-boxed" data-css="body" data-setting-name="ui.boxed">
                        <label class="custom-control-label" for="boxedLayout"><strong><?= _("Boxed Layout") ?></strong></label>
                    </div>
                    <small class="form-text text-muted"><?= _("Activate the boxed layout") ?></small>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input user-setting-checkbox" id="toggleSidebar" data-layout="sidebar-collapse" data-css="body" data-setting-name="ui.sidebar">
                        <label class="custom-control-label" for="toggleSidebar"><strong><?= _("Toggle Sidebar") ?></strong></label>
                    </div>
                    <small class="form-text text-muted"><?= _("Toggle the left sidebar's state (open or collapse)") ?></small>
                </div>
                <div class="form-group">
                    <label for="user-locale-setting"><strong><?= _("Locale") ?></strong></label>
                    <select id="user-locale-setting" class="form-control user-setting-select" data-setting-name="ui.locale" data-reload="true">
                    </select>
                    <small class="form-text text-muted"><?= _("Override system locale") ?>: <?= Bootstrapper::getCurrentLocale()->getSystemLocale() ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Permissions") ?></h3>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><strong><?= gettext("Admin") ?>:</strong> <?= $user->isAdmin() ? _("Yes") : _("No") ?></li>
                    <li><strong><?= gettext("Add Records") ?>:</strong> <?= $user->isAdmin() || $user->isAddRecords() ? _("Yes") : _("No") ?></li>
                    <li><strong><?= gettext("Edit Records") ?>:</strong> <?= $user->isAdmin() || $user->isEditRecords() ? _("Yes") : _("No") ?></li>
                    <li><strong><?= gettext("Delete Records") ?>:</strong> <?= $user->isAdmin() || $user->isDeleteRecords() ? _("Yes") : _("No") ?></li>
                    <li><strong><?= gettext("Manage Properties and Classifications") ?>:</strong> <?= $user->isAdmin() || $user->isMenuOptions() ? _("Yes") : _("No") ?></li>
                    <li><strong><?= gettext("Manage Groups and Roles") ?>:</strong> <?= $user->isAdmin() || $user->isManageGroups() ? _("Yes") : _("No") ?></li>
                    <li><strong><?= gettext("Manage Donations and Finance") ?>:</strong> <?= $user->isAdmin() || $user->isFinance() ? _("Yes") : _("No") ?></li>
                    <li><strong><?= gettext("Manage Notes") ?>:</strong> <?= $user->isAdmin() || $user->isNotes() ? _("Yes") : _("No") ?></li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Tables Settings") ?></h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="tablePageLength"><strong><?= _("Page length") ?></strong></label>
                    <select id="tablePageLength" class="form-control user-setting-select" data-setting-name="ui.table.size">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="-1">All</option>
                    </select>
                    <small class="form-text text-muted"><?= _("Change the initial page length (number of rows per page)") ?></small>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    window.CRM.viewUserId = <?= $user->getId() ?>;
</script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/user.js"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
