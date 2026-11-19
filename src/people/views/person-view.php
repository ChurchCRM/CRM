<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PersonDeleteGuard;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Load Uppy Photo Uploader CSS & JS -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.js') ?>"></script>

<?php
// Unpack commonly used personData fields
$sClassName    = $personData['sClassName'] ?? '';
$sFamRole      = $personData['sFamRole'] ?? '';
$per_MembershipDate = $personData['per_MembershipDate'] ?? null;
$per_FriendDate     = $personData['per_FriendDate'] ?? '';
$per_Facebook       = $personData['per_Facebook'] ?? '';
$per_Twitter        = $personData['per_Twitter'] ?? '';
$per_LinkedIn       = $personData['per_LinkedIn'] ?? '';
$per_WorkEmail      = $personData['per_WorkEmail'] ?? '';
$fam_Latitude       = (float) ($personData['fam_Latitude'] ?? 0);
$fam_Longitude      = (float) ($personData['fam_Longitude'] ?? 0);
?>

<?php
$currentUserId = AuthenticationManager::getCurrentUser()->getId();
// Server-side "Send email" needs the Email permission and a working, enabled SMTP setup.
$canSendEmail = AuthenticationManager::getCurrentUser()->isEmailEnabled() && SystemConfig::isEmailEnabled();
?>

<div id="person-deactivated" class="alert alert-warning d-none">
    <strong><?= gettext("This Person is Inactive") ?> </strong>
</div>

