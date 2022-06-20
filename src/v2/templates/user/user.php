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
                <div class="pull-right">
                    <a id="editSettings" href="<?= SystemURLs::getRootPath() ?>/SettingsIndividual.php" class="btn btn-primary"><i class="fas fa-user-pen"></i> <?= _("Edit") ?> </a>
                </div>
                <h4><?= _("Login Info") ?></h4>
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
                <h4><?= _("Permissions") ?></h4>
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
                <h4><?= _("Skins") ?></h4>
            </div>
            <div class="card-body">
                <ul class="list-unstyled clearfix">
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-blue" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)"
                           class="clearfix full-opacity-hover">
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 7px; background: #367fa9"></span>
                                <span class="bg-light-blue" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #222d32"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin"><?= _("Blue")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-black" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div style="box-shadow: 0 0 2px rgba(0,0,0,0.1)" class="clearfix">
                                <span style="display:block; width: 20%; float: left; height: 7px; background: #fefefe"></span>
                                <span style="display:block; width: 80%; float: left; height: 7px; background: #fefefe"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #222"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin"><?= _("Black")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-purple" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div><span style="display:block; width: 20%; float: left; height: 7px;" class="bg-purple-active"></span>
                                <span class="bg-purple" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #222d32"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin"><?= _("Purple")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-green" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 7px;" class="bg-green-active"></span>
                                <span class="bg-green" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #222d32"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin"><?= _("Green")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-red" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 7px;" class="bg-red-active"></span>
                                <span class="bg-red" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #222d32"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin"><?= _("Red")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-yellow" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div><span style="display:block; width: 20%; float: left; height: 7px;" class="bg-yellow-active"></span>
                                <span class="bg-yellow" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div><span style="display:block; width: 20%; float: left; height: 20px; background: #222d32"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin"><?= _("Yellow")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-blue-light" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 7px; background: #367fa9"></span>
                                <span class="bg-light-blue" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div><span style="display:block; width: 20%; float: left; height: 20px; background: #f9fafc"></span>
                                <span
                                    style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin" style="font-size: 12px"><?= _("Blue Light")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-black-light" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)"
                           class="clearfix full-opacity-hover">
                            <div style="box-shadow: 0 0 2px rgba(0,0,0,0.1)" class="clearfix"><span style="display:block; width: 20%; float: left; height: 7px; background: #fefefe"></span>
                                <span style="display:block; width: 80%; float: left; height: 7px; background: #fefefe"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #f9fafc"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin" style="font-size: 12px"><?= _("Black Light")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-purple-light" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 7px;" class="bg-purple-active"></span>
                                <span class="bg-purple" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #f9fafc"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin" style="font-size: 12px"><?= _("Purple Light")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-green-light" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 7px;" class="bg-green-active"></span>
                                <span class="bg-green" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #f9fafc"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin" style="font-size: 12px"><?= _("Green Light")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-red-light" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 7px;" class="bg-red-active"></span>
                                <span class="bg-red" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 20px; background: #f9fafc"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin" style="font-size: 12px"><?= _("Red Light")?></p>
                    </li>
                    <li style="float:left; width: 33.33333%; padding: 5px;">
                        <a href="javascript:void(0)" data-skin="skin-yellow-light" style="display: block; box-shadow: 0 0 3px rgba(0,0,0,0.4)" class="clearfix full-opacity-hover">
                            <div>
                                <span style="display:block; width: 20%; float: left; height: 7px;" class="bg-yellow-active"></span>
                                <span class="bg-yellow" style="display:block; width: 80%; float: left; height: 7px;"></span>
                            </div>
                            <div><span style="display:block; width: 20%; float: left; height: 20px; background: #f9fafc"></span>
                                <span style="display:block; width: 80%; float: left; height: 20px; background: #f4f5f7"></span>
                            </div>
                        </a>
                        <p class="text-center no-margin" style="font-size: 12px"><?= _("Yellow Light")?></p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h4><?= _("User Interface") ?></h4>
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
                        <select id="user-locale-setting" class="pull-right user-setting-select" data-setting-name="ui.locale">
                        </select>
                        <b><?= _("Locale")?></b></label>
                    <p><?= _("Override system locale")?>: <?= Bootstrapper::GetCurrentLocale()->getSystemLocale() ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
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
