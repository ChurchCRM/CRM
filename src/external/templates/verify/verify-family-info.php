<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext("Family Verification");

require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");

$doShowMap = !(empty($family->getLatitude()) && empty($family->getLongitude()));
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="avatar-placeholder avatar-lg" data-image-entity-type="family" data-image-entity-id="<?= $family->getId() ?>">
                        <?php if ($family->getPhoto()->hasUploadedPhoto()) { ?>
                            <img src="data:<?= $family->getPhoto()->getPhotoContentType() ?>;base64,<?= base64_encode($family->getPhoto()->getPhotoBytes()) ?>" alt="<?= InputUtils::escapeAttribute($family->getName()) ?>" class="avatar-img">
                            <span class="initials" style="display: none;"><?= substr($family->getName(), 0, 2) ?></span>
                        <?php } else { ?>
                            <span class="initials"><?= substr($family->getName(), 0, 2) ?></span>
                        <?php } ?>
                    </div>
                </div>
                <div class="col">
                    <h1 class="mb-2"><?= InputUtils::escapeHTML($family->getName()) ?></h1>
                    <div class="text-muted">
                        <div class="mb-2">
                            <i class="fa-solid fa-fw fa-map-marker text-primary"></i>
                            <?= InputUtils::escapeHTML($family->getAddress()) ?>
                        </div>
                        <?php if (!empty($family->getHomePhone())) { ?>
                        <div class="mb-2">
                            <i class="fa-solid fa-fw fa-phone text-primary"></i>
                            (H) <?= InputUtils::escapeHTML($family->getHomePhone()) ?>
                        </div>
                        <?php } ?>
                        <?php if (!empty($family->getEmail())) { ?>
                        <div class="mb-2">
                            <i class="fa-solid fa-fw fa-envelope text-primary"></i>
                            <?= InputUtils::escapeHTML($family->getEmail()) ?>
                        </div>
                        <?php } ?>
                        <?php if ($family->getWeddingDate() !== null) { ?>
                        <div class="mb-2">
                            <i class="fa-solid fa-fw fa-heart text-danger"></i>
                            <?= $family->getWeddingDate()->format(SystemConfig::getValue("sDateFormatLong")) ?>
                        </div>
                        <?php } ?>
                        <div class="mb-2">
                            <i class="fa-solid fa-fw fa-newspaper text-primary"></i>
                            <?= gettext("Newsletter") ?>: <strong><?= $family->getSendNewsletter() ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Section -->
    <?php if ($doShowMap) { ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <section id="map">
                <div id="map1" style="height: 300px;"></div>
            </section>
        </div>
    </div>
    <?php } ?>

    <!-- Family Members Section -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fa-solid fa-users text-primary me-2"></i><?= gettext("Family Members") ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <?php foreach ($family->getPeopleSorted() as $person) { ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <!-- Avatar with Initials -->
                            <div class="mb-3">
                                <div class="avatar-placeholder avatar-xlg mx-auto" data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>">
                                    <?php if ($person->getPhoto()->hasUploadedPhoto()) { ?>
                                        <img src="data:<?= $person->getPhoto()->getPhotoContentType() ?>;base64,<?= base64_encode($person->getPhoto()->getPhotoBytes()) ?>" alt="<?= InputUtils::escapeAttribute($person->getFullName()) ?>" class="avatar-img">
                                        <span class="initials" style="display: none;"><?= substr(trim($person->getFirstName() . ' ' . $person->getLastName()), 0, 2) ?></span>
                                    <?php } else { ?>
                                        <span class="initials"><?= substr(trim($person->getFirstName() . ' ' . $person->getLastName()), 0, 2) ?></span>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- Name and Role -->
                            <h5 class="card-title mb-1">
                                <?php if (!empty($person->getTitle())) { ?>
                                    <small class="text-muted"><?= InputUtils::escapeHTML($person->getTitle()) ?></small><br>
                                <?php } ?>
                                <?= InputUtils::escapeHTML($person->getFullName()) ?>
                            </h5>
                            <p class="text-muted mb-3">
                                <i class="fa-solid fa-<?= ($person->isMale() ? "mars" : "venus") ?> me-1"></i>
                                <?= InputUtils::escapeHTML($person->getFamilyRoleName()) ?>
                            </p>

                            <!-- Contact Information -->
                            <div class="contact-info w-100">
                                <?php if (!empty($person->getHomePhone())) { ?>
                                <div class="mb-2 small">
                                    <i class="fa-solid fa-fw fa-phone text-primary"></i>
                                    <span class="ms-2">(H) <?= InputUtils::escapeHTML($person->getHomePhone()) ?></span>
                                </div>
                                <?php } ?>
                                <?php if (!empty($person->getWorkPhone())) { ?>
                                <div class="mb-2 small">
                                    <i class="fa-solid fa-fw fa-briefcase text-primary"></i>
                                    <span class="ms-2">(W) <?= InputUtils::escapeHTML($person->getWorkPhone()) ?></span>
                                </div>
                                <?php } ?>
                                <?php if (!empty($person->getCellPhone())) { ?>
                                <div class="mb-2 small">
                                    <i class="fa-solid fa-fw fa-mobile text-primary"></i>
                                    <span class="ms-2">(M) <?= InputUtils::escapeHTML($person->getCellPhone()) ?></span>
                                </div>
                                <?php } ?>
                                <?php if (!empty($person->getEmail())) { ?>
                                <div class="mb-2 small">
                                    <i class="fa-solid fa-fw fa-envelope text-primary"></i>
                                    <span class="ms-2"><?= InputUtils::escapeHTML($person->getEmail()) ?></span>
                                </div>
                                <?php } ?>
                                <?php if (!empty($person->getWorkEmail())) { ?>
                                <div class="mb-2 small">
                                    <i class="fa-solid fa-fw fa-envelope text-success"></i>
                                    <span class="ms-2"><?= InputUtils::escapeHTML($person->getWorkEmail()) ?></span>
                                </div>
                                <?php } ?>
                                <?php if (!empty($person->getFormattedBirthDate())) { ?>
                                <div class="mb-2 small">
                                    <i class="fa-solid fa-fw fa-cake-candles text-warning"></i>
                                    <span class="ms-2"><?= InputUtils::escapeHTML($person->getFormattedBirthDate()) ?></span>
                                </div>
                                <?php } ?>
                            </div>

                            <!-- Classification -->
                            <div class="border-top mt-3 pt-3">
                                <p class="mb-0">
                                    <strong><?= gettext("Classification") ?>:</strong><br>
                                    <span class="badge bg-secondary"><?= InputUtils::escapeHTML(Classification::getName($person->getClsId())) ?></span>
                                </p>
                            </div>

                            <!-- Groups -->
                            <?php if (count($person->getPerson2group2roleP2g2rs()) > 0) { ?>
                            <div class="border-top mt-3 pt-3">
                                <p class="mb-2"><strong><?= gettext("Groups") ?></strong></p>
                                <div class="text-start">
                                    <?php foreach ($person->getPerson2group2roleP2g2rs() as $groupMembership) {
                                        if ($groupMembership->getGroup() != null) {
                                            $listOption = ListOptionQuery::create()
                                                ->filterById($groupMembership->getGroup()->getRoleListId())
                                                ->filterByOptionId($groupMembership->getRoleId())
                                                ->findOne();
                                            $roleName = $listOption ? $listOption->getOptionName() : '';
                                    ?>
                                    <div class="small mb-2">
                                        <strong><?= InputUtils::escapeHTML($groupMembership->getGroup()->getName()) ?>:</strong>
                                        <span class="badge bg-info"><?= InputUtils::escapeHTML($roleName) ?></span>
                                    </div>
                                    <?php
                                        }
                                    } ?>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Buttons -->
<div class="fab-container">
    <button type="button" class="fab fab-success" id="confirmVerifyBtn" data-toggle="modal" data-target="#confirm-Verify" title="<?= gettext('Confirm family information') ?>">
        <i class="fa-solid fa-check"></i>
        <span class="fab-label"><?= gettext('Confirm') ?></span>
    </button>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="confirm-Verify" tabindex="-1" role="dialog" aria-labelledby="verify-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="verify-label"><?= gettext("Confirm Family Information") ?></h5>
                <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" id="confirm-modal-collect">
                <form id="verifyForm">
                    <div class="form-group mb-3">
                        <label class="form-label"><?= gettext("Please confirm your family information:") ?></label>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="verifyType" id="NoChanges" value="no-change" checked>
                            <label class="form-check-label" for="NoChanges">
                                <i class="fa-solid fa-check text-success me-2"></i><?= gettext("All information is correct") ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="verifyType" id="UpdateNeeded" value="change-needed">
                            <label class="form-check-label" for="UpdateNeeded">
                                <i class="fa-solid fa-pencil text-warning me-2"></i><?= gettext("Please update our records with corrections") ?>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm-info-data" class="form-label"><?= gettext("Additional Information") ?></label>
                        <textarea id="confirm-info-data" class="form-control" rows="8" placeholder="<?= gettext('Provide any corrections or additional information here...') ?>"></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-body d-none" id="confirm-modal-done">
                <div class="alert alert-success" role="alert">
                    <i class="fa-solid fa-check-circle me-2"></i>
                    <strong><?= gettext("Thank You!") ?></strong>
                    <p class="mb-0 mt-2"><?= gettext("Your verification request has been received. Thank you for keeping your information up to date.") ?></p>
                </div>
            </div>

            <div class="modal-body d-none" id="confirm-modal-error">
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-exclamation-circle me-2"></i>
                    <strong><?= gettext("Error") ?></strong>
                    <p class="mb-0 mt-2"><?= gettext("We encountered an error processing your verification. Please try again or contact us directly.") ?></p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="onlineVerifyCancelBtn" data-dismiss="modal"><?= gettext("Cancel") ?></button>
                <button type="button" class="btn btn-success" id="onlineVerifyBtn">
                    <i class="fa-solid fa-paper-plane me-2"></i><?= gettext("Submit Verification") ?>
                </button>
                <a href="<?= ChurchMetaData::getChurchWebSite() ?>" id="onlineVerifySiteBtn" class="btn btn-primary" target="_blank">
                    <i class="fa-solid fa-globe me-2"></i><?= gettext("Visit Our Website") ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="//maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue("sGoogleMapsRenderKey") ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    <?php if ($doShowMap) { ?>
        var LatLng = new google.maps.LatLng(<?= $family->getLatitude() ?>, <?= $family->getLongitude() ?>)
    <?php } else { ?>
        var LatLng = null;
    <?php } ?>
    var token = '<?= $token->getToken()?>';
</script>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/family-verify.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/family-verify.min.js') ?>"></script>

<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
