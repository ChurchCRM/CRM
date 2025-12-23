<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

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
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/family-register.min.css') ?>">
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = {
        root: "<?= SystemURLs::getRootPath() ?>",
        churchWebSite: "<?= SystemURLs::getRootPath() ?>/"
    };
</script>
<div class="register-box" style="width: 90%; max-width: 900px;">
    <div class="register-logo text-center mb-4">
        <a href="<?= SystemURLs::getRootPath() ?>/" class="h2"><?= InputUtils::escapeHTML($headerHTML) ?></a>
        <p class="text-muted mt-2"><?= gettext("Join our community by registering your family") ?></p>
    </div>
    <div class="card registration-card">
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
                        <div class="step-header">
                            <h4 class="text-center"><?= gettext('Family Information') ?></h4>
                        </div>

                        <div class="form-group">
                            <label for="familyName"><?= gettext('Family Name') ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                </div>
                                <input id="familyName" name="familyName" type="text" class="form-control" placeholder="<?= gettext('Enter family name') ?>" required>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="familyAddress1"><?= gettext('Address') ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-home"></i></span>
                                </div>
                                <input id="familyAddress1" name="familyAddress1" class="form-control" placeholder="<?= gettext('Street address') ?>" required>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="familyCity"><?= gettext('City') ?> <span class="text-danger">*</span></label>
                                <input id="familyCity" name="familyCity" class="form-control" placeholder="<?= gettext('City') ?>" required value="<?= SystemConfig::getValue('sDefaultCity') ?>">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="familyState"><?= gettext('State') ?></label>
                                <div id="familyStateContainer">
                                    <input id="familyState" name="familyState" class="form-control" placeholder="<?= gettext('State') ?>" value="<?= SystemConfig::getValue('sDefaultState') ?>" data-default="<?= SystemConfig::getValue('sDefaultState') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="familyZip"><?= gettext('Zip Code') ?> <span class="text-danger">*</span></label>
                                <input id="familyZip" name="familyZip" class="form-control" placeholder="<?= gettext('Zip') ?>" value="<?= SystemConfig::getValue('sDefaultZip') ?>" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="familyCountry"><?= gettext('Country') ?></label>
                                <select id="familyCountry" name="familyCountry" class="form-control select2" data-system-default="<?= SystemConfig::getValue('sDefaultCountry') ?>">
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="familyHomePhone"><?= gettext('Home Phone') ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                </div>
                                <input id="familyHomePhone" name="familyHomePhone" class="form-control" placeholder="<?= gettext('Home phone number') ?>" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat') ?>"' data-mask required>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="alert alert-info mt-3" role="alert">
                            <small><span class="text-danger">*</span> <?= gettext('Required fields') ?></small>
                        </div>

                        <div class="form-group mt-4 mb-0">
                            <button type="button" class="btn btn-primary btn-lg" id="family-info-next">
                                <?= gettext('Next') ?> <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>
                    <div id="step-members" class="content" role="tabpanel" aria-labelledby="step-members-trigger">
                        <div class="step-header">
                            <h4 class="text-center">
                                <?= gettext('Family Members') ?>
                                <span id="member-count-display" class="family-count-badge"></span>
                            </h4>
                        </div>

                        <!-- Members container where dynamic cards are added -->
                        <div id="members-container"></div>

                        <!-- Member card template (hidden, cloned for each member) -->
                        <template id="member-card-template">
                            <div class="member-card" data-member-index="">
                                <div class="card-header member-card-header-clickable">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <button type="button" class="btn btn-link member-toggle-btn p-0 mr-2" style="cursor: pointer;">
                                                <i class="fa-solid fa-chevron-down"></i>
                                            </button>
                                            <h5 class="mb-0">
                                                <i class="fa-solid fa-user mr-2"></i>
                                                <span class="member-display-name"><?= gettext('Next Member') ?></span>
                                            </h5>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-member-btn" style="display: none;">
                                            <i class="fa-solid fa-trash mr-1"></i><?= gettext('Remove') ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body member-card-body" style="display: none;">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label><?= gettext('First Name') ?> <span class="text-danger">*</span></label>
                                            <input class="form-control member-first-name" maxlength="50" placeholder="<?= gettext('First name') ?>" required>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label><?= gettext('Last Name') ?> <span class="text-danger">*</span></label>
                                            <input class="form-control member-last-name" maxlength="50" placeholder="<?= gettext('Last name') ?>" required>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label><?= gettext('Role in Family') ?></label>
                                            <select class="form-control member-role">
                                                <?php foreach ($familyRoles as $role) { ?>
                                                    <option value="<?= $role->getOptionId() ?>"><?= $role->getOptionName() ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label><?= gettext('Gender') ?></label>
                                            <select class="form-control member-gender">
                                                <option value="1"><?= gettext('Male') ?></option>
                                                <option value="2"><?= gettext('Female') ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label><?= gettext('Email Address') ?></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                            </div>
                                            <input class="form-control member-email" maxlength="50" placeholder="<?= gettext('Email address') ?>" type="email">
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label><?= gettext('Phone Number') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                                </div>
                                                <input class="form-control member-phone" maxlength="30" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat') ?>"' data-mask placeholder="<?= gettext('Phone number') ?>">
                                            </div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label><?= gettext('Phone Type') ?></label>
                                            <select class="form-control member-phone-type">
                                                <option value="mobile"><?= gettext('Mobile') ?></option>
                                                <option value="home"><?= gettext('Home') ?></option>
                                                <option value="work"><?= gettext('Work') ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-7">
                                            <label><?= gettext('Birthday') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fa-solid fa-birthday-cake"></i></span>
                                                </div>
                                                <input type="text" class="form-control inputDatePicker member-birthday" placeholder="<?= gettext('Select date') ?>">
                                            </div>
                                        </div>
                                        <div class="form-group col-md-5">
                                            <label class="d-block">&nbsp;</label>
                                            <div class="custom-control custom-checkbox mt-2">
                                                <input type="checkbox" class="custom-control-input member-hide-age">
                                                <label class="custom-control-label">
                                                    <?= gettext('Hide Age') ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Add member button -->
                        <div class="mb-4">
                            <button type="button" class="btn btn-success btn-lg" id="add-member-btn">
                                <i class="fa-solid fa-plus mr-2"></i><?= gettext('Add Family Member') ?>
                            </button>
                        </div>

                        <div class="alert alert-info" role="alert">
                            <small><span class="text-danger">*</span> <?= gettext('Required fields') ?></small>
                        </div>

                        <div class="form-group mt-4 mb-0">
                            <button type="button" class="btn btn-secondary btn-lg mr-2" id="members-previous">
                                <i class="fa-solid fa-arrow-left mr-2"></i><?= gettext('Previous') ?>
                            </button>
                            <button type="button" class="btn btn-primary btn-lg" id="members-next">
                                <?= gettext('Next') ?> <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>
                    <div id="step-review" class="content" role="tabpanel" aria-labelledby="step-review-trigger">
                        <div class="step-header">
                            <h4 class="text-center"><?= gettext('Review & Submit') ?></h4>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fa-solid fa-home mr-2"></i><span id="displayFamilyName"></span> <?= gettext("Family") ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong><i class="fa-solid fa-map-marker-alt mr-2 text-primary"></i><?= gettext("Address") ?>:</strong><br />
                                            <span id="displayFamilyAddress" class="ml-4"></span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong><i class="fa-solid fa-phone mr-2 text-primary"></i><?= gettext("Phone") ?>:</strong><br />
                                            <span id="displayFamilyPhone" class="ml-4"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="fa-solid fa-users mr-2"></i><?= gettext("Family Members") ?></h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th><?= gettext("First Name") ?></th>
                                                <th><?= gettext("Last Name") ?></th>
                                                <th><?= gettext("Email") ?></th>
                                                <th><?= gettext("Phone") ?></th>
                                                <th><?= gettext("Birthday") ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($x = 1; $x <= 8; $x++) { ?>
                                                <tr id="displayFamilyPerson<?= $x ?>">
                                                    <td><span id="displayFamilyPersonFName<?= $x ?>"></span></td>
                                                    <td><span id="displayFamilyPersonLName<?= $x ?>"></span></td>
                                                    <td><span id="displayFamilyPersonEmail<?= $x ?>"></span></td>
                                                    <td><span id="displayFamilyPersonPhone<?= $x ?>"></span></td>
                                                    <td><span id="displayFamilyPersonBDay<?= $x ?>"></span></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4" role="alert">
                            <i class="fa-solid fa-info-circle mr-2"></i>
                            <?= gettext('Please review all information carefully before submitting. You can go back to make changes if needed.') ?>
                        </div>

                        <div class="form-group mt-4 mb-0">
                            <button type="button" class="btn btn-secondary btn-lg mr-2" id="review-previous">
                                <i class="fa-solid fa-arrow-left mr-2"></i><?= gettext('Previous') ?>
                            </button>
                            <button type="button" class="btn btn-success btn-lg" id="submit-registration">
                                <i class="fa-solid fa-check-circle mr-2"></i><?= gettext('Submit Registration') ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/external/bs-stepper/bs-stepper.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/family-register.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/DropdownManager.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/FamilyRegister.js') ?>"></script>

<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
