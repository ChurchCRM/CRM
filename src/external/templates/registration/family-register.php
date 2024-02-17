<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

// Set the page title and include HTML header
$sPageTitle = gettext("Family Registration");
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");


$headerHTML = '<b>Church</b>CRM';
$sHeader = SystemConfig::getValue("sHeader");
$sChurchName = SystemConfig::getValue("sChurchName");
if (!empty($sHeader)) {
    $headerHTML = html_entity_decode($sHeader, ENT_QUOTES);
} else if (!empty($sChurchName)) {
    $headerHTML = $sChurchName;
}

?>
<style>
    .wizard .content > .body {
        width: 100%;
        height: auto;
        padding: 15px;
        position: relative;
    }
</style>
<div class="register-box" style="width: 75%;">
    <div class="register-logo">
        <a href="<?= SystemURLs::getRootPath() ?>/"><?= $headerHTML ?></a>
        <h3><?= gettext("Register your family") ?></h3>
    </div>
    <form id="registration-form">
        <div id="wizard">
            <h2><?= gettext("Family Info") ?></h2>
            <section>
                <div class="form-group has-feedback">
                    <span class="fa fa-user form-control-feedback"></span>
                    <input id="familyName" name="familyName" type="text" class="form-control" placeholder="<?= gettext('Family Name') ?>" required>
                </div>
                <div class="form-group has-feedback">
                    <span class="fa fa-envelope form-control-feedback"></span>
                    <input id="familyAddress1" name="familyAddress1" class="form-control" placeholder="<?= gettext('Address') ?>" required>
                </div>
                <div class="form-group has-feedback">
                    <div class="row">
                        <div class="col-lg-6">
                            <input id="familyCity" name="familyCity" class="form-control" placeholder="<?= gettext('City') ?>"  required value="<?= SystemConfig::getValue('sDefaultCity') ?>">
                        </div>
                        <div class="col-lg-6">
                            <input id="familyStateInput" name="familyState" class="form-control" placeholder="<?= gettext('State') ?>" required value="<?= SystemConfig::getValue('sDefaultState') ?>">
                            <select id="familyStateSelect" name="familyState" class="form-control select2 hidden" data-system-default="<?= SystemConfig::getValue('sDefaultState')?>">
                            </select>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group has-feedback">
                    <div class="row">
                        <div class="col-lg-3">
                            <input id="familyZip" name="familyZip" class="form-control" placeholder="<?= gettext('Zip') ?>" required>
                        </div>
                        <div class="col-lg-9">
                            <select id="familyCountry" name="familyCountry" class="form-control select2" data-system-default="<?= SystemConfig::getValue('sDefaultCountry')?>">
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group has-feedback">
                    <input id="familyHomePhone" name="familyHomePhone" class="form-control" placeholder="<?= gettext('Home Phone') ?>"  data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat') ?>"' data-mask>
                    <span class="fa fa-phone form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <label><?= gettext('How many people are in your family') ?></label>
                    <select id="familyCount" name="familyCount" class="form-control">
                        <option>1</option>
                        <option>2</option>
                        <option selected>3</option>
                        <option>4</option>
                        <option>5</option>
                        <option>6</option>
                        <option>7</option>
                        <option>8</option>
                    </select>
                </div>
            </section>
            <h2><?= gettext("Members") ?></h2>
            <section>
                <?php for ($x = 1; $x <= 8; $x++) { ?>
                    <div id="memberBox<?= $x ?>" class="box">
                        <div class="card-header with-border">
                            <h4 class="card-title">
                                <?= gettext("Family Member") . " #" . $x ?>
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group has-feedback">
                                <div class="row">
                                    <div class="col-lg-8">
                                        <select id="memberRole-<?= $x ?>" class="form-control">
                                            <?php
                                            switch ($x) {
                                                case 1:
                                                    $defaultRole = SystemConfig::getValue('sDirRoleHead');
                                                    break;
                                                case 2:
                                                    $defaultRole = SystemConfig::getValue('sDirRoleSpouse');
                                                    break;
                                                default:
                                                    $defaultRole = SystemConfig::getValue('sDirRoleChild');
                                                    break;
                                            }
                                            foreach ($familyRoles as $role) { ?>
                                                <option value="<?= $role->getOptionId() ?>" <?php if ($role->getOptionId() == $defaultRole) {
                                                    echo "selected";
                                                               } ?>><?= $role->getOptionName() ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-lg-4">
                                        <select id="memberGender-<?= $x ?>" class="form-control">
                                            <option value="1"><?= gettext('Male') ?></option>
                                            <option value="2"><?= gettext('Female') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group has-feedback">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <input id="memberFirstName-<?= $x ?>" class="form-control required" maxlength="50" placeholder="<?= gettext('First Name') ?>" required>
                                    </div>
                                    <div class="col-lg-6">
                                        <input id="memberLastName-<?= $x ?>" class="form-control required" maxlength="50" placeholder="<?= gettext('Last Name') ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group has-feedback">
                                <div class="input-group">
                                    <div class="input-group-addon">
                                        <i class="fa fa-envelope"></i>
                                    </div>
                                    <input id="memberEmail-<?= $x ?>" class="form-control" maxlength="50" placeholder="<?= gettext('Email') ?>" type="email">
                                </div>
                            </div>
                            <div class="form-group has-feedback">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="input-group">
                                            <div class="input-group-addon">
                                                <i class="fa fa-phone"></i>
                                            </div>
                                            <select id="memberPhoneType-<?= $x ?>" class="form-control">
                                                <option value="mobile"><?= gettext('Mobile') ?></option>
                                                <option value="home"><?= gettext('Home') ?></option>
                                                <option value="work"><?= gettext('Work') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="input-group">
                                            <input id="memberPhone-<?= $x ?>" class="form-control" maxlength="30" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat')?>"' data-mask
                                                   placeholder="<?= gettext('Phone') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group has-feedback">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="input-group">
                                            <div class="input-group-addon">
                                                <i class="fa fa-birthday-cake"></i>
                                            </div>
                                            <input type="text" class="form-control inputDatePicker" id="memberBirthday-<?= $x ?>">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <label>
                                            <input type="checkbox" id="memberHideAge-<?= $x ?>">&nbsp; <?= gettext('Hide Age') ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </section>
            <h2><?= gettext("Review")?></h2>
            <section>
                <h3 class="text-center"><span id="displayFamilyName"></span> <?= gettext("Family")?> </h3>
                <p>
                    <strong><?= gettext("Address")?>:</strong> <span id="displayFamilyAddress"></span> <br/>
                    <strong><?= gettext("Phone")?>:</strong> <span id="displayFamilyPhone"></span> <br/>
                </p>
                <br/>

                <table class="table table-striped table-bordered table-responsive">
                    <?php for ($x = 1; $x <= 8; $x++) { ?>
                        <tr id="displayFamilyPerson<?= $x ?>">
                            <td><span id="displayFamilyPersonFName<?= $x ?>"></span></td>
                            <td><span id="displayFamilyPersonLName<?= $x ?>"></span></td>
                            <td><span id="displayFamilyPersonEmail<?= $x ?>"></span></td>
                            <td><span id="displayFamilyPersonPhone<?= $x ?>"></span></td>
                            <td><span id="displayFamilyPersonBDay<?= $x ?>"></span></td>
                        </tr>
                    <?php } ?>
                </table>

            </section>
        </div>
    </form>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery.steps/jquery.steps.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery-validation/jquery.validate.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyRegister.js"></script>

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
