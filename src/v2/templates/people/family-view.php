<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = $family->getName() . ' - ' . gettext('Family');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$curYear = DateTimeUtils::getCurrentYear();
$familyAddress = $family->getAddress();

$iFYID = FiscalYearUtils::getCurrentFiscalYearId();
if (array_key_exists('idefaultFY', $_SESSION)) {
    $iFYID = MakeFYString((int) $_SESSION['idefaultFY']);
}

$memberCount = count($family->getPeople());

// Get unique family emails for the verification modal
$familyEmails = $family->getEmails();

// Store family email for JavaScript (used by MailChimp plugin if active)
$familyEmailMD5 = $family->getEmail() ? md5(strtolower($family->getEmail())) : '';
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentFamily = <?= $family->getId() ?>;
    window.CRM.currentFamilyName ="<?= $family->getName() ?>";
    window.CRM.currentActive = <?= $family->isActive() ?"true" :"false" ?>;
    window.CRM.currentFamilyView = 2;
    window.CRM.familyEmail ="<?= InputUtils::escapeAttribute($family->getEmail() ?? '') ?>";
    window.CRM.familyEmailMD5 ="<?= $familyEmailMD5 ?>";
</script>

<div id="family-deactivated" class="alert alert-warning d-none">
    <strong><?= gettext("This Family is Inactive") ?> </strong>
</div>

