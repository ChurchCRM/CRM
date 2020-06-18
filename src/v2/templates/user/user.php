<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("User") . " - " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4>Api Key</h4>
            </div>
            <div class="box-body">
                <form>
                    <input id="apiKey" class="form-control" type="text" readonly value="<?= $user->getApiKey() ?>"/>
                </form>
                <br/>
                <p/>
                <a id="regenApiKey" class="btn btn-warning"><i class="fa fa-repeat"></i> Regen API Key </a>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="box">
            <div class="box-header">
                <h4><?= _("Login Info") ?></h4>
            </div>
            <div class="box-body">
                <li><b><?= gettext("Username") ?>:</b> <?= $user->getUserName() ?></li>
                <li><b><?= gettext("Login Count") ?>:</b> <?= $user->getLoginCount() ?></li>
                <li><b><?= gettext("Failed Login") ?>:</b> <?= $user->getFailedLogins() ?></li>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4><?= _("Permissions") ?></h4>
            </div>
            <div class="box-body">
                <li><b><?= gettext("Admin") ?>:</b> <?= $user->isAdmin() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Add Records") ?>:</b> <?= $user->isAddRecords() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Edit Records") ?>:</b> <?= $user->isEditRecords() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Delete Records") ?>:</b> <?= $user->isDeleteRecords() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Manage Properties and Classifications") ?>:</b> <?= $user->isMenuOptions() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Manage Groups and Roles") ?>:</b> <?= $user->isManageGroups() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Manage Donations and Finance") ?>:</b> <?= $user->isFinance() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Manage Notes") ?>:</b> <?= $user->isNotes() ? _("Yes") : _("No") ?></li>
                <li><b><?= gettext("Canvasser") ?>:</b> <?= $user->isCanvasser() ? _("Yes") : _("No") ?></li>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h4><?= _("Skins") ?></h4>
            </div>
            <div class="box-body">
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
                        <p class="text-center no-margin">Blue</p>
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
                        <p class="text-center no-margin">Black</p>
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
                        <p class="text-center no-margin">Purple</p>
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
                        <p class="text-center no-margin">Green</p>
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
                        <p class="text-center no-margin">Red</p>
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
                        <p class="text-center no-margin">Yellow</p>
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
                        <p class="text-center no-margin" style="font-size: 12px">Blue Light</p>
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
                        <p class="text-center no-margin" style="font-size: 12px">Black Light</p>
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
                        <p class="text-center no-margin" style="font-size: 12px">Purple Light</p>
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
                        <p class="text-center no-margin" style="font-size: 12px">Green Light</p>
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
                        <p class="text-center no-margin" style="font-size: 12px">Red Light</p>
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
                        <p class="text-center no-margin" style="font-size: 12px">Yellow Light</p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
<script>
    window.CRM.viewUserId = <?= $user->getId() ?>;
</script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/user.js"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