<?php
$genderClass = "fa-question";
$genderText = gettext('Unknown');
if ($person->isMale()) {
    $genderClass = "fa-person";
    $genderText = gettext('Male');
} elseif ($person->isFemale()) {
    $genderClass = "fa-person-dress";
    $genderText = gettext('Female');
}
?>
<div class="card mb-3" id="person-profile-header">
    <div class="card-body">
        <div class="d-flex flex-column flex-sm-row gap-3">
            <div class="position-relative profile-photo-thumb flex-shrink-0">
                <a href="#" id="uploadImageButton" class="d-block h-100" title="<?= $bOkToEdit ? gettext("Click to upload photo") : gettext("View Photo") ?>">
                    <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" alt="" class="photo-profile rounded">
                </a>
                <button type="button"
                        class="photo-view-overlay btn btn-sm position-absolute bottom-0 end-0 m-1 d-none"
                        data-entity-type="person"
                        data-entity-id="<?= $person->getId() ?>"
                        title="<?= gettext('View full photo') ?>"
                        aria-label="<?= gettext('View full photo') ?>">
                    <i class="fa-solid fa-magnifying-glass photo-zoom-icon" aria-hidden="true"></i>
                </button>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge bg-primary-lt text-primary"><i class="fa-solid fa-id-card me-1"></i><?= InputUtils::escapeHTML(gettext($sClassName)) ?></span>
                    <?php if (!empty($sFamRole)) : ?>
                    <span class="badge bg-secondary-lt text-secondary"><i class="fa-solid fa-users me-1"></i><?= InputUtils::escapeHTML(gettext($sFamRole)) ?></span>
                    <?php endif; ?>
                    <?php if ($person->getNeedsReview()) : ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/people/self-register" class="badge bg-warning-lt text-warning text-decoration-none">
                        <i class="fa-solid fa-user-clock me-1"></i><?= gettext('Pending review') ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!$person->isDeceased() && $person->isBirthdayToday()) : ?>
                    <span id="person-birthday-today" class="badge bg-success-lt text-success">
                        <i class="fa-solid fa-cake-candles me-1"></i><?= gettext('Birthday today') ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($person->isDeceased()) : ?>
                    <span class="badge bg-secondary text-white">
                        <i class="fa-solid fa-cross me-1"></i><?= gettext('Deceased') ?>
                        <?php if ($person->getDateDeceased()) : ?>
                            &middot; <?= InputUtils::escapeHTML($person->getDateDeceased()->format(SystemConfig::getValue('sDateFormatLong') ?: 'Y-m-d')) ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap column-gap-3 row-gap-1 text-body-secondary mb-3">
                    <span><i class="fa <?= $genderClass ?> me-1"></i><?= $genderText ?></span>
                    <?php if ($dBirthDate) : ?>
                    <span><i class="fa-solid fa-cake-candles me-1"></i><?= $dBirthDate ?><?php if (!$person->hideAge()) : ?> (<?= $person->getAge() ?>)<?php endif; ?></span>
                    <?php endif; ?>
                    <?php if ($person->getGradeName() !== null) : ?>
                    <span id="personGrade" title="<?= InputUtils::escapeAttribute(gettext('Grade')) ?>"><i class="fa-solid fa-graduation-cap me-1"></i><?= gettext('Grade') ?>: <?= InputUtils::escapeHTML($person->getGradeName()) ?></span>
                    <?php endif; ?>
                    <?php if ($per_MembershipDate) : ?>
                    <span><i class="fa-solid fa-calendar-check me-1"></i><?= gettext('Since') ?> <?= DateTimeUtils::formatDate($per_MembershipDate, false) ?></span>
                    <?php endif; ?>
                    <?php if (!SystemConfig::getBooleanValue('bHideFriendDate') && $per_FriendDate !== '') : ?>
                    <span><i class="fa-solid fa-handshake me-1"></i><?= gettext('Friend Date') ?>: <?= DateTimeUtils::formatDate($per_FriendDate, false) ?></span>
                    <?php endif; ?>
                    <?php if ($sEnvelope !== gettext('Not assigned')) : ?>
                    <span><i class="fa-solid fa-envelope me-1"></i><?= gettext('Envelope') ?> #<?= $sEnvelope ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap column-gap-4 row-gap-2" id="person-contact">
                    <?php foreach ([[$sCellPhone, $sCellPhoneUnformatted, 'fa-mobile-screen', gettext('Mobile'), true], [$sHomePhone, $sHomePhoneUnformatted, 'fa-house', gettext('Home'), false], [$sWorkPhone, $sWorkPhoneUnformatted, 'fa-briefcase', gettext('Work'), false]] as [$phone, $phoneRaw, $phoneIcon, $phoneLabel, $canText]) :
                        if (!$phone) { continue; } ?>
                    <span class="text-nowrap" title="<?= InputUtils::escapeAttribute($phoneLabel) ?>">
                        <i class="fa-solid <?= $phoneIcon ?> me-1 text-body-secondary"></i><a href="tel:<?= InputUtils::escapeAttribute($phoneRaw) ?>"><?= InputUtils::escapeHTML($phone) ?></a>
                        <?php if ($canText) : ?>
                        <a href="sms:<?= InputUtils::escapeAttribute(preg_replace('/[^\d+]/', '', $phoneRaw)) ?>" class="ms-1 text-body-secondary" title="<?= gettext('Send text message') ?>"><i class="fa-solid fa-comment-sms"></i></a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-ghost-secondary py-0 px-1 copy-phone-btn" type="button" data-phone="<?= InputUtils::escapeAttribute($phone) ?>" title="<?= gettext('Copy to clipboard') ?>"><i class="fa-solid fa-copy"></i></button>
                    </span>
                    <?php endforeach; ?>
                    <?php if (!empty($sEmail)) : ?>
                    <span class="text-nowrap">
                        <i class="fa-solid fa-at me-1 text-body-secondary"></i><a href="mailto:<?= InputUtils::escapeAttribute($sUnformattedEmail) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($sEmail) ?></a>
                        <button class="btn btn-sm btn-ghost-secondary py-0 px-1 copy-email-btn" type="button" data-email="<?= InputUtils::escapeAttribute($sUnformattedEmail) ?>" title="<?= gettext('Copy to clipboard') ?>"><i class="fa-solid fa-copy"></i></button>
                        <?php if ($canSendEmail) : ?>
                        <button class="btn btn-sm btn-ghost-primary py-0 px-1" type="button"
                                data-email-composer
                                data-email-person-id="<?= (int) $iPersonID ?>"
                                data-email-address="<?= InputUtils::escapeAttribute($sUnformattedEmail) ?>"
                                data-email-name="<?= InputUtils::escapeAttribute($person->getFullName()) ?>"
                                data-email-title="<?= InputUtils::escapeAttribute(sprintf(gettext('Email %s'), $person->getFullName())) ?>"
                                title="<?= gettext('Send email from ChurchCRM') ?>"><i class="fa-solid fa-paper-plane"></i></button>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($per_WorkEmail)) : ?>
                    <span class="text-nowrap" title="<?= gettext('Work') ?>">
                        <i class="fa-solid fa-briefcase me-1 text-body-secondary"></i><a href="mailto:<?= InputUtils::escapeAttribute($per_WorkEmail) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($per_WorkEmail) ?></a>
                        <button class="btn btn-sm btn-ghost-secondary py-0 px-1 copy-email-btn" type="button" data-email="<?= InputUtils::escapeAttribute($per_WorkEmail) ?>" title="<?= gettext('Copy to clipboard') ?>"><i class="fa-solid fa-copy"></i></button>
                    </span>
                    <?php endif; ?>
                    <?php if (strlen($per_Facebook) > 0) : ?>
                    <a href="https://www.facebook.com/<?= InputUtils::escapeAttribute($per_Facebook) ?>" target="_blank" rel="noopener noreferrer" class="text-nowrap"><i class="fa-brands fa-facebook me-1"></i><?= InputUtils::escapeHTML($per_Facebook) ?></a>
                    <?php endif; ?>
                    <?php if (strlen($per_Twitter) > 0) : ?>
                    <a href="https://www.twitter.com/<?= InputUtils::escapeAttribute($per_Twitter) ?>" target="_blank" rel="noopener noreferrer" class="text-nowrap"><i class="fa-brands fa-x-twitter me-1"></i>@<?= InputUtils::escapeHTML($per_Twitter) ?></a>
                    <?php endif; ?>
                    <?php if (strlen($per_LinkedIn) > 0) : ?>
                    <a href="https://www.linkedin.com/in/<?= InputUtils::escapeAttribute($per_LinkedIn) ?>" target="_blank" rel="noopener noreferrer" class="text-nowrap"><i class="fa-brands fa-linkedin me-1"></i><?= InputUtils::escapeHTML($per_LinkedIn) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer py-2 d-print-none">
        <div class="d-flex flex-wrap align-items-center gap-1">
            <?php if ($bOkToEdit) { ?>
            <a class="btn btn-ghost-primary" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $iPersonID ?>" title="<?= gettext("Edit Person") ?>"><i class="fa-solid fa-pen me-1"></i><?= gettext("Edit") ?></a>
            <?php } ?>
            <button class="btn btn-ghost-secondary" id="printPerson" title="<?= gettext("Print") ?>"><i class="fa-solid fa-print me-1"></i><?= gettext("Print") ?></button>
            <button class="btn btn-ghost-success AddToCart" id="AddPersonToCart" data-cart-id="<?= $iPersonID ?>" data-cart-type="person" title="<?= gettext("Add to Cart") ?>"><i class="fa-solid fa-cart-plus me-1"></i><span class="cartActionDescription"><?= gettext("Cart") ?></span></button>
            <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
            <button type="button" class="btn btn-ghost-info" data-open-note-person="<?= (int) $iPersonID ?>">
                <i class="fa-solid fa-note-sticky me-1"></i><?= gettext("Add Note") ?>
            </button>
            <?php } ?>
            <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
            <button type="button" class="btn btn-ghost-info" id="editWhyCame" data-open-why-came="<?= (int) $iPersonID ?>">
                <i class="fa-solid fa-circle-question me-1"></i><?= gettext("Why Came") ?>
            </button>
            <?php } ?>
            <div class="dropdown">
                <button class="btn btn-ghost-secondary dropdown-toggle" id="person-actions-dropdown" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="fa-solid fa-ellipsis-vertical me-1"></i><?= gettext("Actions") ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/people/view/<?= (int) $iPersonID ?>/emails"><i class="fa-solid fa-envelope-open-text me-2"></i><?= gettext('Email History') ?></a>
                    <?php if ($bOkToEdit && $fam_ID !== '') { ?>
                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $fam_ID ?>"><i class="fa-solid fa-people-roof me-2"></i><?= gettext("Edit Family") ?></a>
                        <a class="dropdown-item" id="edit-role-btn" data-person_id="<?= $person->getId() ?>" data-family_role="<?= InputUtils::escapeAttribute($person->getFamilyRoleName()) ?>" data-family_role_id="<?= $person->getFmrId() ?>"><i class="fa-solid fa-user-tag me-2"></i><?= gettext("Change Family Role") ?></a>
                    <?php } ?>
                    <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) { ?>
                        <a class="dropdown-item" id="addGroup"><i class="fa-solid fa-users me-2"></i><?= gettext("Assign New Group") ?></a>
                    <?php } ?>
                    <?php if ($fam_ID !== '' && $familyHasCoords) { ?>
                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/people/map/neighbors?familyId=<?= $fam_ID ?>"><i class="fa-solid fa-people-roof me-2"></i><?= gettext("Find Neighbors") ?></a>
                    <?php } ?>
                    <?php if ($bOkToEdit) { ?>
                        <div class="dropdown-divider"></div>
                        <h6 class="dropdown-header"><?= gettext("Photo") ?></h6>
                        <a class="dropdown-item" id="uploadPhotoAction" href="#"><i class="fa-solid fa-camera me-2"></i><?= gettext("Upload Photo") ?></a>
                        <?php if ($person->getPhoto()->hasUploadedPhoto()) { ?>
                            <a class="dropdown-item" id="view-larger-image-btn" href="#"><i class="fa-solid fa-magnifying-glass-plus me-2"></i><?= gettext("View Photo") ?></a>
                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#confirm-delete-image"><i class="fa-solid fa-trash-can me-2"></i><?= gettext("Delete Photo") ?></a>
                        <?php } ?>
                    <?php } ?>
                    <?php if ($bOkToEdit && $currentUserId !== (int)$iPersonID) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" id="activateDeactivatePerson">
                            <i class="fa-solid fa-power-off me-2"></i><?= ($person->isActive() ? gettext('Set Inactive') : gettext('Set Active')) ?>
                        </a>
                    <?php } ?>
                    <?php if ($loginAsPerson !== null) { ?>
                        <div class="dropdown-divider"></div>
                        <?php if ($loginAsPerson['refusal'] === null) { ?>
                            <a class="dropdown-item" href="#" id="loginAsPerson" data-person-id="<?= (int) $iPersonID ?>" data-label="<?= InputUtils::escapeAttribute($loginAsPerson['label']) ?>" data-csrf-token="<?= InputUtils::escapeAttribute($loginAsPerson['csrfToken']) ?>"><i class="fa-solid fa-user-secret me-2"></i><?= InputUtils::escapeHTML($loginAsPerson['label']) ?></a>
                        <?php } else { ?>
                            <span class="d-block" id="loginAsPersonRefusal" tabindex="0" title="<?= InputUtils::escapeAttribute($loginAsPerson['refusal']) ?>">
                                <span class="dropdown-item disabled" id="loginAsPerson" aria-disabled="true"><i class="fa-solid fa-user-secret me-2"></i><?= InputUtils::escapeHTML($loginAsPerson['label']) ?><span class="visually-hidden">: <?= InputUtils::escapeHTML($loginAsPerson['refusal']) ?></span></span>
                            </span>
                        <?php } ?>
                    <?php } ?>
                    <?php if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger delete-person" id="deletePersonBtn" data-person_name="<?= InputUtils::escapeAttribute($person->getFullName()) ?>" data-person_id="<?= $iPersonID ?>"<?= PersonDeleteGuard::attributes((int) $iPersonID) ?>><i class="fa-solid fa-trash-can me-2"></i><?= gettext("Delete Person") ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>


        <?php
                $hasCustomFields = false;
                $customFieldsHtml = '';
                foreach ($customFieldsMaster as $Row) {
                    $custom_Field   = $Row->getId();
                    $custom_Name    = $Row->getName();
                    $type_ID        = $Row->getTypeId();
                    $custom_Special = $Row->getSpecial();
                    $currentData    = isset($aCustomData[$custom_Field]) ? trim($aCustomData[$custom_Field]) : '';
                    if ($currentData !== '') {
                        $hasCustomFields = true;
                        $displayIcon = "fa-solid fa-tag";
                        $displayLink = "";
                        if ((int)$type_ID === 9) {
                            $displayIcon = "fa-solid fa-person-half-dress";
                            $linkedPersonId = (int) $currentData;
                            if ($linkedPersonId > 0) {
                                $displayLink = SystemURLs::getRootPath() . '/people/view/' . $linkedPersonId;
                            }
                        } elseif ((int)$type_ID === 11) {
                            $custom_Special = null;
                            $displayIcon = "fa-solid fa-phone";
                            // Sanitize phone number for tel: URI (aligned with GHSA-frj8-mpcx-44g9 allowlist)
                            $sanitizedPhone = preg_replace('/[^0-9+\-().\sxX#*]/', '', $currentData);
                            $displayLink = "tel:" . $sanitizedPhone;
                        }
                        $temp_string = nl2br(InputUtils::escapeHTML(CustomFieldUtils::display($type_ID, $currentData, $custom_Special)));
                        $customFieldsHtml .= '<div class="col-12 col-sm-6 col-xl-4 text-break">'
                            . '<div class="small text-body-secondary"><i class="' . $displayIcon . ' me-1"></i>' . InputUtils::escapeHTML($custom_Name) . '</div>'
                            . '<div>' . ($displayLink ? '<a href="' . InputUtils::escapeAttribute($displayLink) . '">' . $temp_string . '</a>' : $temp_string) . '</div>'
                            . '</div>';
                    }
                }
        ?>