<div class="row">
    <!-- LEFT COLUMN: Photo, Address, Metadata -->
    <div class="col-lg-4">
        <!-- Family Photo Card -->
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><?= $family->getName() ?></h3>
                <div class="card-tools ms-auto">
                    <span class="badge bg-light text-dark"><?= gettext('ID:') ?> <?= $family->getId() ?></span>
                </div>
            </div>
            <div class="card-body text-center">
                <div class="image-container d-inline-block">
                    <img data-image-entity-type="family" 
                         data-image-entity-id="<?= $family->getId() ?>" 
                         class="<?= $family->getPhoto()->hasUploadedPhoto() ? 'img-fluid rounded' : 'avatar avatar-lg photo-large' ?> mb-2"
                         style="<?= $family->getPhoto()->hasUploadedPhoto() ? 'max-width: 100%; max-height: 300px;' : '' ?>"/>
                </div>
                <div class="photo-actions">
                    <div class="btn-group" role="group">
                        <a id="view-larger-image-btn" href="#" class="btn btn-sm btn-primary hide-if-no-photo" title="<?= gettext("View Photo") ?>">
                            <i class="fa-solid fa-magnifying-glass-plus"></i>
                        </a>
                        <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) : ?>
                        <a id="uploadImageButton" href="#" class="btn btn-sm btn-info" title="<?= gettext("Upload Photo") ?>">
                            <i class="fa-solid fa-camera"></i>
                        </a>
                        <?php if ($family->getPhoto()->hasUploadedPhoto()) : ?>
                        <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirm-delete-image" title="<?= gettext("Delete Photo") ?>">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-<?= $family->isActive() ? 'success' : 'secondary' ?> me-1">
                        <i class="fa-solid fa-circle"></i> <?= $family->isActive() ? gettext('Active') : gettext('Inactive') ?>
                    </span>
                    <span class="badge bg-info me-1">
                        <i class="fa-solid fa-person-half-dress"></i> <?= $memberCount ?> <?= $memberCount == 1 ? gettext('Member') : gettext('Members') ?>
                    </span>
                    <?php if ($family->getEnvelope()) { ?>
                        <span class="badge bg-primary">
                            <i class="fa-solid fa-envelope"></i> #<?= $family->getEnvelope() ?>
                        </span>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Family Actions Toolbar -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a class="btn btn-ghost-warning" href="#" data-bs-toggle="modal" data-bs-target="#confirm-verify">
                        <i class="fa-solid fa-clipboard-check me-1"></i><?= gettext('Verify') ?>
                    </a>
                    <a class="btn btn-ghost-success AddToCart" id="AddFamilyToCart" data-cart-id="<?= $family->getId() ?>" data-cart-type="family">
                        <i class="fa-solid fa-cart-plus me-1"></i><?= gettext('Cart') ?>
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-ghost-secondary dropdown-toggle" id="family-actions-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-ellipsis-vertical me-1"></i><?= gettext("Actions") ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                                <a class="dropdown-item" id="activateDeactivate">
                                    <i class="fa-solid fa-power-off me-2"></i><?= ($family->isActive() ? gettext('Set Inactive') : gettext('Set Active')) ?>
                                </a>
                            <?php } ?>
                            <?php if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" id="deleteFamilyBtn" href="<?= SystemURLs::getRootPath() ?>/SelectDelete.php?FamilyID=<?= $family->getId() ?>">
                                    <i class="fa-solid fa-trash-can me-2"></i><?= gettext('Delete') ?>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Address Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-map"></i> <?= gettext("Address") ?>
                    <?php if ($family->hasLatitudeAndLongitude()): ?>
                    <span class="badge bg-green-lt text-green ms-2" title="<?= gettext('Address has been geocoded (coordinates stored)') ?>">
                        <i class="fa-solid fa-check"></i> <?= gettext('Geocoded') ?>
                    </span>
                    <?php elseif ($family->hasAddress()): ?>
                    <span class="badge bg-warning text-dark ms-2" title="<?= gettext('Address entered but coordinates not yet set') ?>">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= gettext('Unverified') ?>
                    </span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="card-body">
                <a href="https://maps.google.com/?q=<?= urlencode($familyAddress) ?>"
                   target="_blank" rel="noopener noreferrer"><?= $familyAddress ?></a>
                <?php $directionsUrl = $family->getDirectionsUrl(); ?>
                <div class="mt-2">
                    <?php if (!empty($directionsUrl)) : ?>
                    <a href="<?= $directionsUrl ?>" target="_blank" rel="noopener noreferrer"
                       class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-diamond-turn-right me-1"></i><?= gettext('Get Directions') ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!$family->hasLatitudeAndLongitude()) : ?>
                    <button type="button" class="btn btn-sm btn-outline-success" id="refresh-coordinates-btn"
                            data-family-id="<?= $family->getId() ?>"
                            title="<?= gettext('Automatically detect coordinates using address') ?>">
                        <i class="fa-solid fa-location-dot me-1"></i><?= gettext('Refresh Coordinates') ?>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Family location map (Leaflet + OpenStreetMap) -->
                <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.css') ?>">
                <?php if ($family->hasLatitudeAndLongitude()) : ?>
                    <div class="border-right border-left mt-2">
                        <div id="map1" style="height: 200px;"></div>
                    </div>
                    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                        window.CRM = window.CRM || {};
                        window.CRM.familyMapConfig = <?= json_encode(['lat' => (float) $family->getLatitude(), 'lng' => (float) $family->getLongitude()]) ?>;
                    </script>
                <?php endif; ?>
                <script src="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.js') ?>"></script>
                <script src="<?= SystemURLs::assetVersioned('/skin/v2/people-family-view.min.js') ?>"></script>
            </div>
        </div>

        <!-- Contact Info Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-address-book"></i> <?= gettext("Contact Info") ?></h3>
            </div>
            <div class="card-body">
                <ul class="fa-ul">
                    <?php
                    if (!SystemConfig::getBooleanValue("bHideFamilyNewsletter")) { /* Newsletter can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa-solid fa-newspaper"></i><?= gettext("Send Newsletter") ?>:
                            <span class="<?= ($family->isSendNewsletter() ?"text-success" :"text-danger") ?>"><i
                                    class="fa-solid fa-<?= ($family->isSendNewsletter() ?"check" :"times") ?>"></i></span>
                        </li>
                        <?php
                    }
                    if (!SystemConfig::getBooleanValue("bHideWeddingDate") && !empty($family->getWeddingdate())) { /* Wedding Date can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa-solid fa-magic"></i><?= gettext("Wedding Date") ?>:
                            <span><?= $family->getWeddingDate()->format(SystemConfig::getValue("sDateFormatLong")) ?></span></li>
                        <?php
                    }
                    if (SystemConfig::getValue("bUseDonationEnvelopes")) {
                        ?>
                        <li><i class="fa-li fa-solid fa-envelope"></i><?= gettext("Envelope Number") ?>
                            <span><?= $family->getEnvelope() ?></span>
                        </li>
                        <?php
                    }
                    if (!empty($family->getHomePhone())) {
                        ?>
                        <li><i class="fa-li fa-solid fa-phone"></i><?= gettext("Home Phone") ?>: <span><a
                                    href="tel:<?= $family->getHomePhone() ?>"><?= $family->getHomePhone() ?></a></span>
                        </li>
                        <?php
                    }
                    if ($family->getEmail() !=="") {
                        ?>
                        <li><i class="fa-li fa-solid fa-envelope"></i><?= gettext("Email") ?>:<a
                                href="mailto:<?= $family->getEmail() ?>">
                                <span><?= $family->getEmail() ?></span></a></li>
                        <!-- MailChimp status - populated by JavaScript if plugin is active -->
                        <li class="d-none" id="mailchimp-status-container">
                            <i class="fa-li fa-regular fa-paper-plane"></i><?= gettext("Mailchimp") ?>:
                            <span id="mailchimp-status">... <?= gettext("loading")?> ...</span>
                        </li>
                        <?php
                    }
                    foreach ($familyCustom as $customField) {
                        echo '<li><i class="fa-li ' . $customField->getIcon() . '"></i>' . $customField->getDisplayValue() . ': <span>';
                        if ($customField->getLink()) {
                            echo"<a href=\"" . $customField->getLink() ."\">" . $customField->getFormattedValue() ."</a>";
                        } else {
                            echo $customField->getFormattedValue();
                        }
                        echo '</span></li>';
                    }  ?>
                </ul>
            </div>
        </div>

        <!-- Properties Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-hashtag"></i> <?= gettext("Properties") ?></h3>
                <div class="card-tools ms-auto">
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                    <button id="add-family-property" type="button" class="btn btn-tool d-block">
                        <i class="fa-solid fa-circle-plus text-primary"></i>
                    </button>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <div id="family-property-loading" class="w-100 text-center">
                    <i class="btn btn-secondary btn-lg ajax">
                        <i class="fa-solid fa-spinner fa-spin"></i>&nbsp; <?= gettext("Loading") ?>
                    </i>
                </div>

                <div id="family-property-no-data" class="text-center text-muted py-3" style="display: block;">
                    <i class="fa-solid fa-tags fa-2x mb-2 d-block opacity-50"></i>
                    <p class="mb-0"><?= gettext("No properties assigned.") ?></p>
                </div>

                <div class="table-responsive">
                    <table id="family-property-table" class="table table-striped table-bordered data-table">
                        <thead>
                            <tr>
                                <th width="50"></th>
                                <th width="250" class="text-center"><?= gettext("Name") ?></h3></th>
                                <th class="text-center"><?= gettext("Value") ?></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Timeline, Family Members -->
    <div class="col-lg-8">
        <!-- Family Navigation -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= SystemURLs::getRootPath()?>/v2/family" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left"></i> <?= gettext('Back to Families'); ?>
                    </a>
                    <div class="btn-group" role="group" aria-label="<?= gettext('Family Navigation'); ?>">
                        <a id="lastFamily" class="btn btn-outline-primary">
                            <i class="fa-solid fa-chevron-left"></i> <?= gettext('Previous'); ?>
                        </a>
                        <a id="nextFamily" class="btn btn-outline-primary">
                            <?= gettext('Next'); ?> <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#family-timeline-body" aria-expanded="false">
                <h3 class="card-title m-0"><i class="fa-solid fa-clock-rotate-left me-1"></i> <?= gettext("Timeline") ?></h3>
                <div class="ms-auto"><i class="fa-solid fa-chevron-down"></i></div>
            </div>
            <div class="collapse" id="family-timeline-body">
                <div class="card-body">
                    <?php if (empty($familyTimeline)) { ?>
                        <div class="alert alert-info">
                            <i class="fa-solid fa-circle-info fa-fw fa-lg"></i>
                            <span><?= gettext('No timeline events yet.') ?></span>
                        </div>
                    <?php } else {
                        $currentYear = ''; ?>
                        <div class="timeline">
                            <?php foreach ($familyTimeline as $item) {
                                if ($currentYear !== $item['year']) {
                                    $currentYear = $item['year']; ?>
                                    <div class="timeline-event">
                                        <div class="timeline-event-icon bg-secondary-lt">
                                            <i class="fa-solid fa-calendar-days"></i>
                                        </div>
                                        <div class="timeline-event-card">
                                            <span class="fw-bold text-secondary"><?= $currentYear ?></span>
                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="timeline-event">
                                    <div class="timeline-event-icon bg-<?= $item['color'] ?>-lt text-<?= $item['color'] ?>">
                                        <i class="fa-solid <?= $item['style'] ?>"></i>
                                    </div>
                                    <div class="timeline-event-card card">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <div>
                                                    <?php if ($item['slim']) { ?>
                                                        <span class="text-secondary"><?= $item['text'] ?> <?= gettext($item['header']) ?></span>
                                                    <?php } else { ?>
                                                        <strong>
                                                            <?php if (in_array('headerlink', $item)) { ?>
                                                                <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                                                            <?php } else { ?>
                                                                <?= gettext($item['header']) ?>
                                                            <?php } ?>
                                                        </strong>
                                                    <?php } ?>
                                                </div>
                                                <div class="d-flex align-items-center gap-1 ms-2 flex-shrink-0">
                                                    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled() && (isset($item["editLink"]) || isset($item["deleteLink"]))) { ?>
                                                        <?php if (isset($item["editLink"])) { ?>
                                                            <a href="<?= $item["editLink"] ?>" class="btn btn-sm btn-ghost-primary" title="<?= gettext('Edit') ?>"><i class="fa-solid fa-pen"></i></a>
                                                        <?php }
                                                        if (isset($item["deleteLink"])) { ?>
                                                            <a href="<?= $item["deleteLink"] ?>" class="btn btn-sm btn-ghost-danger" title="<?= gettext('Delete') ?>"><i class="fa-solid fa-trash"></i></a>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <?php if (!$item['slim'] && !empty($item['text'])) { ?>
                                                <div class="text-secondary mt-1" style="white-space: pre-wrap; font-size: 0.875rem;"><?= $item['text'] ?></div>
                                            <?php } ?>
                                            <small class="text-muted"><i class="fa-solid fa-clock me-1"></i><?= $item['datetime'] ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) {
            $familyNotes = [];
            foreach ($familyTimeline as $item) {
                if ($item['type'] === 'note') {
                    $familyNotes[] = $item;
                }
            }
            $latestNote = !empty($familyNotes) ? $familyNotes[0] : null;
            ?>
            <!-- Notes Card -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#family-notes-body" aria-expanded="false">
                    <h3 class="card-title m-0"><i class="fa-solid fa-note-sticky me-1"></i> <?= gettext("Notes") ?></h3>
                    <div class="card-tools d-flex align-items-center ms-auto">
                        <a class="btn btn-outline-success btn-sm me-2" href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?FamilyID=<?= $family->getId() ?>" title="<?= gettext('Add New') . ' ' . gettext('Note') ?>">
                            <i class="fa-solid fa-plus"></i> <?= gettext('Add New') . ' ' . gettext('Note') ?>
                        </a>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
                <div class="collapse" id="family-notes-body">
                    <div class="card-body">
                        <?php if ($latestNote) { ?>
                            <div class="mb-3 border rounded p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong><?= gettext('Latest Note') ?></strong>
                                    <small class="text-muted"><?= date('Y-m-d H:i', strtotime($latestNote['datetime'])) ?></small>
                                </div>
                                <p class="mb-1"><?= InputUtils::escapeHTML($latestNote['text']) ?></p>
                                <small class="text-muted"><i class="fa-solid fa-user"></i> <?= InputUtils::escapeHTML($latestNote['header']) ?></small>
                            </div>
                        <?php } ?>
                        <?php if (empty($familyNotes)) { ?>
                            <div class="alert alert-info">
                                <i class="fa-solid fa-circle-info fa-fw fa-lg"></i>
                                <span><?= gettext('No notes have been added for this family.') ?></span>
                            </div>
                        <?php } else { ?>
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th class="td-shrink"><?= gettext('Date') ?></th>
                                        <th><?= gettext('Note') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($familyNotes as $note) { ?>
                                        <tr>
                                            <td class="td-shrink align-top">
                                                <div class="text-center">
                                                    <i class="fa-solid fa-calendar"></i><br>
                                                    <?= date('Y-m-d', strtotime($note['datetime'])) ?><br>
                                                    <small class="text-muted"><?= date('h:i A', strtotime($note['datetime'])) ?></small>
                                                    <div class="mt-2">
                                                        <?php if (isset($note['editLink']) && $note['editLink']) { ?>
                                                            <a href="<?= $note['editLink'] ?>" class="btn btn-sm btn-primary" title="<?= gettext('Edit') ?>">
                                                                <i class="fa-solid fa-pen"></i>
                                                            </a>
                                                        <?php }
                                                        if (isset($note['deleteLink'])) { ?>
                                                            <a href="<?= $note['deleteLink'] ?>" class="btn btn-sm btn-danger" title="<?= gettext('Delete') ?>">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </a>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-top">
                                                <div class="mb-2">
                                                    <?= InputUtils::escapeHTML($note['text']) ?>
                                                </div>
                                                <small class="text-muted"><i class="fa-solid fa-user"></i> <?= InputUtils::escapeHTML($note['header']) ?></small>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <!-- Family Members Card -->
        <div class="card border border-success mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-people-roof"></i> <?= gettext("Family Members") ?></h3>
                <div class="card-tools d-flex align-items-center">
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                        <a class="btn btn-outline-success btn-sm me-2" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?FamilyID=<?= $family->getId() ?>" title="<?= gettext('Add New') . ' ' . gettext('Member') ?>">
                            <i class="fa-solid fa-user-plus"></i> <?= gettext('Add New') . ' ' . gettext('Member') ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                <?php foreach ($family->getPeople() as $person) { ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center position-relative">
                                <!-- Action dropdown -->
                                <div class="position-absolute top-0 end-0 m-2">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $person->getID() ?>"><i class="fa-solid fa-eye me-2"></i><?= gettext('View') ?></a>
                                            <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $person->getID() ?>"><i class="fa-solid fa-pen me-2"></i><?= gettext('Edit') ?></a>
                                            <button class="dropdown-item AddToCart" data-cart-id="<?= $person->getId() ?>" data-cart-type="person"><i class="fa-solid fa-cart-plus me-2"></i><?= gettext('Add to Cart') ?></button>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item text-danger delete-person" data-person_name="<?= $person->getFullName() ?>" data-person_id="<?= $person->getId() ?>" data-view="family"><i class="fa-solid fa-trash-can me-2"></i><?= gettext('Delete') ?></button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Person info -->
                                <a href="<?= $person->getViewURI() ?>">
                                    <img class="avatar avatar-md mb-2"
                                         data-image-entity-type="person"
                                         data-image-entity-id="<?= $person->getId() ?>">
                                    <h3 class="mb-0"><?= $person->getTitle() ?> <?= $person->getFullName() ?></h3>
                                </a>
                                <p class="text-muted mb-2">
                                    <i class="fa-solid fa-<?= ($person->isMale() ? "person" : "person-dress") ?> me-1"></i><?= $person->getFamilyRoleName() ?>
                                    <?php if ($person->getClsId()) { ?>
                                        <span class="badge bg-secondary-lt text-secondary ms-1"><?= Classification::getName($person->getClsId()) ?></span>
                                    <?php } ?>
                                </p>
                                <!-- Contact details -->
                                <ul class="list-unstyled text-start mb-0">
                                    <?php if (!empty($person->getCellPhone())) { ?>
                                        <li class="mb-1"><i class="fa-solid fa-mobile me-2 text-muted"></i><a href="tel:<?= $person->getCellPhone() ?>"><?= $person->getCellPhone() ?></a></li>
                                    <?php }
                                    if (!empty($person->getHomePhone())) { ?>
                                        <li class="mb-1"><i class="fa-solid fa-phone me-2 text-muted"></i><a href="tel:<?= $person->getHomePhone() ?>"><?= $person->getHomePhone() ?></a></li>
                                    <?php }
                                    if (!empty($person->getEmail())) { ?>
                                        <li class="mb-1"><i class="fa-solid fa-envelope me-2 text-muted"></i><a href="mailto:<?= $person->getEmail() ?>"><?= $person->getEmail() ?></a></li>
                                    <?php }
                                    $formattedBirthday = $person->getFormattedBirthDate();
                                    if ($formattedBirthday) { ?>
                                        <li class="mb-1"><i class="fa-solid fa-cake-candles me-2 text-muted"></i><?= $formattedBirthday ?> <?= $person->getAge() ?? '' ?></li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    ?>
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-circle-dollar-to-slot"></i> <?= gettext("Pledges and Payments") ?></h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <a class="btn btn-success w-100" href="<?= SystemURLs::getRootPath()?>/PledgeEditor.php?FamilyID=<?= $family->getId() ?>&amp;linkBack=v2/family/<?= $family->getId() ?>&PledgeOrPayment=Pledge">
                            <i class="fa-solid fa-hand-holding-dollar"></i> <?= gettext('Add Pledge') ?>
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <a class="btn btn-success w-100" href="<?= SystemURLs::getRootPath()?>/PledgeEditor.php?FamilyID=<?= $family->getId() ?>&amp;linkBack=v2/family/<?= $family->getId() ?>&PledgeOrPayment=Payment">
                            <i class="fa-solid fa-money-bill-wave"></i> <?= gettext('Add Payment') ?>
                        </a>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-center">
                            <label class="mb-0 me-3">
                                <input type="checkbox" id="ShowPledges" <?= AuthenticationManager::getCurrentUser()->isShowPledges() ?"checked" :"" ?>> 
                                <?= gettext("Show Pledges") ?>
                            </label>
                            <label class="mb-0 me-3">
                                <input type="checkbox" id="ShowPayments" <?= AuthenticationManager::getCurrentUser()->isShowPayments() ?"checked" :"" ?>> 
                                <?= gettext("Show Payments") ?>
                            </label>
                            <label class="mb-0 me-2"><?= gettext("Since") ?>:</label>
                            <input type="text" class="date-picker form-control form-control-sm" id="ShowSinceDate" style="width: 150px;"
                                   value="<?= AuthenticationManager::getCurrentUser()->getShowSince() ?>" maxlength="10">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="pledge-payment-v2-table" class="table table-striped table-bordered data-table" style="width: 100%;">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- Photo uploader bundle - loaded only on this page -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/js/MemberView.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/FamilyView.js') ?>"></script>

<!-- Photos start -->
<div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog"
     aria-labelledby="delete-Image-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm Delete") ?></h4>
            </div>

            <div class="modal-body">
                <p><?= gettext("You are about to delete the profile photo, this procedure is irreversible.") ?></p>

                <p><?= gettext("Do you want to proceed?") ?></p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>

            </div>
        </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    // Copy photo uploader function from temporary storage to window.CRM
    // This must happen after Header-function.php initializes window.CRM
    if (window._CRM_createPhotoUploader) {
        window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
    } else {
        console.error('Photo uploader function not found in window._CRM_createPhotoUploader');
    }

    // Initialize photo uploader when window loads
    window.addEventListener('load', function() {
        if (typeof window.CRM.createPhotoUploader !== 'function') {
            console.error('window.CRM.createPhotoUploader is not a function');
            return;
        }
        
        window.CRM.photoUploader = window.CRM.createPhotoUploader({
            uploadUrl: window.CRM.root +"/api/family/" + window.CRM.currentFamily +"/photo",
            maxFileSize: window.CRM.maxUploadSizeBytes,
            photoHeight: <?= Photo::PHOTO_HEIGHT ?>,
            photoWidth: <?= Photo::PHOTO_WIDTH ?>,
            onComplete: function() {
                location.reload();
            }
        });
    });

    // Set up click handlers (use event delegation)
    $(document).on('click', '#uploadImageButton', function(e) {
        e.preventDefault();
        if (window.CRM && window.CRM.photoUploader) {
            window.CRM.photoUploader.show();
        } else {
            console.error('Photo uploader not initialized!');
        }
    });
</script>
<!-- Photos end -->

<!-- FAB Container for Family View -->
<div id="fab-family-view" class="fab-container fab-family-view">
    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
    <a href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?FamilyID=<?= $family->getId() ?>" class="fab-button fab-note" aria-label="<?= gettext('Add New') . ' ' . gettext('Note') ?>">
        <span class="fab-label"><?= gettext('Add New') . ' ' . gettext('Note') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-note-sticky"></i>
        </div>
    </a>
    <?php } ?>
    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
    <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>" class="fab-button fab-edit" aria-label="<?= gettext('Edit Family') ?>">
        <span class="fab-label"><?= gettext('Edit Family') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-pen"></i>
        </div>
    </a>
    <?php } ?>
</div>

<div class="modal fade" id="confirm-verify" tabindex="-1" role="dialog" aria-labelledby="confirm-verify-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h4 class="modal-title"
                    id="confirm-verify-label"><?= gettext("Request Family Info Verification") ?></h4>
            </div>
            <div class="modal-body">
                <b><?= gettext("Select how do you want to request the family information to be verified") ?></b>
                <p>
                    <?php if (count($familyEmails) > 0) {
                        ?>
                <p><?= gettext("You are about to email copy of the family information to the following emails") ?>
                <ul>
                        <?php foreach ($familyEmails as $tmpEmail) { ?>
                        <li><?= $tmpEmail ?></li>
                        <?php } ?>
                </ul>
                </p>
            </div>
                        <?php
                    } ?>
            <div class="modal-footer text-center">
                <?php if (count($familyEmails) > 0 && !empty(SystemConfig::getValue('sSMTPHost'))) {
                    ?>
                    <button type="button" id="onlineVerify"
                            class="btn btn-warning warning"><i
                            class="fa-solid fa-envelope"></i> <?= gettext("Online Verification") ?>
                    </button>
                    <button type="button" id="verifyEmailPDF"
                            class="btn btn-warning"><i
                            class="fa-solid fa-file-pdf"></i> <?= gettext("Email PDF") ?>
                    </button>
                    <?php
                } ?>
                <button type="button" id="verifyURL"
                        class="btn btn-secondary"><i class="fa-solid fa-link"></i> <?= gettext("URL") ?></button>
                <button type="button" id="verifyDownloadPDF"
                        class="btn btn-info"><i class="fa-solid fa-download"></i> <?= gettext("PDF") ?></button>
                <button type="button" id="verifyNow"
                        class="btn btn-success"><i class="fa-solid fa-check"></i> <?= gettext("Verified In Person") ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
