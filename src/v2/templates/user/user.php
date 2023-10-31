<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("User") . " - " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><?= _("Login Info") ?></div>
                <div class="card-tools">
                    <a id="editSettings" href="<?= SystemURLs::getRootPath() ?>/SettingsIndividual.php" class="btn btn-primary float-end"><i class="fas fa-user-pen"></i> <?= _("Edit") ?> </a>
                </div>
            </div>
            <div class="card-body">
                <li><b><?= gettext("Username") ?>:</b> <?= $user->getUserName() ?></li>
                <li><b><?= gettext("Login Count") ?>:</b> <?= $user->getLoginCount() ?></li>
                <li><b><?= gettext("Failed Login") ?>:</b> <?= $user->getFailedLogins() ?></li>
            </div>
            <div class="card-header">
                <h4><?= _("Api Key") ?></h4>
            </div>
            <div class="card-body">
                <form>
                    <input id="apiKey" class="form-control" type="text" readonly value="<?= $user->getApiKey() ?>"/>
                </form>
                <br/>
                <p/>
                <a id="regenApiKey" class="btn btn-warning"><i class="fa fa-repeat"></i> <?= _("Regen API Key")?> </a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
            <div class="card-title"><?= _("Permissions") ?></div>
            </div>
            <div class="card-body">
                <li><b><?= gettext("Admin") ?>:</b> <?= $user->isAdmin() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Add Records") ?>:</b> <?= $user->isAdmin() || $user->isAddRecords() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Edit Records") ?>:</b> <?= $user->isAdmin() || $user->isEditRecords() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Delete Records") ?>:</b> <?= $user->isAdmin() ||  $user->isDeleteRecords() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Manage Properties and Classifications") ?>:</b> <?= $user->isAdmin() || $user->isMenuOptions() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Manage Groups and Roles") ?>:</b> <?= $user->isAdmin() || $user->isManageGroups() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Manage Donations and Finance") ?>:</b> <?= $user->isAdmin() || $user->isFinance() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Manage Notes") ?>:</b> <?= $user->isAdmin() || $user->isNotes() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Canvasser") ?>:</b> <?= $user->isAdmin() || $user->isCanvasser() ? _("Yes") : _("No") ?></li>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><?= _("User Interface") ?></div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="control-sidebar-subheading"><input type="checkbox" data-layout="layout-boxed" data-css="body" class="pull-right user-setting-checkbox" data-setting-name="ui.boxed"> <b><?= _("Boxed Layout")?></b></label>
                    <p><?= _("Activate the boxed layout")?></p>
                </div>
                <div class="form-group">
                    <label class="control-sidebar-subheading"><input type="checkbox" data-layout="sidebar-collapse" data-css="body" class="pull-right user-setting-checkbox" data-setting-name="ui.sidebar"> <b><?= _("Toggle Sidebar")?></b></label>
                    <p><?= _("Toggle the left sidebar's state (open or collapse)")?></p>
                </div>
                <div class="form-group">
                    <label class="control-sidebar-subheading">
                        <b><?= _("Locale")?></b></label>
                        <select id="user-locale-setting" class="pull-right user-setting-select" data-setting-name="ui.locale">
                        </select>
                    <p><?= _("Override system locale")?>: <?= Bootstrapper::getCurrentLocale()->getName() ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h4><?= _("Tables Settings") ?></h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="control-sidebar-subheading">
                        <select class="pull-right user-setting-select" data-setting-name="ui.table.size">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="-1">All</option>
                        </select>
                        <b><?= _("Page length")?></b>
                    </label>
                    <p><?= _("Change the initial page length (number of rows per page)")?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    window.CRM.viewUserId = <?= $user->getId() ?>;
</script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/user.js"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