<div class="row">
    <div class="col-12">
        <!-- Family Members Card -->
        <?php if ($fam_ID !== '' && $person->getFamily() !== null) { ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-people-roof me-1"></i><?= InputUtils::escapeHTML($fam_Name) ?> <?= gettext('Family') ?></h3>
                <div class="ms-auto d-flex gap-1">
                    <?php if ($bOkToEdit) { ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $fam_ID ?>" class="btn btn-sm btn-ghost-secondary" title="<?= gettext('Edit Family') ?>"><i class="fa-solid fa-pen"></i></a>
                    <?php } ?>
                    <a href="<?= $person->getFamily()->getViewURI() ?>" class="btn btn-sm btn-ghost-primary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= gettext('View') ?></a>
                </div>
            </div>
            <div class="table-clip-x">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th><?= gettext('Name') ?></th>
                            <th class="text-center"><?= gettext('Role') ?></th>
                            <th class="d-none d-md-table-cell"><?= gettext('Birthday') ?></th>
                            <th class="d-none d-md-table-cell"><?= gettext('Email') ?></th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($person->getFamily()->getPeopleSorted() as $familyMember) {
                            $tmpPersonId = $familyMember->getId();
                            $isSelf = ($tmpPersonId === $iPersonID);
                        ?>
                            <tr<?= $isSelf ? ' class="bg-surface-secondary"' : '' ?>>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img data-image-entity-type="person" data-image-entity-id="<?= $familyMember->getId() ?>" class="avatar avatar-sm me-2">
                                        <?php if ($isSelf) { ?>
                                            <span class="fw-bold"><?= InputUtils::escapeHTML($familyMember->getFullName()) ?></span>
                                            <i class="fa-solid fa-circle-user text-primary ms-2" title="<?= gettext('Current person') ?>"></i>
                                        <?php } else { ?>
                                            <a href="<?= $familyMember->getViewURI() ?>"><?= InputUtils::escapeHTML($familyMember->getFullName()) ?></a>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-lt text-secondary"><?= InputUtils::escapeHTML($familyMember->getFamilyRoleName()) ?></span>
                                </td>
                                <td class="d-none d-md-table-cell text-nowrap"><?= $familyMember->getFormattedBirthDate(); ?></td>
                                <td class="d-none d-md-table-cell text-nowrap">
                                    <?php $tmpEmail = $familyMember->getEmail();
                                    if ($tmpEmail !== '') { ?>
                                        <a href="mailto:<?= InputUtils::escapeAttribute($tmpEmail) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($tmpEmail) ?></a>
                                        <?php if ($canSendEmail && !$isSelf) { // the person's own line above already has the button ?>
                                        <button class="btn btn-sm btn-ghost-primary ms-1 py-0 px-1" type="button"
                                                data-email-composer
                                                data-email-person-id="<?= (int) $familyMember->getId() ?>"
                                                data-email-address="<?= InputUtils::escapeAttribute($tmpEmail) ?>"
                                                data-email-name="<?= InputUtils::escapeAttribute($familyMember->getFullName()) ?>"
                                                data-email-title="<?= InputUtils::escapeAttribute(sprintf(gettext('Email %s'), $familyMember->getFullName())) ?>"
                                                title="<?= gettext('Send email from ChurchCRM') ?>">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                        <?php } ?>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!$isSelf) { ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <?php if ($bOkToEdit) { ?>
                                            <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $tmpPersonId ?>"><i class="fa-solid fa-pen me-2"></i><?= gettext('Edit') ?></a>
                                            <?php } ?>
                                            <button class="dropdown-item AddToCart" data-cart-id="<?= $tmpPersonId ?>" data-cart-type="person"><i class="fa-solid fa-cart-plus me-2"></i><?= gettext('Add to Cart') ?></button>
                                            <?php if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) { ?>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item text-danger delete-person" data-person_name="<?= InputUtils::escapeAttribute($familyMember->getFullName()) ?>" data-person_id="<?= $familyMember->getId() ?>" data-view="family"<?= PersonDeleteGuard::attributes((int) $familyMember->getId()) ?>><i class="fa-solid fa-trash-can me-2"></i><?= gettext('Delete') ?></button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>


        <!--
            person-view.js bundle (map, refresh-coordinates, group manager, timeline
            filter, attendance history) — loaded unconditionally. It must NOT be
            nested inside the "has family" / "has mailing address" blocks above:
            group management, the timeline filter, and the attendance history tab
            are all needed regardless of whether the person has a family or address
            (e.g. the admin's own "Church Admin" placeholder person has neither).
        -->

        <script src="<?= SystemURLs::assetVersioned('/skin/v2/people-person-view.min.js') ?>"></script>
        <?php if ($canSendEmail) : ?>
        <script src="<?= SystemURLs::assetVersioned('/skin/v2/email-composer.min.js') ?>" defer nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
        <?php endif; ?>

        <!-- Tabbed Content -->
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <ul class="nav nav-pills card-header-pills flex-nowrap overflow-x-auto text-nowrap">
                    <li class="nav-item">
                        <a class="nav-link active" id="nav-item-overview" href="#overview" data-bs-toggle="tab">
                            <i class="fa-solid fa-address-card me-1"></i><?= gettext('Overview') ?>
                        </a>
                    </li>
                    <?php $noteCount = count(array_filter($personTimeline, static fn (array $item): bool => ($item['category'] ?? 'notes') === 'notes')); ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-notes" href="#notes" data-bs-toggle="tab">
                            <i class="fa-solid fa-note-sticky me-1"></i><?= gettext('Notes') ?>
                            <?php if ($noteCount > 0) : ?><span class="badge bg-secondary-lt text-secondary ms-1"><?= $noteCount ?></span><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-groups" href="#groups" data-bs-toggle="tab">
                            <i class="fa-solid fa-users me-1"></i><?= gettext('Groups') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-volunteer" href="#volunteer" data-bs-toggle="tab">
                            <i class="fa-solid fa-handshake-angle me-1"></i><?= gettext('Volunteer') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-attendance" href="#attendance" data-bs-toggle="tab">
                            <i class="fa-solid fa-calendar-check me-1"></i><?= gettext('Attendance') ?>
                        </a>
                    </li>
                    <?php if (AuthenticationManager::getCurrentUser()->isFinanceEnabled() && !empty($fam_ID)) : ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-giving" href="#giving" data-bs-toggle="tab">
                            <i class="fa-solid fa-circle-dollar-to-slot me-1"></i><?= gettext("Giving History") ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($portalSignIn !== null) : ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-portal" href="#portal" data-bs-toggle="tab">
                            <i class="fa-solid fa-right-to-bracket me-1"></i><?= gettext('Portal sign-in') ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-timeline" href="#timeline" data-bs-toggle="tab">
                            <i class="fa-solid fa-clock me-1"></i><?= gettext('Timeline') ?>
                        </a>
                    </li>
                    <!-- Plugin tabs will be dynamically added here by JavaScript -->
                    <li class="nav-item d-none" id="nav-item-mailchimp-container">
                        <a class="nav-link" id="nav-item-mailchimp" href="#mailchimp" data-bs-toggle="tab">
                            <i class="fa-brands fa-mailchimp me-1"></i>Mailchimp
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">

                    <div class="tab-pane active" id="overview">
                        <div class="row g-4">
                            <div class="col-12 col-lg-7">
                                <?php if ($hasCustomFields) : ?>
                                <div class="mb-4" id="person-custom-fields-card">
                                    <h4 class="mb-3"><i class="fa-solid fa-circle-info me-1"></i> <?= gettext('Additional Information') ?></h4>
                                    <div class="row g-3"><?= $customFieldsHtml ?></div>
                                </div>
                                <?php endif; ?>
        <!-- Properties Card (sidebar) -->
        <div class="mb-4">
            <div class="d-flex align-items-center mb-2">
                <h4 class="m-0"><i class="fa-solid fa-hashtag me-1"></i> <?= gettext('Properties') ?></h4>
            </div>
            <div>
                <?php if (count($assignedPersonProperties) === 0) : ?>
                    <p class="text-body-secondary mb-0"><?= gettext('No properties assigned.') ?></p>
                <?php else : ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($assignedPersonProperties as $rp) {
                            $prop     = $rp->getProperty();
                            $propType = $prop->getPropertyType();
                            $value    = $rp->getPropertyValue(); ?>
                            <div class="list-group-item px-0 d-flex align-items-center">
                                <div class="me-auto">
                                    <strong><?= InputUtils::escapeHTML($prop->getProName()) ?></strong>
                                    <?php if ($propType) { ?>
                                        <span class="badge bg-secondary-lt text-secondary ms-1"><?= InputUtils::escapeHTML($propType->getPrtName()) ?></span>
                                    <?php } ?>
                                    <?php if (!empty($value)) { ?>
                                        <small class="text-body-secondary d-block"><?= InputUtils::escapeHTML($value) ?></small>
                                    <?php } ?>
                                </div>
                                <?php if ($bOkToEdit) { ?>
                                    <button class="btn btn-sm btn-ghost-danger remove-property-btn" data-property_id="<?= $rp->getPropertyId() ?>" title="<?= gettext('Remove') ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php endif; ?>

                <?php if ($bOkToEdit && count($allPersonProperties) !== 0) : ?>
                    <div class="mt-3 d-print-none">
                        <form method="post" action="<?= SystemURLs::getRootPath() . '/api/properties/persons/assign' ?>" id="assign-property-form">
                            <div class="d-flex gap-2 align-items-start">
                            <div class="flex-grow-1 min-w-0">
                                <select name="PropertyId" id="input-person-properties" class="form-select" data-placeholder="<?= gettext('Choose a property...') ?>">
                                    <option value=""></option>
                                    <?php
                                    $assignedPropertyIds = [];
                                    foreach ($assignedPersonProperties as $rp) {
                                        $assignedPropertyIds[] = $rp->getPropertyId();
                                    }
                                    foreach ($allPersonProperties as $prop) {
                                        $pro_ID     = $prop->getProId();
                                        $pro_Name   = $prop->getProName();
                                        $pro_Prompt = $prop->getProPrompt() ?? '';
                                        $attributes = "value=\"{$pro_ID}\"";
                                        if (!empty($pro_Prompt)) {
                                            $pro_Value = '';
                                            foreach ($assignedPersonProperties as $rp) {
                                                if ($rp->getPropertyId() === (int)$pro_ID) {
                                                    $pro_Value = $rp->getPropertyValue();
                                                }
                                            }
                                            $attributes .= " data-pro_Prompt=\"" . InputUtils::escapeAttribute($pro_Prompt) . "\" data-pro_Value=\"" . InputUtils::escapeAttribute($pro_Value) . "\"";
                                        }
                                        $optionText = InputUtils::escapeHTML($pro_Name);
                                        if (in_array($pro_ID, $assignedPropertyIds)) {
                                            $optionText = InputUtils::escapeHTML($pro_Name) . ' (' . gettext('assigned') . ')';
                                        }
                                        echo "<option {$attributes}>{$optionText}</option>";
                                    } ?>
                                </select>
                            </div>
                            <button id="assign-property-btn" type="button" class="btn btn-primary">
                                <i class="fa-solid fa-check me-1"></i><?= gettext('Assign') ?>
                            </button>
                            </div>
                            <div id="prompt-box" class="mt-2"></div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

                            </div>
                            <div class="col-12 col-lg-5">
        <?php
        // Address card — mirrors the family-view Address card. Renders when the
        // person has a mailing address, the family has a distinct mailing address
        // (#9743), OR a map config (family-less person with their own address).
        // The #person-map element must exist whenever $personMapConfig is set, or
        // person-view.js's L.map() call throws.
        $personDirectionsUrl = $person->getDirectionsUrl();
        $personAppleDirectionsUrl = $person->getAppleMapsDirectionsUrl();
        $personFamily = $person->getFamily();
        $familyMailingAddress = $personFamily !== null && $personFamily->hasDistinctMailingAddress()
            ? $personFamily->getSecondaryAddress()
            : '';
        ?>
        <?php if (!empty($formattedMailingAddress) || $familyMailingAddress !== '' || $personMapConfig !== null) : ?>
        <div class="mb-4">
            <div class="d-flex align-items-center mb-2">
                <h4 class="m-0"><i class="fa-solid fa-map me-1"></i> <?= gettext('Address') ?>
                    <?php if ($familyHasCoords) : ?>
                    <span class="badge bg-green-lt text-green ms-2" title="<?= gettext('Address has been geocoded (coordinates stored)') ?>">
                        <i class="fa-solid fa-check"></i> <?= gettext('Geocoded') ?>
                    </span>
                    <?php elseif (!empty($formattedMailingAddress)) : ?>
                    <span class="badge bg-warning text-dark ms-2" title="<?= gettext('Address entered but coordinates not yet set') ?>">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= gettext('Unverified') ?>
                    </span>
                    <?php endif; ?>
                </h4>
            </div>
            <div>
                <?php if (!empty($formattedMailingAddress)) : ?>
                <a href="https://maps.google.com/?q=<?= urlencode($plaintextMailingAddress) ?>" target="_blank" rel="noopener noreferrer"><?= $formattedMailingAddress ?></a>
                <?php endif; ?>
                <?php
                // The address above stays the person's own / inherited primary
                // address. The family's distinct mailing address goes underneath,
                // even when there is no primary address, so it is clear where mail goes.
                if ($familyMailingAddress !== '') : ?>
                <div class="mt-2 text-body-secondary small" id="person-family-mailing-address">
                    <i class="fa-solid fa-envelope me-1"></i><strong><?= gettext('Mailing Address') ?></strong>:
                    <?= InputUtils::escapeHTML($familyMailingAddress) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($formattedMailingAddress)) : ?>
                <div class="mt-2 d-flex flex-wrap gap-1">
                    <?php if (!empty($personDirectionsUrl) || !empty($personAppleDirectionsUrl)) : ?>
                    <div class="btn-group directions-btn-group">
                        <?php if (!empty($personDirectionsUrl)) : ?>
                        <a href="<?= $personDirectionsUrl ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-diamond-turn-right me-1"></i><?= gettext('Get Directions') ?></a>
                        <?php endif; ?>
                        <?php if (!empty($personAppleDirectionsUrl)) : ?>
                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle dropdown-toggle-split directions-provider-toggle d-none" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                            <span class="visually-hidden"><?= gettext('Choose map provider') ?></span>
                        </button>
                        <div class="dropdown-menu directions-provider-menu d-none">
                            <?php if (!empty($personDirectionsUrl)) : ?>
                            <a class="dropdown-item" href="<?= $personDirectionsUrl ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fa-brands fa-google me-2"></i><?= gettext('Open in Google Maps') ?>
                            </a>
                            <?php endif; ?>
                            <a class="dropdown-item apple-maps-option" href="<?= $personAppleDirectionsUrl ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fa-brands fa-apple me-2"></i><?= gettext('Open in Apple Maps') ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fam_ID) && $familyHasCoords) : ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/people/map/neighbors?familyId=<?= $fam_ID ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-people-roof me-1"></i><?= gettext('Find Neighbors') ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($fam_ID) && !$familyHasCoords && AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) : ?>
                    <button type="button" class="btn btn-sm btn-outline-success" id="refresh-coordinates-btn" data-family-id="<?= $fam_ID ?>" title="<?= gettext('Automatically detect coordinates using address') ?>">
                        <i class="fa-solid fa-location-dot me-1"></i><?= gettext('Refresh Coordinates') ?>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($personMapConfig !== null) : ?>
                <div class="mt-2 rounded overflow-hidden">
                    <div id="person-map" class="map-canvas-sm"></div>
                </div>
                <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                    window.CRM = window.CRM || {};
                    window.CRM.personMapConfig = <?= InputUtils::jsonEncodeForScript($personMapConfig) ?>;
                </script>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

                            </div>
                        </div>
                    </div>

                    <div class="tab-pane timeline-container" id="notes">
                        <?php $timeline = $personTimeline; $timelineScope = 'notes'; include __DIR__ . '/timeline-events.php'; ?>
                    </div>

                    <div class="tab-pane timeline-container" id="timeline" data-timeline-default="all">
                        <?php $timeline = $personTimeline; $timelineScope = 'activity'; include __DIR__ . '/timeline-events.php'; ?>
                    </div>

                    <div class="tab-pane" id="groups">
                        <?php
                        $sAssignedGroups = ',';
                        if (count($assignedGroupsData) === 0) {
                        ?>
                            <div class="text-center text-body-secondary py-4">
                                <i class="fa-solid fa-users fa-2x mb-2 d-block opacity-50"></i>
                                <p class="mb-1"><?= gettext('No group assignments yet.') ?></p>
                                <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) { ?>
                                    <a href="#" id="addGroupFromEmpty" class="btn btn-sm btn-outline-primary mt-2"><i class="fa-solid fa-plus me-1"></i><?= gettext('Assign to a Group') ?></a>
                                <?php } ?>
                            </div>
                        <?php
                        } else { ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($assignedGroupsData as $aRow) {
                                    $grp_ID           = $aRow['grp_ID'];
                                    $grp_Name         = $aRow['grp_Name'];
                                    $grp_Type         = $aRow['grp_Type'];
                                    $grp_hasSpecialProps = $aRow['grp_hasSpecialProps'];
                                    $roleId           = $aRow['roleId'];
                                    $roleName         = $aRow['roleName'];
                                    $groupTypeName    = $aRow['groupTypeName'];
                                    $sAssignedGroups .= $grp_ID . ','; ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center">
                                            <div class="me-auto">
                                                <a href="<?= InputUtils::escapeAttribute($aRow['viewUri']) ?>" class="fw-bold"><?= InputUtils::escapeHTML($grp_Name) ?></a>
                                                <?php if ((int)$grp_Type !== 0) { ?>
                                                <span class="badge bg-info-lt text-info ms-2"><?= InputUtils::escapeHTML($groupTypeName) ?></span>
                                                <?php } ?>
                                                <span class="badge bg-secondary-lt text-secondary ms-1"><?= InputUtils::escapeHTML(gettext($roleName)) ?></span>
                                                <?php
                                                if ($grp_hasSpecialProps) {
                                                    $grp_ID_int = (int)$grp_ID;
                                                    $sSQL = 'SELECT groupprop_master.* FROM groupprop_master WHERE grp_ID = ' . $grp_ID_int . " AND prop_PersonDisplay = 'true' ORDER BY prop_ID";
                                                    $rsPropList = RunQuery($sSQL);
                                                    $sSQL = 'SELECT * FROM groupprop_' . $grp_ID_int . ' WHERE per_ID = ' . $iPersonID;
                                                    $rsPersonProps = RunQuery($sSQL);
                                                    $aPersonProps = mysqli_fetch_array($rsPersonProps, MYSQLI_BOTH);
                                                    while ($aProps = mysqli_fetch_array($rsPropList)) {
                                                        $prop_Name    = $aProps['prop_Name'];
                                                        $prop_Field   = $aProps['prop_Field'];
                                                        $type_ID      = $aProps['type_ID'];
                                                        $prop_Special = $aProps['prop_Special'] ?? null;
                                                        $currentData  = isset($aPersonProps[$prop_Field]) ? trim($aPersonProps[$prop_Field]) : '';
                                                        if (strlen($currentData) > 0) {
                                                            if ((int)$type_ID === 11) {
                                                                $prop_Special = null;
                                                            }
                                                            echo '<br><small class="text-body-secondary"><strong>' . InputUtils::escapeHTML($prop_Name) . '</strong>: ' . InputUtils::escapeHTML(CustomFieldUtils::display($type_ID, $currentData, $prop_Special)) . '</small>';
                                                        }
                                                    }
                                                } ?>
                                            </div>
                                            <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) { ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="<?= InputUtils::escapeAttribute($aRow['viewUri']) ?>"><i class="fa-solid fa-eye me-2"></i><?= gettext('View Group') ?></a>
                                                    <a class="dropdown-item changeRole" data-groupid="<?= $grp_ID ?>" data-current-role-id="<?= (int)$roleId ?>"><i class="fa-solid fa-user-tag me-2"></i><?= gettext('Change Role') ?></a>
                                                    <?php if ($grp_hasSpecialProps) { ?>
                                                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/groups/<?= $grp_ID ?>/members/<?= $iPersonID ?>/properties"><i class="fa-solid fa-sliders me-2"></i><?= gettext('Update Properties') ?></a>
                                                    <?php } ?>
                                                    <div class="dropdown-divider"></div>
                                                    <button class="dropdown-item text-danger groupRemove" data-groupid="<?= (int)$grp_ID ?>" data-groupname="<?= InputUtils::escapeAttribute($grp_Name) ?>"><i class="fa-solid fa-trash-can me-2"></i><?= gettext('Remove') ?></button>
                                                </div>
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php
                    $volunteerQualifications = $volunteerQualifications ?? [];
                    $volunteerAssignments    = $volunteerAssignments ?? [];
                    $volunteerEmpty          = count($volunteerQualifications) === 0
                                              && count($volunteerAssignments) === 0;
                    $volunteerStatusBadges   = [
                        'pending'  => 'bg-yellow-lt text-yellow',
                        'accepted' => 'bg-green-lt text-green',
                    ];
                    ?>
                    <div class="tab-pane" id="volunteer">
                        <?php if ($volunteerEmpty) : ?>
                            <div class="empty" id="person-volunteer-empty">
                                <div class="empty-icon"><i class="fa-solid fa-handshake-angle fa-2x text-muted"></i></div>
                                <p class="empty-title"><?= gettext('Not volunteering yet') ?></p>
                                <p class="empty-subtitle text-body-secondary">
                                    <?= gettext('Once this person is qualified for a position they appear here, along with the dates they are scheduled to serve.') ?>
                                </p>
                            </div>
                        <?php else : ?>
                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <h4 class="mb-2">
                                        <i class="fa-solid fa-award me-2"></i><?= gettext('Qualified for') ?>
                                    </h4>
                                    <?php if (count($volunteerQualifications) === 0) : ?>
                                        <p class="text-body-secondary" id="person-volunteer-qualifications">
                                            <?= gettext('No qualifications yet.') ?>
                                        </p>
                                    <?php else : ?>
                                        <div class="list-group list-group-flush" id="person-volunteer-qualifications">
                                            <?php foreach ($volunteerQualifications as $aQualification) : ?>
                                                <div class="list-group-item">
                                                    <span class="fw-bold"><?= InputUtils::escapeHTML($aQualification['positionName'] ?? '') ?></span>
                                                    <?php if (!empty($aQualification['ministryName'])) : ?>
                                                        <div class="text-body-secondary small">
                                                            <?php if (!empty($aQualification['ministryId'])) : ?>
                                                                <a href="<?= SystemURLs::getRootPath() ?>/ministries/<?= (int) $aQualification['ministryId'] ?>">
                                                                    <?= InputUtils::escapeHTML($aQualification['ministryName']) ?>
                                                                </a>
                                                            <?php else : ?>
                                                                <?= InputUtils::escapeHTML($aQualification['ministryName']) ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12 col-lg-6">
                                    <h4 class="mb-2">
                                        <i class="fa-solid fa-calendar-check me-2"></i><?= gettext('Serving next') ?>
                                    </h4>
                                    <?php if (count($volunteerAssignments) === 0) : ?>
                                        <p class="text-body-secondary" id="person-volunteer-assignments">
                                            <?= gettext('Nothing scheduled.') ?>
                                        </p>
                                    <?php else : ?>
                                        <div class="list-group list-group-flush" id="person-volunteer-assignments">
                                            <?php foreach ($volunteerAssignments as $aAssignment) : ?>
                                                <a class="list-group-item list-group-item-action"
                                                   href="<?= SystemURLs::getRootPath() ?>/ministries/occurrences/<?= (int) $aAssignment['occurrenceId'] ?>">
                                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                                        <div>
                                                            <span class="fw-bold"><?= InputUtils::escapeHTML($aAssignment['positionName'] ?? '') ?></span>
                                                            <div class="text-body-secondary small">
                                                                <time datetime="<?= InputUtils::escapeAttribute($aAssignment['occurrenceDate'] ?? '') ?>" data-format="date"><?= InputUtils::escapeHTML($aAssignment['occurrenceDate'] ?? '') ?></time>
                                                                <?php if (!empty($aAssignment['ministryName'])) : ?>
                                                                    &middot; <?= InputUtils::escapeHTML($aAssignment['ministryName']) ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <span class="badge <?= $volunteerStatusBadges[$aAssignment['status']] ?? 'bg-secondary-lt text-secondary' ?>">
                                                            <?= $aAssignment['status'] === 'accepted' ? gettext('Accepted') : gettext('Awaiting reply') ?>
                                                        </span>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($person->getEmail()) || !empty($person->getWorkEmail())) : ?>
                    <div class="tab-pane d-none" id="mailchimp">
                        <table class="table">
                            <tr>
                                <th><?= gettext("Type") ?></th>
                                <th><?= gettext("Email") ?></th>
                                <th><?= gettext("Lists") ?></th>
                            </tr>
                            <?php if (!empty($person->getEmail())) : ?>
                            <tr>
                                <td><?= gettext("Home") ?></td>
                                <td><?= $person->getEmail() ?></td>
                                <td id="<?= md5(strtolower($person->getEmail())) ?>" data-loading="true"> ... <?= gettext("loading") ?> ... </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($person->getWorkEmail()) && strtolower($person->getWorkEmail()) !== strtolower($person->getEmail() ?? '')) : ?>
                            <tr>
                                <td><?= gettext("Work") ?></td>
                                <td><?= $person->getWorkEmail() ?></td>
                                <td id="<?= md5(strtolower($person->getWorkEmail())) ?>" data-loading="true"> ... <?= gettext("loading") ?> ... </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if (AuthenticationManager::getCurrentUser()->isFinanceEnabled() && !empty($fam_ID)) : ?>
                    <div class="tab-pane" id="giving">
        <div id="giving-history-card">
            <span id="person-ytd-total-badge" class="badge bg-green-lt text-green mb-2 d-none"></span>
            <div>
                <div id="giving-history-loading" class="text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= gettext("Loading...") ?>
                </div>
                <div id="giving-history-content" class="d-none">
                    <ul class="list-unstyled mb-3">
                        <li class="mb-2">
                            <i class="fa-solid fa-calendar-check me-2 text-muted icon-fixed-width"></i>
                            <strong><?= gettext("YTD Paid") ?>:</strong> <span id="person-giving-ytd" class="text-success fw-bold">$0.00</span>
                        </li>
                        <li class="mb-2" id="person-last-gift-row">
                            <i class="fa-solid fa-hand-holding-dollar me-2 text-muted icon-fixed-width"></i>
                            <strong><?= gettext("Last Gift") ?>:</strong> <span id="person-last-gift">—</span>
                        </li>
                    </ul>
                    <a href="<?= SystemURLs::getRootPath() ?>/people/family/<?= (int)$fam_ID ?>#giving-history" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= gettext("View Full Family Giving") ?>
                    </a>
                </div>
                <div id="giving-history-empty" class="d-none text-muted">
                    <i class="fa-solid fa-circle-info me-1"></i> <?= gettext("No giving history found.") ?>
                </div>
            </div>
        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($portalSignIn !== null) : ?>
                    <div class="tab-pane" id="portal">
            <?php include __DIR__ . '/partials/portal-signin-card.php'; ?>
                    </div>
                    <?php endif; ?>

                    <div class="tab-pane" id="attendance">
                        <?php include __DIR__ . '/partials/attendance-tab.php'; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php if ($loginAsPersonHistory !== null) : ?>
            <div class="mt-3"><?php include __DIR__ . '/partials/login-as-person-history.php'; ?></div>
        <?php endif; ?>
        <!-- Modal -->
        <div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog" aria-labelledby="delete-Image-label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        <h4 class="modal-title" id="delete-Image-label"><?= gettext('Confirm Delete') ?></h4>
                    </div>

                    <div class="modal-body">
                        <p><?= gettext('You are about to delete the profile photo, this procedure is irreversible.') ?></p>

                        <p><?= gettext('Do you want to proceed?') ?></p>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext("Cancel") ?></button>
                        <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>
                    </div>
                </div>
            </div>
        </div>
        <script nonce="<?= SystemURLs::getCSPNonce() ?>">
            window.CRM.currentPersonID = <?= (int) $iPersonID ?>;
            window.CRM.currentFY = <?= InputUtils::jsonEncodeForScript(FinancialService::formatFiscalYear(FiscalYearUtils::getCurrentFiscalYearId() ?? 1)) ?>;
            window.CRM.personView = <?= InputUtils::jsonEncodeForScript([
                'id' => (int) $iPersonID,
                'name' => $person->getFullName(),
                'active' => $person->isActive(),
                'famId' => (int) $fam_ID,
                'canEdit' => (bool) $bOkToEdit,
                'photoWidth' => Photo::PHOTO_WIDTH,
                'photoHeight' => Photo::PHOTO_HEIGHT,
            ]) ?>;
        </script>

<?php require_once __DIR__ . '/delete-note-modal.php'; ?>

<?php
echo '<script src="' . SystemURLs::assetVersioned('/skin/v2/note-why-came-modal.min.js') . '"></script>';
require_once SystemURLs::getDocumentRoot() . '/Include/Footer.php';
