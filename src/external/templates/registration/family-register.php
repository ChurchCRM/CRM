<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Family Registration");
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");

$headerHTML = '<b>Church</b>CRM';
$sHeader = SystemConfig::getValue("sHeader");
$sChurchName = SystemConfig::getValue("sChurchName");

if (!empty($sHeader)) {
    $headerHTML = html_entity_decode($sHeader, ENT_QUOTES);
} elseif (!empty($sChurchName)) {
    $headerHTML = $sChurchName;
}

?>
<style nonce="<?= SystemURLs::getCSPNonce() ?>">
    .bs-stepper .content {
        padding: 15px;
    }
</style>
<div class="register-box" style="width: 75%;">
    <div class="register-logo">
        <a href="<?= SystemURLs::getRootPath() ?>/"><?= $headerHTML ?></a>
        <h3><?= gettext("Register your family") ?></h3>
    </div>
    <div id="registration-stepper" class="bs-stepper">
        <div class="bs-stepper-header" role="tablist">
            <div class="step" data-target="#step-family-info">
                <button type="button" class="step-trigger" role="tab" aria-controls="step-family-info" id="step-family-info-trigger">
                    <span class="bs-stepper-circle">1</span>
                    <span class="bs-stepper-label"><?= gettext("Family Info") ?></span>
                </button>
            </div>
            <div class="line"></div>
            <div class="step" data-target="#step-members">
                <button type="button" class="step-trigger" role="tab" aria-controls="step-members" id="step-members-trigger">
                    <span class="bs-stepper-circle">2</span>
                    <span class="bs-stepper-label"><?= gettext("Members") ?></span>
                </button>
            </div>
            <div class="line"></div>
            <div class="step" data-target="#step-review">
                <button type="button" class="step-trigger" role="tab" aria-controls="step-review" id="step-review-trigger">
                    <span class="bs-stepper-circle">3</span>
                    <span class="bs-stepper-label"><?= gettext("Review") ?></span>
                </button>
            </div>
        </div>
        <div class="bs-stepper-content">
            <form id="registration-form" novalidate>
                <div id="step-family-info" class="content" role="tabpanel" aria-labelledby="step-family-info-trigger">
                <div id="step-family-info" class="content" role="tabpanel" aria-labelledby="step-family-info-trigger">
                    <div class="form-group has-feedback">
                        <span class="fa-solid fa-user form-control-feedback"></span>
                        <input id="familyName" name="familyName" type="text" class="form-control" placeholder="<?= gettext('Family Name') ?>" required>
                        <div class="help-block with-errors"></div>
                    </div>
                    <div class="form-group has-feedback">
                        <span class="fa-solid fa-envelope form-control-feedback"></span>
                        <input id="familyAddress1" name="familyAddress1" class="form-control" placeholder="<?= gettext('Address') ?>" required>
                        <div class="help-block with-errors"></div>
                    </div>
                    <div class="form-group has-feedback">
                        <div class="row">
                            <div class="col-lg-6">
                                <input id="familyCity" name="familyCity" class="form-control" placeholder="<?= gettext('City') ?>"  required value="<?= SystemConfig::getValue('sDefaultCity') ?>">
                                <div class="help-block with-errors"></div>
                            </div>
                            <div class="col-lg-6">
                                <input id="familyStateInput" name="familyState" class="form-control" placeholder="<?= gettext('State') ?>" value="<?= SystemConfig::getValue('sDefaultState') ?>">
                                <select id="familyStateSelect" name="familyState" class="form-control select2 hidden" data-system-default="<?= SystemConfig::getValue('sDefaultState')?>">
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group has-feedback">
                        <div class="row">
                            <div class="col-lg-3">
                                <input id="familyZip" name="familyZip" class="form-control" placeholder="<?= gettext('Zip') ?>" value="<?= SystemConfig::getValue('sDefaultZip') ?>">
                            </div>
                            <div class="col-lg-9">
                                <select id="familyCountry" name="familyCountry" class="form-control select2" data-system-default="<?= SystemConfig::getValue('sDefaultCountry')?>">
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group has-feedback">
                        <input id="familyHomePhone" name="familyHomePhone" class="form-control" placeholder="<?= gettext('Home Phone') ?>"  data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat') ?>"' data-mask>
                        <span class="fa-solid fa-phone form-control-feedback"></span>
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
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" onclick="registrationStepper.next()"><?= gettext('Next') ?></button>
                    </div>
                </div>
                <div id="step-members" class="content" role="tabpanel" aria-labelledby="step-members-trigger">
                <div id="step-members" class="content" role="tabpanel" aria-labelledby="step-members-trigger">
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
                                            <div class="help-block with-errors"></div>
                                        </div>
                                        <div class="col-lg-6">
                                            <input id="memberLastName-<?= $x ?>" class="form-control required" maxlength="50" placeholder="<?= gettext('Last Name') ?>" required>
                                            <div class="help-block with-errors"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group has-feedback">
                                    <div class="input-group">
                                        <div class="input-group-addon">
                                            <i class="fa-solid fa-envelope"></i>
                                        </div>
                                        <input id="memberEmail-<?= $x ?>" class="form-control" maxlength="50" placeholder="<?= gettext('Email') ?>" type="email">
                                    </div>
                                </div>
                                <div class="form-group has-feedback">
                                    <div class="row">
                                        <div class="col-lg-4">
                                            <div class="input-group">
                                                <div class="input-group-addon">
                                                    <i class="fa-solid fa-phone"></i>
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
                                                    <i class="fa-solid fa-birthday-cake"></i>
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
                    <div class="mt-3">
                        <button type="button" class="btn btn-secondary" onclick="registrationStepper.previous()"><?= gettext('Previous') ?></button>
                        <button type="button" class="btn btn-primary" onclick="registrationStepper.next()"><?= gettext('Next') ?></button>
                    </div>
                </div>
                <div id="step-review" class="content" role="tabpanel" aria-labelledby="step-review-trigger">
                <div id="step-review" class="content" role="tabpanel" aria-labelledby="step-review-trigger">
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
                    <div class="mt-3">
                        <button type="button" class="btn btn-secondary" onclick="registrationStepper.previous()"><?= gettext('Previous') ?></button>
                        <button type="button" class="btn btn-success" id="submit-registration"><?= gettext('Submit Registration') ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bs-stepper/bs-stepper.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyRegister.js"></script>

<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
