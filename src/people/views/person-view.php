<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

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

<div class="row">
    <div class="col-lg-4">
        <!-- Photo & Info Card -->
        <div class="card mb-3">
            <div class="card-body p-0">
                <div class="d-flex">
                    <!-- Photo (left) — click to upload -->
                    <div class="flex-shrink-0 position-relative" style="width: 120px; aspect-ratio: 1 / 1;">
                        <a href="#" id="uploadImageButton" class="d-block w-100 h-100" title="<?= $bOkToEdit ? gettext("Click to upload photo") : gettext("View Photo") ?>">
                            <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" alt="" class="photo-profile w-100 h-100 object-fit-cover" style="border-radius: var(--tblr-border-radius) 0 0 var(--tblr-border-radius);">
                        </a>
                        <button type="button"
                                class="photo-view-overlay btn btn-sm position-absolute bottom-0 end-0 m-1 d-none"
                                data-entity-type="person"
                                data-entity-id="<?= $person->getId() ?>"
                                title="<?= gettext('View full photo') ?>"
                                aria-label="<?= gettext('View full photo') ?>">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true" style="color:white; text-shadow: 0 1px 3px rgba(0,0,0,.8);"></i>
                        </button>
                    </div>
                    <!-- Attributes (right) -->
                    <div class="p-3 flex-grow-1">
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
                        <ul class="list-unstyled mb-0">
                            <li class="mb-1"><i class="fa <?= $genderClass ?> me-2 text-body-secondary" style="width: 1rem; text-align: center;"></i><?= $genderText ?></li>
                            <li class="mb-1"><i class="fa-solid fa-id-card me-2 text-body-secondary" style="width: 1rem; text-align: center;"></i><?= InputUtils::escapeHTML(gettext($sClassName)) ?></li>
                            <?php if (!empty($sFamRole)) : ?>
                            <li class="mb-1"><i class="fa-solid fa-users me-2 text-body-secondary" style="width: 1rem; text-align: center;"></i><?= InputUtils::escapeHTML(gettext($sFamRole)) ?></li>
                            <?php endif; ?>
                            <?php if ($per_MembershipDate) : ?>
                            <li class="mb-1"><i class="fa-solid fa-calendar-check me-2 text-body-secondary" style="width: 1rem; text-align: center;"></i><?= gettext('Since') ?> <?= DateTimeUtils::formatDate($per_MembershipDate, false) ?></li>
                            <?php endif; ?>
                            <?php if ($sEnvelope !== gettext('Not assigned')) : ?>
                            <li class="mb-1"><i class="fa-solid fa-envelope me-2 text-body-secondary" style="width: 1rem; text-align: center;"></i><?= gettext('Envelope') ?> #<?= $sEnvelope ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact & Personal Info -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Contact & Personal Info') ?></h3>
            </div>
            <div class="card-body">

                <!-- Personal Information -->
                <?php if ($dBirthDate || (!SystemConfig::getBooleanValue('bHideFriendDate') && $per_FriendDate !== '')) : ?>
                <div class="mb-3">
                    <h6 class="text-body-secondary mb-2"><i class="fa-solid fa-user me-1"></i><?= gettext('Personal') ?></h6>
                    <ul class="list-unstyled ms-3">
                        <?php if ($dBirthDate) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-cake-candles me-2 text-body-secondary"></i>
                            <?= $dBirthDate ?>
                            <?php if (!$person->hideAge()) : ?>
                                <span class="text-body-secondary">(<?= $person->getAge() ?>)</span>
                            <?php endif; ?>
                        </li>
                        <?php endif; ?>
                        <?php if (!SystemConfig::getBooleanValue('bHideFriendDate') && $per_FriendDate !== '') : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-handshake me-2 text-body-secondary"></i>
                            <?= gettext('Friend Date') ?>: <?= DateTimeUtils::formatDate($per_FriendDate, false) ?>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Phone Numbers -->
                <?php if ($sCellPhone || $sHomePhone || $sWorkPhone) : ?>
                <div class="mb-3">
                    <h6 class="text-body-secondary mb-2"><i class="fa-solid fa-phone me-1"></i><?= gettext('Phone') ?></h6>
                    <ul class="list-unstyled ms-3">
                        <?php if ($sCellPhone) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-mobile-screen me-2 text-body-secondary"></i>
                            <a href="tel:<?= InputUtils::escapeAttribute($sCellPhoneUnformatted) ?>"><?= $sCellPhone ?></a>
                            <a href="sms:<?= InputUtils::escapeAttribute(preg_replace('/[^\d+]/', '', $sCellPhoneUnformatted)) ?>"
                               class="ms-1 text-body-secondary" title="<?= gettext('Send text message') ?>">
                                <i class="fa-solid fa-comment-sms"></i>
                            </a>
                            <button class="btn btn-sm btn-ghost-secondary ms-1 copy-phone-btn" type="button"
                                    data-phone="<?= InputUtils::escapeAttribute($sCellPhone) ?>"
                                    title="<?= gettext('Copy to clipboard') ?>">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            <small class="text-body-secondary">(<?= gettext('Mobile') ?>)</small>
                        </li>
                        <?php endif; ?>
                        <?php if ($sHomePhone) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-house me-2 text-body-secondary"></i>
                            <a href="tel:<?= InputUtils::escapeAttribute($sHomePhoneUnformatted) ?>"><?= $sHomePhone ?></a>
                            <button class="btn btn-sm btn-ghost-secondary ms-1 copy-phone-btn" type="button"
                                    data-phone="<?= InputUtils::escapeAttribute($sHomePhone) ?>"
                                    title="<?= gettext('Copy to clipboard') ?>">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            <small class="text-body-secondary">(<?= gettext('Home') ?>)</small>
                        </li>
                        <?php endif; ?>
                        <?php if ($sWorkPhone) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-briefcase me-2 text-body-secondary"></i>
                            <a href="tel:<?= InputUtils::escapeAttribute($sWorkPhoneUnformatted) ?>"><?= $sWorkPhone ?></a>
                            <button class="btn btn-sm btn-ghost-secondary ms-1 copy-phone-btn" type="button"
                                    data-phone="<?= InputUtils::escapeAttribute($sWorkPhone) ?>"
                                    title="<?= gettext('Copy to clipboard') ?>">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            <small class="text-body-secondary">(<?= gettext('Work') ?>)</small>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Email -->
                <?php if (!empty($sEmail) || !empty($per_WorkEmail)) : ?>
                <div class="mb-3">
                    <h6 class="text-body-secondary mb-2"><i class="fa-solid fa-envelope me-1"></i><?= gettext('Email') ?></h6>
                    <ul class="list-unstyled ms-3">
                        <?php if (!empty($sEmail)) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-at me-2 text-body-secondary"></i>
                            <a href="mailto:<?= InputUtils::escapeAttribute($sUnformattedEmail) ?>" target="_blank" rel="noopener noreferrer"><?= $sEmail ?></a>
                            <button class="btn btn-sm btn-ghost-secondary ms-1 copy-email-btn" type="button"
                                    data-email="<?= InputUtils::escapeAttribute($sUnformattedEmail) ?>"
                                    title="<?= gettext('Copy to clipboard') ?>">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($per_WorkEmail)) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-briefcase me-2 text-body-secondary"></i>
                            <a href="mailto:<?= InputUtils::escapeAttribute($per_WorkEmail) ?>" target="_blank" rel="noopener noreferrer"><?= $per_WorkEmail ?></a>
                            <button class="btn btn-sm btn-ghost-secondary ms-1 copy-email-btn" type="button"
                                    data-email="<?= InputUtils::escapeAttribute($per_WorkEmail) ?>"
                                    title="<?= gettext('Copy to clipboard') ?>">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            <small class="text-body-secondary">(<?= gettext('Work') ?>)</small>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Social Media -->
                <?php if (strlen($per_Facebook) > 0 || strlen($per_Twitter) > 0 || strlen($per_LinkedIn) > 0) : ?>
                <div class="mb-3">
                    <h6 class="text-body-secondary mb-2"><i class="fa-solid fa-share-nodes me-1"></i><?= gettext('Social Media') ?></h6>
                    <ul class="list-unstyled ms-3">
                        <?php if (strlen($per_Facebook) > 0) : ?>
                        <li class="mb-2">
                            <i class="fa-brands fa-facebook me-2 text-primary"></i>
                            <a href="https://www.facebook.com/<?= InputUtils::escapeAttribute($per_Facebook) ?>" target="_blank"><?= InputUtils::escapeHTML($per_Facebook) ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (strlen($per_Twitter) > 0) : ?>
                        <li class="mb-2">
                            <i class="fa-brands fa-x-twitter me-2 text-dark"></i>
                            <a href="https://www.twitter.com/<?= InputUtils::escapeAttribute($per_Twitter) ?>" target="_blank">@<?= InputUtils::escapeHTML($per_Twitter) ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (strlen($per_LinkedIn) > 0) : ?>
                        <li class="mb-2">
                            <i class="fa-brands fa-linkedin me-2 text-info"></i>
                            <a href="https://www.linkedin.com/in/<?= InputUtils::escapeAttribute($per_LinkedIn) ?>" target="_blank"><?= InputUtils::escapeHTML($per_LinkedIn) ?></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Custom Fields -->
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
                            // Sanitize phone number for tel: URI
                            $sanitizedPhone = preg_replace('/[^0-9+\-()e]/', '', $currentData);
                            $displayLink = "tel:" . $sanitizedPhone;
                        }
                        $customFieldsHtml .= '<li class="mb-2">';
                        $customFieldsHtml .= '<i class="' . $displayIcon . ' me-2 text-body-secondary"></i>';
                        $temp_string = nl2br(CustomFieldUtils::display($type_ID, $currentData, $custom_Special));
                        if ($displayLink) {
                            $customFieldsHtml .= '<strong>' . InputUtils::escapeHTML($custom_Name) . ':</strong> <a href="' . InputUtils::escapeAttribute($displayLink) . '">' . $temp_string . '</a>';
                        } else {
                            $customFieldsHtml .= '<strong>' . InputUtils::escapeHTML($custom_Name) . ':</strong> ' . $temp_string;
                        }
                        $customFieldsHtml .= '</li>';
                    }
                }
                if ($hasCustomFields) : ?>
                <div class="mb-3">
                    <h6 class="text-body-secondary mb-2"><i class="fa-solid fa-circle-info me-1"></i><?= gettext('Additional Information') ?></h6>
                    <ul class="list-unstyled ms-3">
                        <?= $customFieldsHtml ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Properties Card (sidebar) -->
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Properties') ?></h3>
            </div>
            <div class="card-body">
                <?php if (count($assignedPersonProperties) === 0) : ?>
                    <div class="text-center text-body-secondary py-3">
                        <i class="fa-solid fa-tags fa-2x mb-2 d-block opacity-50"></i>
                        <p class="mb-0"><?= gettext('No properties assigned.') ?></p>
                    </div>
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
                            <div class="mb-2">
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
                            <div id="prompt-box" class="mb-2"></div>
                            <button id="assign-property-btn" type="button" class="btn btn-sm btn-primary w-100">
                                <i class="fa-solid fa-check me-1"></i><?= gettext('Assign') ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <!-- Toolbar -->
        <div class="d-flex align-items-center mb-3 gap-2 d-print-none">
            <?php if ($bOkToEdit) { ?>
            <a class="btn btn-ghost-primary" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $iPersonID ?>" title="<?= gettext("Edit Person") ?>"><i class="fa-solid fa-pen me-1"></i><?= gettext("Edit") ?></a>
            <?php } ?>
            <button class="btn btn-ghost-secondary" id="printPerson" title="<?= gettext("Print") ?>"><i class="fa-solid fa-print me-1"></i><?= gettext("Print") ?></button>
            <button class="btn btn-ghost-success AddToCart" id="AddPersonToCart" data-cart-id="<?= $iPersonID ?>" data-cart-type="person" title="<?= gettext("Add to Cart") ?>"><i class="fa-solid fa-cart-plus me-1"></i><span class="cartActionDescription"><?= gettext("Cart") ?></span></button>
            <div class="dropdown">
                <button class="btn btn-ghost-secondary dropdown-toggle" id="person-actions-dropdown" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="fa-solid fa-ellipsis-vertical me-1"></i><?= gettext("Actions") ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa-solid fa-note-sticky me-2"></i><?= gettext("Add Note") ?></a>
                        <a class="dropdown-item" id="editWhyCame" href="<?= SystemURLs::getRootPath() ?>/WhyCameEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa-solid fa-circle-question me-2"></i><?= gettext("Why Came") ?></a>
                    <?php } ?>
                    <?php if ($bOkToEdit && $fam_ID !== '') { ?>
                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $fam_ID ?>"><i class="fa-solid fa-people-roof me-2"></i><?= gettext("Edit Family") ?></a>
                        <a class="dropdown-item" id="edit-role-btn" data-person_id="<?= $person->getId() ?>" data-family_role="<?= $person->getFamilyRoleName() ?>" data-family_role_id="<?= $person->getFmrId() ?>"><i class="fa-solid fa-user-tag me-2"></i><?= gettext("Change Family Role") ?></a>
                    <?php } ?>
                    <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) { ?>
                        <a class="dropdown-item" id="addGroup"><i class="fa-solid fa-users me-2"></i><?= gettext("Assign New Group") ?></a>
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
                    <?php if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger delete-person" id="deletePersonBtn" data-person_name="<?= $person->getFullName() ?>" data-person_id="<?= $iPersonID ?>"><i class="fa-solid fa-trash-can me-2"></i><?= gettext("Delete Person") ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Family Members Card -->
        <?php if ($fam_ID !== '' && $person->getFamily() !== null) { ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-people-roof me-1"></i><?= InputUtils::escapeHTML($fam_Name) ?> <?= gettext('Family') ?></h3>
                <div class="ms-auto d-flex gap-1">
                    <?php if ($bOkToEdit) { ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $fam_ID ?>" class="btn btn-sm btn-ghost-secondary" title="<?= gettext('Edit Family') ?>"><i class="fa-solid fa-pen"></i></a>
                    <?php } ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/v2/family/<?= $person->getFamId() ?>" class="btn btn-sm btn-ghost-primary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= gettext('View') ?></a>
                </div>
            </div>
            <div style="overflow: visible;">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th><?= gettext('Name') ?></th>
                            <th class="text-center"><?= gettext('Role') ?></th>
                            <th><?= gettext('Birthday') ?></th>
                            <th><?= gettext('Email') ?></th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($person->getFamily()->getPeopleSorted() as $familyMember) {
                            $tmpPersonId = $familyMember->getId();
                            $isSelf = ($tmpPersonId === $iPersonID);
                        ?>
                            <tr<?= $isSelf ? ' class="bg-light"' : '' ?>>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img data-image-entity-type="person" data-image-entity-id="<?= $familyMember->getId() ?>" class="avatar avatar-sm me-2">
                                        <?php if ($isSelf) { ?>
                                            <span class="fw-bold"><?= $familyMember->getFullName() ?></span>
                                            <i class="fa-solid fa-circle-user text-primary ms-2" title="<?= gettext('Current person') ?>"></i>
                                        <?php } else { ?>
                                            <a href="<?= $familyMember->getViewURI() ?>"><?= $familyMember->getFullName() ?></a>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-lt text-secondary"><?= $familyMember->getFamilyRoleName() ?></span>
                                </td>
                                <td><?= $familyMember->getFormattedBirthDate(); ?></td>
                                <td>
                                    <?php $tmpEmail = $familyMember->getEmail();
                                    if ($tmpEmail !== '') { ?>
                                        <a href="mailto:<?= InputUtils::escapeAttribute($tmpEmail) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($tmpEmail) ?></a>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!$isSelf) { ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $tmpPersonId ?>"><i class="fa-solid fa-pen me-2"></i><?= gettext('Edit') ?></a>
                                            <button class="dropdown-item AddToCart" data-cart-id="<?= $tmpPersonId ?>" data-cart-type="person"><i class="fa-solid fa-cart-plus me-2"></i><?= gettext('Add to Cart') ?></button>
                                            <?php if ($bOkToEdit) { ?>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item text-danger delete-person" data-person_name="<?= $familyMember->getFullName() ?>" data-person_id="<?= $familyMember->getId() ?>" data-view="family"><i class="fa-solid fa-trash-can me-2"></i><?= gettext('Delete') ?></button>
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
            <?php if (!empty($formattedMailingAddress)) : ?>
            <div class="card-footer">
                <div class="d-flex align-items-start gap-3">
                    <div>
                        <i class="fa-solid fa-location-dot me-1 text-body-secondary"></i>
                        <a href="https://maps.google.com/?q=<?= urlencode($plaintextMailingAddress) ?>" target="_blank" rel="noopener noreferrer"><?= $formattedMailingAddress ?></a>
                        <?php
                        $personDirectionsUrl = $person->getDirectionsUrl();
                        $personAppleDirectionsUrl = $person->getAppleMapsDirectionsUrl();
                        ?>
                        <?php if (!empty($personDirectionsUrl) || !empty($personAppleDirectionsUrl)) : ?>
                            <div class="btn-group ms-2 directions-btn-group">
                                <?php if (!empty($personDirectionsUrl)) : ?>
                                    <a href="<?= $personDirectionsUrl ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost-primary"><i class="fa-solid fa-diamond-turn-right me-1"></i><?= gettext('Directions') ?></a>
                                <?php endif; ?>
                                <?php if (!empty($personAppleDirectionsUrl)) : ?>
                                <button type="button" class="btn btn-sm btn-ghost-primary dropdown-toggle dropdown-toggle-split directions-provider-toggle d-none" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
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
                        <?php if (!empty($fam_ID) && !$familyHasCoords) : ?>
                            <button type="button" class="btn btn-sm btn-ghost-success ms-1" id="refresh-coordinates-btn" data-family-id="<?= $fam_ID ?>" title="<?= gettext('Refresh Coordinates') ?>">
                                <i class="fa-solid fa-location-dot"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.css') ?>">
                <?php if ($personMapConfig !== null) : ?>
                <div class="mt-2">
                    <div id="person-map" style="height: 150px; border-radius: 4px;"></div>
                </div>
                <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                    window.CRM = window.CRM || {};
                    window.CRM.personMapConfig = <?= json_encode($personMapConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                </script>
                <?php endif; ?>
                <script src="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.js') ?>"></script>
                <script src="<?= SystemURLs::assetVersioned('/skin/v2/people-person-view.min.js') ?>"></script>
            </div>
            <?php endif; ?>
        </div>
        <?php } ?>

        <!-- Tabbed Content -->
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <ul class="nav nav-pills card-header-pills">
                    <li class="nav-item">
                        <a class="nav-link active" id="nav-item-timeline" href="#timeline" data-bs-toggle="tab">
                            <i class="fa-solid fa-clock me-1"></i><?= gettext('Timeline') ?>
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
                    <!-- Plugin tabs will be dynamically added here by JavaScript -->
                    <li class="nav-item d-none" id="nav-item-mailchimp-container">
                        <a class="nav-link" id="nav-item-mailchimp" href="#mailchimp" data-bs-toggle="tab">
                            <i class="fa-brands fa-mailchimp me-1"></i><?= gettext('Mailchimp') ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">

                    <div class="tab-pane active timeline-container" id="timeline">
                        <?php
                        if (empty($personTimeline)) { ?>
                            <div class="alert alert-info mt-3">
                                <i class="fa-solid fa-circle-info fa-fw fa-lg"></i>
                                <span><?= gettext('No timeline events yet.') ?></span>
                            </div>
                        <?php } else {
                            $timelineCounts = ['notes' => 0, 'events' => 0, 'system' => 0];
                            foreach ($personTimeline as $tlItem) {
                                $cat = $tlItem['category'] ?? 'notes';
                                if (isset($timelineCounts[$cat])) {
                                    $timelineCounts[$cat]++;
                                }
                            }
                            ?>
                            <div class="timeline-filters d-flex flex-wrap align-items-center gap-2 mt-3 mb-1" role="group" aria-label="<?= gettext('Timeline filters') ?>">
                                <span class="text-body-secondary small me-1"><i class="fa-solid fa-filter me-1"></i><?= gettext('Show:') ?></span>
                                <button type="button" class="btn btn-sm btn-primary timeline-filter-chip active" data-filter="notes">
                                    <i class="fa-solid fa-note-sticky me-1"></i><?= gettext('Notes') ?>
                                    <span class="badge bg-white text-primary ms-1"><?= $timelineCounts['notes'] ?></span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary timeline-filter-chip" data-filter="events">
                                    <i class="fa-solid fa-calendar-days me-1"></i><?= gettext('Events') ?>
                                    <span class="badge bg-secondary-lt text-secondary ms-1"><?= $timelineCounts['events'] ?></span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary timeline-filter-chip" data-filter="system">
                                    <i class="fa-solid fa-gear me-1"></i><?= gettext('System') ?>
                                    <span class="badge bg-secondary-lt text-secondary ms-1"><?= $timelineCounts['system'] ?></span>
                                </button>
                                <button type="button" class="btn btn-sm btn-link ms-auto timeline-filter-all" data-filter="all">
                                    <?= gettext('Show all') ?>
                                </button>
                            </div>
                            <div class="timeline-empty-notice alert alert-info" style="display:none;">
                                <i class="fa-solid fa-circle-info fa-fw me-1"></i><?= gettext('No matching entries for the selected filters.') ?>
                            </div>
                            <?php $currentYear = ''; ?>
                            <div class="timeline mt-3">
                                <?php foreach ($personTimeline as $item) {
                                    if ($currentYear !== $item['year']) {
                                        $currentYear = $item['year']; ?>
                                        <div class="hr-text timeline-year" data-timeline-year="<?= htmlspecialchars((string)$currentYear) ?>"> <i class="fa-solid fa-calendar-days"></i> <?= $currentYear ?></div>
                                    <?php } ?>
                                    <div class="timeline-event" data-timeline-category="<?= htmlspecialchars($item['category'] ?? 'notes') ?>" data-timeline-year="<?= htmlspecialchars((string)$item['year']) ?>">
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
                                                                <?php if (!empty($item['headerLink'])) { ?>
                                                                    <a href="<?= $item['headerLink'] ?>"><?= $item['header'] ?></a>
                                                                <?php } else { ?>
                                                                    <?= $item['header'] ?>
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
                                                <small class="text-body-secondary"><i class="fa-solid fa-clock me-1"></i><?= $item['datetime'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
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
                                                <a href="<?= SystemURLs::getRootPath() ?>/groups/view/<?= $grp_ID ?>" class="fw-bold"><?= InputUtils::escapeHTML($grp_Name) ?></a>
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
                                                            echo '<br><small class="text-body-secondary"><strong>' . InputUtils::escapeHTML($prop_Name) . '</strong>: ' . CustomFieldUtils::display($type_ID, $currentData, $prop_Special) . '</small>';
                                                        }
                                                    }
                                                } ?>
                                            </div>
                                            <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) { ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/groups/view/<?= $grp_ID ?>"><i class="fa-solid fa-eye me-2"></i><?= gettext('View Group') ?></a>
                                                    <a class="dropdown-item changeRole" data-groupid="<?= $grp_ID ?>" data-current-role-id="<?= (int)$roleId ?>"><i class="fa-solid fa-user-tag me-2"></i><?= gettext('Change Role') ?></a>
                                                    <?php if ($grp_hasSpecialProps) { ?>
                                                        <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/GroupPropsEditor.php?GroupID=<?= $grp_ID ?>&PersonID=<?= $iPersonID ?>"><i class="fa-solid fa-sliders me-2"></i><?= gettext('Update Properties') ?></a>
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
                    <div class="tab-pane" id="volunteer">
                        <?php
                        $assignedVolIDs = [];
                        if (count($assignedVolunteerOppsData) === 0) {
                        ?>
                            <div class="text-center text-body-secondary py-4">
                                <i class="fa-solid fa-handshake-angle fa-2x mb-2 d-block opacity-50"></i>
                                <p class="mb-1"><?= gettext('No volunteer opportunity assignments yet.') ?></p>
                            </div>
                        <?php
                        } else {
                            echo '<table class="table table-hover">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>' . gettext('Name') . '</th>';
                            echo '<th>' . gettext('Description') . '</th>';
                            if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
                                echo '<th class="text-end no-export">' . gettext('Actions') . '</th>';
                            }
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';

                            foreach ($assignedVolunteerOppsData as $aRow) {
                                $vol_ID          = $aRow->getId();
                                $vol_Name        = $aRow->getName();
                                $vol_Description = $aRow->getDescription();
                                $assignedVolIDs[] = (int) $vol_ID;
                                echo '<tr>';
                                echo '<td><strong>' . InputUtils::escapeHTML($vol_Name) . '</strong></td>';
                                echo '<td>' . InputUtils::escapeHTML($vol_Description) . '</td>';

                                if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
                                    echo '<td class="text-end">';
                                    echo '<a class="btn btn-sm btn-danger" href="' . SystemURLs::getRootPath() . '/people/view/' . $iPersonID . '?RemoveVO=' . (int)$vol_ID . '" title="' . gettext('Remove') . '">';
                                    echo '<i class="fa-solid fa-trash"></i>';
                                    echo '</a>';
                                    echo '</td>';
                                }

                                echo '</tr>';
                            }
                            echo '</tbody>';
                            echo '</table>';
                        } ?>

                        <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled() && count($allVolunteerOppsData) > 0) : ?>
                            <div class="card mt-3">
                                <div class="card-header d-flex align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fa-solid fa-circle-plus me-2"></i><?= gettext('Assign a New Volunteer Opportunity') ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="<?= SystemURLs::getRootPath() ?>/people/view/<?= $iPersonID ?>">
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="input-volunteer-opportunities"><?= gettext('Select Opportunities') ?></label>
                                                <select id="input-volunteer-opportunities" name="VolunteerOpportunityIDs[]" multiple class="form-select" data-placeholder="<?= gettext('Choose opportunities...') ?>">
                                                    <?php
                                                    foreach ($allVolunteerOppsData as $aRow) {
                                                        $vol_ID   = $aRow->getId();
                                                        $vol_Name = $aRow->getName();
                                                        if (!in_array((int) $vol_ID, $assignedVolIDs, true)) {
                                                            echo '<option value="' . InputUtils::escapeAttribute($vol_ID) . '">' . InputUtils::escapeHTML($vol_Name) . '</option>';
                                                        }
                                                    } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <button type="submit" name="VolunteerOpportunityAssign" class="btn btn-primary">
                                                    <i class="fa-solid fa-check me-1"></i><?= gettext('Assign Opportunities') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
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
                </div>
            </div>
        </div>
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
        <script src="<?= SystemURLs::assetVersioned('/skin/js/MemberView.js') ?>"></script>
        <script src="<?= SystemURLs::assetVersioned('/skin/js/PersonView.js') ?>"></script>
        <script nonce="<?= SystemURLs::getCSPNonce() ?>">
            window.CRM.currentPersonID = <?= $iPersonID ?>;

            $("#deletePhoto").click(function() {
                window.CRM.deletePhoto("person", window.CRM.currentPersonID);
            });

            $(document).ready(function() {
                var volEl = document.getElementById("input-volunteer-opportunities");
                if (volEl && !volEl.tomselect) new TomSelect(volEl, { plugins: ["remove_button"] });
                var propEl = document.getElementById("input-person-properties");
                if (propEl && !propEl.tomselect) new TomSelect(propEl);

                // Attach lightbox click handler to view button
                // Note: Button visibility is managed by avatar-loader.ts based on hasPhoto status
                $("#view-larger-image-btn").click(function() {
                    window.CRM.showPhotoLightbox("person", window.CRM.currentPersonID);
                });

                // Copy photo uploader function from temporary storage to window.CRM
                if (window._CRM_createPhotoUploader) {
                    window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
                } else {
                    console.error('Photo uploader function not found in window._CRM_createPhotoUploader');
                }

                // Initialize Uppy photo uploader (edit-only)
                <?php if ($bOkToEdit): ?>
                if (typeof window.CRM.createPhotoUploader === 'function') {
                    window.CRM.photoUploader = window.CRM.createPhotoUploader({
                        uploadUrl: window.CRM.root + '/api/person/<?= $iPersonID ?>/photo',
                        maxFileSize: window.CRM.maxUploadSizeBytes,
                        photoWidth: <?= Photo::PHOTO_WIDTH ?>,
                        photoHeight: <?= Photo::PHOTO_HEIGHT ?>,
                        onComplete: function(result) {
                            window.location.reload();
                        }
                    });

                    // Both the photo image click and the dropdown "Upload Photo" trigger the uploader
                    $("#uploadImageButton, #uploadPhotoAction").click(function(e) {
                        e.preventDefault();
                        if (window.CRM && window.CRM.photoUploader) {
                            window.CRM.photoUploader.show();
                        } else {
                            console.error('Photo uploader not initialized!');
                        }
                    });
                } else {
                    console.error('window.CRM.createPhotoUploader is not a function');
                }
                <?php endif; ?>

            });
        </script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    function initTimelineFilter(container) {
        if (!container) { return; }
        var chips = container.querySelectorAll(".timeline-filter-chip");
        var allChip = container.querySelector(".timeline-filter-all");
        var events = container.querySelectorAll(".timeline-event[data-timeline-category]");
        var years = container.querySelectorAll(".timeline-year");
        var emptyNotice = container.querySelector(".timeline-empty-notice");
        if (chips.length === 0 || events.length === 0) { return; }

        var active = new Set(["notes"]);

        function applyFilter() {
            var showAll = active.has("all");
            var visibleYears = {};
            var anyVisible = false;
            events.forEach(function (el) {
                var cat = el.getAttribute("data-timeline-category") || "notes";
                var visible = showAll || active.has(cat);
                el.style.display = visible ? "" : "none";
                if (visible) {
                    anyVisible = true;
                    var yr = el.getAttribute("data-timeline-year") || "";
                    visibleYears[yr] = true;
                }
            });
            years.forEach(function (y) {
                var yr = y.getAttribute("data-timeline-year") || "";
                y.style.display = visibleYears[yr] ? "" : "none";
            });
            if (emptyNotice) {
                emptyNotice.style.display = anyVisible ? "none" : "";
            }
            chips.forEach(function (chip) {
                var f = chip.getAttribute("data-filter");
                var on = showAll || active.has(f);
                chip.classList.toggle("btn-primary", on);
                chip.classList.toggle("active", on);
                chip.classList.toggle("btn-outline-secondary", !on);
            });
            if (allChip) {
                allChip.classList.toggle("active", showAll);
            }
        }

        chips.forEach(function (chip) {
            chip.addEventListener("click", function () {
                var f = chip.getAttribute("data-filter");
                if (!f) { return; }
                active.delete("all");
                if (active.has(f)) {
                    active.delete(f);
                } else {
                    active.add(f);
                }
                if (active.size === 0) {
                    active.add("notes");
                }
                applyFilter();
            });
        });

        if (allChip) {
            allChip.addEventListener("click", function () {
                active = new Set(["all"]);
                applyFilter();
            });
        }

        applyFilter();
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".timeline-container").forEach(initTimelineFilter);
    });
})();

(function () {
    function isAppleMobile() {
        var ua = navigator.userAgent || "";
        if (/iPad|iPhone|iPod/.test(ua) && !window.MSStream) {
            return true;
        }
        if (navigator.platform === "MacIntel" && typeof navigator.maxTouchPoints === "number" && navigator.maxTouchPoints > 1) {
            return true;
        }
        return false;
    }
    document.addEventListener("DOMContentLoaded", function () {
        if (!isAppleMobile()) { return; }
        document.querySelectorAll(".directions-provider-toggle").forEach(function (el) {
            el.classList.remove("d-none");
        });
        document.querySelectorAll(".directions-provider-menu").forEach(function (el) {
            el.classList.remove("d-none");
        });
    });
})();
</script>

<?php
require_once SystemURLs::getDocumentRoot() . '/Include/Footer.php';
