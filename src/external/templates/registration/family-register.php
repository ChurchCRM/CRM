<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext("Family Registration");
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");

?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/family-register.min.css') ?>">
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = {
        root:"<?= SystemURLs::getRootPath() ?>",
        churchWebSite:"<?= SystemURLs::getRootPath() ?>/",
        churchName:"<?= ChurchMetaData::getChurchName() ?>",
        phoneFormats: {
            home:<?= SystemConfig::getValueForJs('sPhoneFormat') ?>,
            cell:<?= SystemConfig::getValueForJs('sPhoneFormatCell') ?>,
            work:<?= SystemConfig::getValueForJs('sPhoneFormatWithExt') ?>
        }
    };
</script>
<div class="register-box" style="max-width: 900px;">
    <div class="register-logo text-center mb-4">
        <a href="<?= SystemURLs::getRootPath() ?>/" class="h2"><?= ChurchMetaData::getChurchName() ?></a>
        <p class="text-body-secondary mt-2"><?= gettext("We're so glad you're here! Register your family in just 3 easy steps.") ?></p>
    </div>
    <div class="card registration-card">
        <div id="registration-stepper" class="bs-stepper">
            <div class="bs-stepper-header" role="tablist">
                <div class="step" data-bs-target="#step-family-info">
                    <button type="button" class="step-trigger" role="tab" aria-controls="step-family-info" id="step-family-info-trigger">
                        <span class="bs-stepper-circle">1</span>
                        <span class="bs-stepper-label"><?= gettext("Family Info") ?></span>
                    </button>
                </div>
                <div class="line"></div>
                <div class="step" data-bs-target="#step-members">
                    <button type="button" class="step-trigger" role="tab" aria-controls="step-members" id="step-members-trigger">
                        <span class="bs-stepper-circle">2</span>
                        <span class="bs-stepper-label"><?= gettext("Members") ?></span>
                    </button>
                </div>
                <div class="line"></div>
                <div class="step" data-bs-target="#step-review">
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
                            <h4 class="text-center"><?= gettext('Tell us about your family') ?></h4>
                            <p class="text-center mb-0 mt-1" style="opacity:0.85; font-size:0.9rem;"><?= gettext("We'll use this to keep in touch and send church updates.") ?></p>
                        </div>

                        <div class="mb-3">
                            <label for="familyName"><?= gettext('Family Name') ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input id="familyName" name="familyName" type="text" class="form-control" placeholder="<?= gettext('Enter family name') ?>" required>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="mb-3">
                            <label for="familyAddress1"><?= gettext('Address') ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-home"></i></span>
                                <input id="familyAddress1" name="familyAddress1" class="form-control" placeholder="<?= gettext('Street address') ?>" required>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label for="familyCity"><?= gettext('City') ?> <span class="text-danger">*</span></label>
                                <input id="familyCity" name="familyCity" class="form-control" placeholder="<?= gettext('City') ?>" required value="<?= SystemConfig::getValueForAttr('sDefaultCity') ?>">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="familyState"><?= gettext('State') ?></label>
                                <div id="familyStateContainer">
                                    <input id="familyState" name="familyState" class="form-control" placeholder="<?= gettext('State') ?>" value="<?= SystemConfig::getValueForAttr('sDefaultState') ?>" data-default="<?= SystemConfig::getValueForAttr('sDefaultState') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label for="familyZip"><?= gettext('Zip Code') ?> <span class="text-danger">*</span></label>
                                <input id="familyZip" name="familyZip" class="form-control" placeholder="<?= gettext('Zip') ?>" value="<?= SystemConfig::getValueForAttr('sDefaultZip') ?>" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="familyCountry"><?= gettext('Country') ?></label>
                                <select id="familyCountry" name="familyCountry" class="form-select" data-system-default="<?= SystemConfig::getValueForAttr('sDefaultCountry') ?>">
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="familyHomePhone"><?= gettext('Home Phone') ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                <input id="familyHomePhone" name="familyHomePhone" class="form-control" placeholder="<?= gettext('Home phone number') ?>" data-inputmask='"mask":"<?= SystemConfig::getValueForAttr('sPhoneFormat') ?>"' data-mask required>
                                <span class="input-group-text gap-2">
                                    <input class="form-check-input mt-0" type="checkbox" id="NoFormat_familyHomePhone" name="NoFormat_familyHomePhone" value="1">
                                    <label class="form-check-label" for="NoFormat_familyHomePhone"><?= gettext('No format') ?></label>
                                </span>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="alert alert-info mt-3" role="alert">
                            <small><span class="text-danger">*</span> <?= gettext('Required fields') ?></small>
                        </div>

                        <div class="mb-3 mt-4 mb-0">
                            <button type="button" class="btn btn-primary" id="family-info-next">
                                <?= gettext('Next') ?> <i class="fa-solid fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    <div id="step-members" class="content" role="tabpanel" aria-labelledby="step-members-trigger">
                        <div class="step-header">
                            <h4 class="text-center">
                                <?= gettext('Who is in your family?') ?>
                                <span id="member-count-display" class="family-count-badge"></span>
                            </h4>
                            <p class="text-center mb-0 mt-1" style="opacity:0.85; font-size:0.9rem;"><?= gettext("Add everyone in your household — adults and children alike.") ?></p>
                        </div>

                        <!-- Members container where dynamic cards are added -->
                        <div id="members-container"></div>

                        <!-- Member card template (hidden, cloned for each member) -->
                        <template id="member-card-template">
                            <div class="member-card" data-member-index="">
                                <div class="card-header member-card-header-clickable user-select-none">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <button type="button" class="btn btn-link member-toggle-btn p-0 me-2">
                                                <i class="fa-solid fa-chevron-down"></i>
                                            </button>
                                            <h5 class="mb-0">
                                                <i class="fa-solid fa-user me-2"></i>
                                                <span class="member-display-name"><?= gettext('Next Member') ?></span>
                                            </h5>
                                        </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-member-btn d-none">
                                            <i class="fa-solid fa-trash me-1"></i><?= gettext('Remove') ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body member-card-body d-none">
                                    <div class="row">
                                        <div class="mb-3 col-md-6">
                                            <label><?= gettext('First Name') ?> <span class="text-danger">*</span></label>
                                            <input class="form-control member-first-name" maxlength="50" placeholder="<?= gettext('First name') ?>" required>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="mb-3 col-md-6">
                                            <label><?= gettext('Last Name') ?> <span class="text-danger">*</span></label>
                                            <input class="form-control member-last-name" maxlength="50" placeholder="<?= gettext('Last name') ?>" required>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="mb-3 col-md-6">
                                            <label><?= gettext('Role in Family') ?></label>
                                            <select class="form-select member-role">
                                                <?php foreach ($familyRoles as $role) { ?>
                                                    <option value="<?= $role->getOptionId() ?>"><?= InputUtils::escapeHTML($role->getOptionName()) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="mb-3 col-md-6">
                                            <label><?= gettext('Gender') ?></label>
                                            <select class="form-select member-gender">
                                                <option value="1"><?= gettext('Male') ?></option>
                                                <option value="2"><?= gettext('Female') ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label><?= gettext('Email Address') ?></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                            <input class="form-control member-email" maxlength="50" placeholder="<?= gettext('Email address') ?>" type="email">
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>

                                    <div class="row">
                                        <div class="mb-3 col-md-6">
                                            <label><?= gettext('Phone Number') ?></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                                <input class="form-control member-phone" maxlength="30" data-inputmask='"mask":"<?= SystemConfig::getValueForAttr('sPhoneFormat') ?>"' data-mask placeholder="<?= gettext('Phone number') ?>" data-phone-format-home="<?= SystemConfig::getValueForAttr('sPhoneFormat') ?>" data-phone-format-cell="<?= SystemConfig::getValueForAttr('sPhoneFormatCell') ?>">
                                                <span class="input-group-text gap-2">
                                                    <input class="form-check-input mt-0 member-phone-noformat" type="checkbox" id="member-phone-noformat" name="member-phone-noformat" value="1">
                                                    <label class="form-check-label member-phone-noformat-label" for="member-phone-noformat"><?= gettext('No format') ?></label>
                                                </span>
                                            </div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="mb-3 col-md-6">
                                            <label><?= gettext('Phone Type') ?></label>
                                            <select class="form-select member-phone-type">
                                                <option value="mobile"><?= gettext('Mobile') ?></option>
                                                <option value="home"><?= gettext('Home') ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="mb-3 col-md-7">
                                            <label><?= gettext('Birthday') ?></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fa-solid fa-cake-candles"></i></span>
                                                <input type="text" class="form-control inputDatePicker member-birthday" placeholder="<?= gettext('Select date') ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3 col-md-5">
                                            <label class="d-block">&nbsp;</label>
                                            <div class="form-check mt-2">
                                                <input type="checkbox" class="form-check-input member-hide-age">
                                                <label class="form-check-label">
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
                            <button type="button" class="btn btn-success" id="add-member-btn">
                                <i class="fa-solid fa-plus me-2"></i><?= gettext('Add Family Member') ?>
                            </button>
                        </div>

                        <div class="alert alert-info" role="alert">
                            <small><span class="text-danger">*</span> <?= gettext('Required fields') ?></small>
                        </div>

                        <div class="mb-3 mt-4 mb-0">
                            <button type="button" class="btn btn-secondary me-2" id="members-previous">
                                <i class="fa-solid fa-arrow-left me-2"></i><?= gettext('Previous') ?>
                            </button>
                            <button type="button" class="btn btn-primary" id="members-next">
                                <?= gettext('Next') ?> <i class="fa-solid fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    <div id="step-review" class="content" role="tabpanel" aria-labelledby="step-review-trigger">
                        <div class="step-header">
                            <h4 class="text-center"><?= gettext('Almost there — review your details') ?></h4>
                            <p class="text-center mb-0 mt-1" style="opacity:0.85; font-size:0.9rem;"><?= gettext("Please check everything looks right before submitting.") ?></p>
                        </div>

                        <div class="card mb-4">
                            <div class="card-status-top bg-primary"></div>
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fa-solid fa-home me-2"></i><span id="displayFamilyName"></span> <?= gettext("Family") ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong><i class="fa-solid fa-location-dot me-2 text-primary"></i><?= gettext("Address") ?>:</strong><br />
                                            <span id="displayFamilyAddress" class="ms-4"></span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong><i class="fa-solid fa-phone me-2 text-primary"></i><?= gettext("Phone") ?>:</strong><br />
                                            <span id="displayFamilyPhone" class="ms-4"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-status-top bg-secondary"></div>
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fa-solid fa-users me-2"></i><?= gettext("Family Members") ?></h5>
                            </div>
                            <div class="card-body p-0">
                                <!-- Desktop/table view (md and up) -->
                                <div class="table-responsive d-none d-md-block">
                                    <table class="table table-hover mb-0">
                                        <thead>
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
                                                <tr id="displayFamilyPerson<?= $x ?>" class="d-none">
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

                                <!-- Mobile/card view (small screens) -->
                                <div class="d-block d-md-none">
                                    <?php for ($x = 1; $x <= 8; $x++) { ?>
                                        <div id="displayFamilyPersonCard<?= $x ?>" class="card mb-3 d-none border-0 shadow-none">
                                            <div class="card-body bg-white p-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i id="displayFamilyPersonCardGenderIcon<?= $x ?>" class="fa-solid fa-user text-primary me-2"></i>
                                                    <h6 class="card-title mb-0">
                                                        <span id="displayFamilyPersonCardFName<?= $x ?>"></span>
                                                        <span id="displayFamilyPersonCardLName<?= $x ?>"></span>
                                                    </h6>
                                                </div>
                                                <div id="displayFamilyPersonCardEmailBlock<?= $x ?>" class="mb-2 d-none">
                                                    <i class="fa-solid fa-envelope text-body-secondary me-2"></i>
                                                    <strong><?= gettext('Email') ?>:</strong>
                                                    <div class="ms-4"><span id="displayFamilyPersonCardEmail<?= $x ?>"></span></div>
                                                </div>
                                                <div id="displayFamilyPersonCardPhoneBlock<?= $x ?>" class="mb-2 d-none">
                                                    <i class="fa-solid fa-phone text-body-secondary me-2"></i>
                                                    <strong><?= gettext('Phone') ?>:</strong>
                                                    <div class="ms-4"><span id="displayFamilyPersonCardPhone<?= $x ?>"></span></div>
                                                </div>
                                                <div id="displayFamilyPersonCardBDayBlock<?= $x ?>" class="mb-0 d-none">
                                                    <i class="fa-solid fa-cake-candles text-body-secondary me-2"></i>
                                                    <strong><?= gettext('Birthday') ?>:</strong>
                                                    <div class="ms-4"><span id="displayFamilyPersonCardBDay<?= $x ?>"></span></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4" role="alert">
                            <div class="d-flex align-items-start">
                                <i class="fa-solid fa-circle-info me-3 mt-1 flex-shrink-0"></i>
                                <div>
                                    <strong><?= gettext("What happens next?") ?></strong>
                                    <p class="mb-1 mt-1"><?= gettext("Once you submit, our team will receive your registration and reach out to personally welcome your family. If anything looks wrong, use the Previous button to go back and make changes.") ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 mt-4 mb-0">
                            <button type="button" class="btn btn-secondary me-2" id="review-previous">
                                <i class="fa-solid fa-arrow-left me-2"></i><?= gettext('Previous') ?>
                            </button>
                            <button type="button" class="btn btn-success" id="submit-registration">
                                <i class="fa-solid fa-circle-check me-2"></i><?= gettext('Submit Registration') ?>
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
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
