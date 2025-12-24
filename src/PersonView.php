<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$timelineService = new TimelineService();
$mailchimp = new MailChimpService();
$personService = new PersonService();

// Get the person ID from the querystring
$iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');

$person = PersonQuery::create()->findPk($iPersonID);

if (empty($person)) {
    header('Location: ' . SystemURLs::getRootPath() . '/v2/person/not-found?id=' . $iPersonID);
    exit;
}

// GHSA-fcw7-mmfh-7vjm: Prevent IDOR - verify user has permission to view this person
$currentUser = AuthenticationManager::getCurrentUser();
if (!$currentUser->canEditPerson($iPersonID, $person->getFamId())) {
    RedirectUtils::securityRedirect('PersonView');
}

$sPageTitle = gettext('Person Profile');
require_once __DIR__ . '/Include/Header.php';
?>

<!-- Load Uppy Photo Uploader CSS & JS -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.js') ?>"></script>

<?php
$iRemoveVO = 0;
if (array_key_exists('RemoveVO', $_GET)) {
    $iRemoveVO = InputUtils::legacyFilterInput($_GET['RemoveVO'], 'int');
}

if (isset($_POST['VolunteerOpportunityAssign']) && AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    $volIDs = $_POST['VolunteerOpportunityIDs'];
    if ($volIDs) {
        foreach ($volIDs as $volID) {
            $personService->addVolunteerOpportunity((int)$iPersonID, (int)$volID);
        }
    }
}

// Service remove-volunteer-opportunity (these links set RemoveVO)
if ($iRemoveVO > 0 && AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    $personService->removeVolunteerOpportunity((int)$iPersonID, (int)$iRemoveVO);
}

// Get this person's data
$sSQL = "SELECT a.*, family_fam.*, COALESCE(cls.lst_OptionName , 'Unassigned') AS sClassName, fmr.lst_OptionName AS sFamRole, b.per_FirstName AS EnteredFirstName, b.per_ID AS EnteredId,
        b.Per_LastName AS EnteredLastName, c.per_FirstName AS EditedFirstName, c.per_LastName AS EditedLastName, c.per_ID AS EditedId
      FROM person_per a
      LEFT JOIN family_fam ON a.per_fam_ID = family_fam.fam_ID
      LEFT JOIN list_lst cls ON a.per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
      LEFT JOIN list_lst fmr ON a.per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
      LEFT JOIN person_per b ON a.per_EnteredBy = b.per_ID
      LEFT JOIN person_per c ON a.per_EditedBy = c.per_ID
      WHERE a.per_ID = " . $iPersonID;
$rsPerson = RunQuery($sSQL);
extract(mysqli_fetch_array($rsPerson));

$assignedProperties = $person->getProperties();

// Get the lists of custom person fields
$sSQL = 'SELECT person_custom_master.* FROM person_custom_master
  ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);

// Get the custom field data for this person.
$sSQL = 'SELECT * FROM person_custom WHERE per_ID = ' . $iPersonID;
$rsCustomData = RunQuery($sSQL);
$aCustomData = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH);

// Get the Groups this Person is assigned to
$sSQL = 'SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
FROM group_grp
LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
WHERE person2group2role_p2g2r.p2g2r_per_ID = ' . $iPersonID . '
ORDER BY grp_Name';
$rsAssignedGroups = RunQuery($sSQL);
$sAssignedGroups = ',';

// Get all the Groups
$sSQL = 'SELECT grp_ID, grp_Name FROM group_grp ORDER BY grp_Name';
$rsGroups = RunQuery($sSQL);

// Get the volunteer opportunities this Person is assigned to
$sSQL = 'SELECT vol_ID, vol_Name, vol_Description FROM volunteeropportunity_vol
LEFT JOIN person2volunteeropp_p2vo ON p2vo_vol_ID = vol_ID
WHERE person2volunteeropp_p2vo.p2vo_per_ID = ' . $iPersonID . ' ORDER by vol_Order';
$rsAssignedVolunteerOpps = RunQuery($sSQL);

// Get all the volunteer opportunities
$sSQL = 'SELECT vol_ID, vol_Name FROM volunteeropportunity_vol ORDER BY vol_Order';
$rsVolunteerOpps = RunQuery($sSQL);

// Get the Properties assigned to this Person
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
FROM record2property_r2p
LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
WHERE pro_Class = 'p' AND r2p_record_ID = " . $iPersonID .
    ' ORDER BY prt_Name, pro_Name';
$rsAssignedProperties = RunQuery($sSQL);

// Get all the properties
$sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'p' ORDER BY pro_Name";
$rsProperties = RunQuery($sSQL);

// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

$dBirthDate = $person->getFormattedBirthDate();

// Assign person data only - each person must enter their own information

//Get an unformatted mailing address to pass as a parameter to a google maps search
$Address1 = $per_Address1 ?? '';
$Address2 = $per_Address2 ?? '';
$sCity = $per_City ?? '';
$sState = $per_State ?? '';
$sZip = $per_Zip ?? '';
$sCountry = $per_Country ?? '';
$plaintextMailingAddress = $person->getAddress();

//Get a formatted mailing address to use as display to the user.
$Address1 = $per_Address1 ?? '';
$Address2 = $per_Address2 ?? '';
$sCity = $per_City ?? '';
$sState = $per_State ?? '';
$sZip = $per_Zip ?? '';
$sCountry = $per_Country ?? '';
$formattedMailingAddress = $person->getAddress();

$sPhoneCountry = $per_Country ?? '';
$sHomePhone = ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy);
$sHomePhoneUnformatted = ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy);

$sWorkPhone = ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $dummy);
$sWorkPhoneUnformatted = ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $dummy);

$sCellPhone = ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $dummy);
$sCellPhoneUnformatted = ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $dummy);

$sEmail = $per_Email ?? '';
$sUnformattedEmail = $per_Email ?? '';

if ($per_Envelope > 0) {
    $sEnvelope = $per_Envelope;
} else {
    $sEnvelope = gettext('Not assigned');
}

$iTableSpacerWidth = 10;

$bOkToEdit = (
    AuthenticationManager::getCurrentUser()->isEditRecordsEnabled() ||
    (AuthenticationManager::getCurrentUser()->isEditSelfEnabled() && $per_ID == AuthenticationManager::getCurrentUser()->getId()) ||
    (AuthenticationManager::getCurrentUser()->isEditSelfEnabled() && $per_fam_ID == AuthenticationManager::getCurrentUser()->getPerson()->getFamId())
);

?>
<div class="row">
    <div class="col-lg-3 col-md-3 col-sm-3">
        <div class="card card-primary">
            <div class="card-header with-border">
                <h3 class="card-title" style="font-size: 1.5rem; font-weight: 600;">
                    <?= $person->getFullName() ?>
                </h3>
                <div class="card-tools">
                    <span class="badge badge-secondary"><?= gettext('ID') ?>: <?= $person->getId() ?></span>
                </div>
            </div>
            <div class="card-body box-profile">
                <div class="image-container text-center">
                    <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" class="photo-profile mb-2">
                    <?php if ($bOkToEdit) : ?>
                    <div class="photo-actions">
                        <div class="btn-group" role="group">
                            <a id="view-larger-image-btn" href="#" class="btn btn-sm btn-primary hide-if-no-photo d-none" title="<?= gettext("View Photo") ?>">
                                <i class="fa-solid fa-search-plus"></i>
                            </a>
                            <a id="uploadImageButton" href="#" class="btn btn-sm btn-info" title="<?= gettext("Upload Photo") ?>">
                                <i class="fa-solid fa-camera"></i>
                            </a>
                            <?php if ($person->getPhoto()->hasUploadedPhoto()) : ?>
                            <a href="#" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#confirm-delete-image" title="<?= gettext("Delete Photo") ?>">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <ul class="list-group list-group-unbordered mb-3 mt-3">
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <?php $genderClass = "fa-question";
                                $genderText = gettext('Unknown');
                                if ($person->isMale()) {
                                    $genderClass = "fa-person";
                                    $genderText = gettext('Male');
                                } elseif ($person->isFemale()) {
                                    $genderClass = "fa-person-dress";
                                    $genderText = gettext('Female');
                                } ?>
                                <i class="fa <?= $genderClass ?> mr-2"></i>
                                <strong><?= gettext('Gender') ?>:</strong> <?= $genderText ?>
                            </span>
                        </div>
                    </li>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fa-solid fa-users mr-2"></i>
                                <strong><?= gettext('Family Role') ?>:</strong> <?= empty($sFamRole) ? gettext('Undefined') : gettext($sFamRole) ?>
                            </span>
                            <?php if ($bOkToEdit) : ?>
                            <button id="edit-role-btn" data-person_id="<?= $person->getId() ?>" data-family_role="<?= $person->getFamilyRoleName() ?>" data-family_role_id="<?= $person->getFmrId() ?>" class="btn btn-xs btn-primary" title="<?= gettext('Edit Role') ?>">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fa-solid fa-id-card mr-2"></i>
                                <strong><?= gettext('Classification') ?>:</strong> <?= gettext($sClassName) ?>
                            </span>
                        </div>
                        <?php if ($per_MembershipDate) : ?>
                        <small class="text-muted d-block mt-1">
                            <i class="fa-solid fa-calendar-check mr-1"></i><?= gettext('Since') ?>: <?= FormatDate($per_MembershipDate, false) ?>
                        </small>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
            <!-- /.box-body -->
        </div>
        <!-- /.box -->

        <!-- Contact & Personal Info -->
        <div class="card card-primary">
            <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Contact & Personal Info') ?></h3>
            </div>
            <div class="card-body">
                <!-- Family Section -->
                <?php if ($fam_ID != '' || !empty($formattedMailingAddress)) : ?>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="fa-solid fa-people-roof mr-1"></i><?= gettext('Family') ?></h6>
                    <ul class="list-unstyled ml-3">
                        <?php if ($fam_ID != '') : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-home mr-2 text-muted"></i>
                            <a href="<?= SystemURLs::getRootPath() ?>/v2/family/<?= $fam_ID ?>"><?= $fam_Name ?></a>
                            <?php if ($bOkToEdit) : ?>
                            <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $fam_ID ?>" class="ml-1" title="<?= gettext('Edit Family') ?>">
                                <i class="fa-solid fa-pen fa-xs"></i>
                            </a>
                            <?php endif; ?>
                        </li>
                        <?php else : ?>
                        <li class="mb-2 text-muted">
                            <i class="fa-solid fa-home mr-2"></i><?= gettext('No assigned family') ?>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($formattedMailingAddress)) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-map-marker-alt mr-2 text-muted"></i>
                            <a href="https://maps.google.com/?q=<?= $plaintextMailingAddress ?>" target="_blank">
                                <?= $formattedMailingAddress ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Personal Information -->
                <?php if ($dBirthDate || (!SystemConfig::getValue('bHideFriendDate') && $per_FriendDate != '')) : ?>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="fa-solid fa-user mr-1"></i><?= gettext('Personal') ?></h6>
                    <ul class="list-unstyled ml-3">
                        <?php if ($dBirthDate) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-birthday-cake mr-2 text-muted"></i>
                            <?= $dBirthDate ?>
                            <?php if (!$person->hideAge()) : ?>
                                <span class="text-muted">(<?= $person->getAge() ?>)</span>
                            <?php endif; ?>
                        </li>
                        <?php endif; ?>
                        <?php if (!SystemConfig::getValue('bHideFriendDate') && $per_FriendDate != '') : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-handshake mr-2 text-muted"></i>
                            <?= gettext('Friend Date') ?>: <?= FormatDate($per_FriendDate, false) ?>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Phone Numbers -->
                <?php if ($sCellPhone || $sHomePhone || $sWorkPhone) : ?>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="fa-solid fa-phone mr-1"></i><?= gettext('Phone') ?></h6>
                    <ul class="list-unstyled ml-3">
                        <?php if ($sCellPhone) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-mobile-screen mr-2 text-muted"></i>
                            <a href="tel:<?= $sCellPhoneUnformatted ?>"><?= $sCellPhone ?></a>
                            <small class="text-muted">(<?= gettext('Mobile') ?>)</small>
                        </li>
                        <?php endif; ?>
                        <?php if ($sHomePhone) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-house mr-2 text-muted"></i>
                            <a href="tel:<?= $sHomePhoneUnformatted ?>"><?= $sHomePhone ?></a>
                            <small class="text-muted">(<?= gettext('Home') ?>)</small>
                        </li>
                        <?php endif; ?>
                        <?php if ($sWorkPhone) : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-briefcase mr-2 text-muted"></i>
                            <a href="tel:<?= $sWorkPhoneUnformatted ?>"><?= $sWorkPhone ?></a>
                            <small class="text-muted">(<?= gettext('Work') ?>)</small>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Email -->
                <?php if ($sEmail != '' || $per_WorkEmail != '') : ?>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="fa-solid fa-envelope mr-1"></i><?= gettext('Email') ?></h6>
                    <ul class="list-unstyled ml-3">
                        <?php if ($sEmail != '') : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-at mr-2 text-muted"></i>
                            <a href="mailto:<?= $sUnformattedEmail ?>"><?= $sEmail ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($per_WorkEmail != '') : ?>
                        <li class="mb-2">
                            <i class="fa-solid fa-briefcase mr-2 text-muted"></i>
                            <a href="mailto:<?= $per_WorkEmail ?>"><?= $per_WorkEmail ?></a>
                            <small class="text-muted">(<?= gettext('Work') ?>)</small>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Social Media -->
                <?php if (strlen($per_Facebook) > 0 || strlen($per_Twitter) > 0 || strlen($per_LinkedIn) > 0) : ?>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="fa-solid fa-share-nodes mr-1"></i><?= gettext('Social Media') ?></h6>
                    <ul class="list-unstyled ml-3">
                        <?php if (strlen($per_Facebook) > 0) : ?>
                        <li class="mb-2">
                            <i class="fa-brands fa-facebook mr-2 text-primary"></i>
                            <a href="https://www.facebook.com/<?= InputUtils::sanitizeText($per_Facebook) ?>" target="_blank"><?= $per_Facebook ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (strlen($per_Twitter) > 0) : ?>
                        <li class="mb-2">
                            <i class="fa-brands fa-x-twitter mr-2 text-dark"></i>
                            <a href="https://www.twitter.com/<?= InputUtils::sanitizeText($per_Twitter) ?>" target="_blank">@<?= $per_Twitter ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (strlen($per_LinkedIn) > 0) : ?>
                        <li class="mb-2">
                            <i class="fa-brands fa-linkedin mr-2 text-info"></i>
                            <a href="https://www.linkedin.com/in/<?= InputUtils::sanitizeText($per_LinkedIn) ?>" target="_blank"><?= $per_LinkedIn ?></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Custom Fields -->
                <?php
                $hasCustomFields = false;
                $customFieldsHtml = '';
                while ($Row = mysqli_fetch_array($rsCustomFields)) {
                    extract($Row);
                    $currentData = trim($aCustomData[$custom_Field]);
                    if ($currentData != '') {
                        $hasCustomFields = true;
                        $displayIcon = "fa-solid fa-tag";
                        $displayLink = "";
                        if ($type_ID == 9) {
                            $displayIcon = "fa-solid fa-user";
                            $displayLink = SystemURLs::getRootPath() . '/PersonView.php?PersonID=' . $currentData;
                        } elseif ($type_ID == 11) {
                            $custom_Special = $sPhoneCountry;
                            $displayIcon = "fa-solid fa-phone";
                            // Sanitize phone number for tel: URI - allow only digits, +, -, (, ), and 'e' for extension
                            // Remove all other characters including spaces to prevent injection
                            $sanitizedPhone = preg_replace('/[^0-9+\-()e]/', '', $currentData);
                            $displayLink = "tel:" . $sanitizedPhone;
                        }
                        $customFieldsHtml .= '<li class="mb-2">';
                        $customFieldsHtml .= '<i class="' . $displayIcon . ' mr-2 text-muted"></i>';
                        $temp_string = nl2br(displayCustomField($type_ID, $currentData, $custom_Special));
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
                    <h6 class="text-muted mb-2"><i class="fa-solid fa-info-circle mr-1"></i><?= gettext('Additional Information') ?></h6>
                    <ul class="list-unstyled ml-3">
                        <?= $customFieldsHtml ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-9 col-md-9 col-sm-9">
        <div class="row">
            <a class="btn btn-app bg-info" id="printPerson" href="<?= SystemURLs::getRootPath() ?>/PrintView.php?PersonID=<?= $iPersonID ?>"><i class="fa-solid fa-print fa-3x"></i><br><?= gettext("Printable Page") ?></a>
            <button class="btn btn-app bg-success AddToCart" id="AddPersonToCart" data-cart-id="<?= $iPersonID ?>" data-cart-type="person"><i class="fa-solid fa-cart-plus fa-3x"></i><br><span class="cartActionDescription"><?= gettext("Add to Cart") ?></span></button>
            <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) {
            ?>
                <a class="btn btn-app bg-warning" id="editWhyCame" href="<?= SystemURLs::getRootPath() ?>/WhyCameEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa-solid fa-question-circle fa-3x"></i><br><?= gettext("Edit \"Why Came\" Notes") ?></a>
            <?php
            }
            if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
            ?>
                <a class="btn btn-app bg-info" id="addGroup"><i class="fa-solid fa-users fa-3x"></i><br><?= gettext("Assign New Group") ?></a>
            <?php
            } ?>
            <a class="btn btn-app bg-secondary" role="button" href="<?= SystemURLs::getRootPath() ?>/v2/people"><i class="fa-solid fa-list fa-3x"></i><br><?= gettext("List Members") ?></a>
            <?php
            if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) {
            ?>
                <a id="deletePersonBtn" class="btn btn-app bg-maroon delete-person" data-person_name="<?= $person->getFullName() ?>" data-person_id="<?= $iPersonID ?>"><i class="fa-solid fa-trash-can fa-3x"></i><br><?= gettext("Delete this Record") ?></a>
            <?php
            }
            ?>
            <br />
            <?php
            if (AuthenticationManager::getCurrentUser()->isAdmin()) {
                if (!$person->isUser()) {
            ?>
                    <a class="btn btn-app bg-purple" href="<?= SystemURLs::getRootPath() ?>/UserEditor.php?NewPersonID=<?= $iPersonID ?>"><i class="fa-solid fa-person-chalkboard fa-3x"></i><br><?= gettext('Make User') ?></a>
                <?php
                } else {
                ?>
                    <a class="btn btn-app bg-purple" href="<?= SystemURLs::getRootPath() ?>/UserEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa-solid fa-user-secret fa-3x"></i><br><?= gettext('Edit User') ?></a>
                    <a class="btn btn-app bg-info" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $iPersonID ?>"><i class="fa-solid fa-eye fa-3x"></i><br><?= gettext('View User') ?></a>
                    <a class="btn btn-app bg-warning" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $iPersonID ?>/changePassword"><i class="fa-solid fa-key fa-3x"></i><br><?= gettext("Change Password") ?></a>
                <?php
                }
            } elseif ($person->isUser() && $person->getId() == AuthenticationManager::getCurrentUser()->getId()) {
                ?>
                <a class="btn btn-app bg-info" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $iPersonID ?>"><i class="fa-solid fa-eye fa-3x"></i><br><?= gettext('View User') ?></a>
                <a class="btn btn-app bg-warning" href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword"><i class="fa-solid fa-key fa-3x"></i><br><?= gettext("Change Password") ?></a>
            <?php
            } ?>
        </div>

        <div class="card">
            <div class="card-header">
                <ul class="nav nav-pills card-header-pills">
                    <li class="nav-item">
                        <a class="nav-link active" id="nav-item-family" href="#family" data-toggle="tab">
                            <i class="fa-solid fa-people-roof mr-1"></i><?= gettext('Family') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-timeline" href="#timeline" data-toggle="tab">
                            <i class="fa-solid fa-clock mr-1"></i><?= gettext('Timeline') ?>
                        </a>
                    </li>
                    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-notes" href="#notes" data-toggle="tab">
                            <i class="fa-solid fa-sticky-note mr-1"></i><?= gettext('Notes') ?>
                        </a>
                    </li>
                    <?php } ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-groups" href="#groups" data-toggle="tab">
                            <i class="fa-solid fa-users mr-1"></i><?= gettext('Groups') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-volunteer" href="#volunteer" data-toggle="tab">
                            <i class="fa-solid fa-hands-helping mr-1"></i><?= gettext('Volunteer') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-properties" href="#properties" data-toggle="tab">
                            <i class="fa-solid fa-tags mr-1"></i><?= gettext('Properties') ?>
                        </a>
                    </li>
                    <?php if ($mailchimp->isActive()) { ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-mailchimp" href="#mailchimp" data-toggle="tab">
                            <i class="fa-brands fa-mailchimp mr-1"></i><?= gettext('Mailchimp') ?>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="active tab-pane" id="family">
                        <?php if ($person->getFamId() != '' && $person->getFamily() != null) { ?>
                            <table class="table user-list table-hover">
                                <thead>
                                    <tr>
                                        <th><span><?= gettext('Family Members') ?></span></th>
                                        <th class="text-center"><span><?= gettext('Role') ?></span></th>
                                        <th><span><?= gettext('Birthday') ?></span></th>
                                        <th><span><?= gettext('Email') ?></span></th>
                                        <th>&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($person->getFamily()->getPeopleSorted() as $familyMember) {
                                        $tmpPersonId = $familyMember->getId(); ?>
                                        <tr>
                                            <td>

                                                <img data-image-entity-type="person" data-image-entity-id="<?= $familyMember->getId() ?>" class="photo-tiny">
                                                <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $tmpPersonId ?>" class="user-link"><?= $familyMember->getFullName() ?> </a>

                                            </td>
                                            <td class="text-center">
                                                <?= $familyMember->getFamilyRoleName() ?>
                                            </td>
                                            <td>
                                                <?= $familyMember->getFormattedBirthDate(); ?>
                                            </td>
                                            <td>
                                                <?php $tmpEmail = $familyMember->getEmail();
                                                if ($tmpEmail != '') { ?>
                                                    <a href="mailto:<?= $tmpEmail ?>"><?= $tmpEmail ?></a>
                                                <?php } ?>
                                            </td>
                                            <td class="text-right">
                                                <div class="btn-group" role="group">
                                                    <button class="AddToCart btn btn-sm btn-success" data-cart-id="<?= $tmpPersonId ?>" data-cart-type="person" title="<?= gettext('Add to Cart') ?>">
                                                        <i class="fa-solid fa-cart-plus"></i>
                                                    </button>
                                                    <?php if ($bOkToEdit) {
                                                    ?>
                                                        <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $tmpPersonId ?>" class="btn btn-sm btn-primary" title="<?= gettext('Edit') ?>">
                                                            <i class="fa-solid fa-pen"></i>
                                                        </a>
                                                        <a class="delete-person btn btn-sm btn-danger" data-person_name="<?= $familyMember->getFullName() ?>" data-person_id="<?= $familyMember->getId() ?>" data-view="family" title="<?= gettext('Delete') ?>">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </a>
                                                    <?php
                                                    } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
                                    } ?>
                                </tbody>
                            </table>
                        <?php
                        } ?>
                    </div>

                    <div class="tab-pane" id="timeline">
                        <ul class="timeline timeline-inverse">
                            <!-- timeline time label -->
                            <div class="time-label">
                                <span class="bg-green">
                                    <?php $now = new DateTime('');
                                    echo $now->format('Y') ?>
                                </span>
                            </div>
                            <!-- /.timeline-label -->

                            <!-- timeline item -->
                            <?php foreach ($timelineService->getForPerson($iPersonID) as $item) {
                            ?>
                                <div>
                                    <!-- timeline icon -->
                                    <i class="fa <?= $item['style'] ?>"></i>

                                    <div class="timeline-item">
                                        <span class="time">
                                            <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled() && (isset($item["editLink"]) || isset($item["deleteLink"]))) {
                                            ?>
                                                <?php if (isset($item["editLink"])) {
                                                ?>
                                                    <a href="<?= $item["editLink"] ?>"><button type="button" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen"></i></button></a>
                                                <?php
                                                }
                                                if (isset($item["deleteLink"])) {
                                                ?>
                                                    <a href="<?= $item["deleteLink"] ?>"><button type="button" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button></a>
                                                <?php
                                                } ?>
                                                &nbsp;
                                            <?php
                                            } ?>
                                            <i class="fa-solid fa-clock"></i> <?= $item['datetime'] ?></span>

                                        <?php if ($item['slim']) {
                                        ?>
                                            <h4 class="timeline-header">
                                                <?= $item['text'] ?> <?= gettext($item['header']) ?>
                                            </h4>
                                        <?php
                                        } else {
                                        ?>
                                            <h3 class="timeline-header">
                                                <?php if (in_array('headerlink', $item)) {
                                                ?>
                                                    <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                                                <?php
                                                } else {
                                                ?>
                                                    <?= $item['header'] ?>
                                                <?php
                                                } ?>
                                            </h3>

                                            <div class="timeline-body">
                                                <pre style="line-height: 1.2;"><?= $item['text'] ?></pre>
                                            </div>

                                        <?php
                                        } ?>
                                    </div>
                                </div>
                            <?php
                            } ?>
                            <!-- END timeline item -->
                        </ul>
                    </div>

                    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
                        <div class="tab-pane" id="notes">
                            <div class="card">
                                <div class="card-body">
                                    <?php
                                    $personNotes = $timelineService->getNotesForPerson($iPersonID);
                                    if (empty($personNotes)) {
                                    ?>
                                        <div class="alert alert-info">
                                            <i class="fa-solid fa-info-circle fa-fw fa-lg"></i>
                                            <span><?= gettext('No notes have been added for this person.') ?></span>
                                        </div>
                                    <?php
                                    } else {
                                    ?>
                                        <table class="table table-hover table-striped" id="notes-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 1%; white-space: nowrap;"><?= gettext('Date') ?></th>
                                                    <th><?= gettext('Note') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($personNotes as $note) { ?>
                                                    <tr>
                                                        <td style="width: 1%; white-space: nowrap; vertical-align: top;">
                                                            <div style="text-align: center;">
                                                                <i class="fa-solid fa-calendar"></i><br>
                                                                <?= date('Y-m-d', strtotime($note['datetime'])) ?><br>
                                                                <small class="text-muted"><?= date('h:i A', strtotime($note['datetime'])) ?></small>
                                                                <div style="margin-top: 10px;">
                                                                    <?php if (isset($note['editLink']) && $note['editLink']) { ?>
                                                                        <a href="<?= $note['editLink'] ?>" class="btn btn-sm btn-primary" title="<?= gettext('Edit') ?>">
                                                                            <i class="fa-solid fa-pen"></i>
                                                                        </a>
                                                                    <?php }
                                                                    if (isset($note['deleteLink']) && $note['deleteLink']) { ?>
                                                                        <a href="<?= $note['deleteLink'] ?>" class="btn btn-sm btn-danger" title="<?= gettext('Delete') ?>">
                                                                            <i class="fa-solid fa-trash"></i>
                                                                        </a>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td style="width: 99%; vertical-align: top;">
                                                            <div style="margin-bottom: 8px;">
                                                                <?= $note['text'] ?>
                                                            </div>
                                                            <small class="text-muted"><i class="fa-solid fa-user"></i> <?= $note['header'] ?></small>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    <?php
                                    } ?>
                                </div>
                                <!-- /.main-box-body -->
                            </div>
                            <!-- /.main-box -->
                        </div>
                    <?php } ?>

                    <div class="tab-pane" id="groups">
                        <div class="card">
                            <div class="card-body">
                                <?php
                                //Was anything returned?
                                if (mysqli_num_rows($rsAssignedGroups) === 0) {
                                ?>
                                    <br>
                                    <div class="alert alert-warning">
                                        <i class="fa-solid fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No group assignments.') ?></span>
                                    </div>
                                    <?php
                                } else {
                                    echo '<div class="row">';
                                    // Loop through the rows
                                    while ($aRow = mysqli_fetch_array($rsAssignedGroups)) {
                                        extract($aRow); ?>
                                        <div class="col-md-4">
                                            <p><br /></p>
                                            <!-- Info box -->
                                            <div class="card card-info">
                                                <div class="card-header">
                                                    <h3 class="card-title"><a href="<?= SystemURLs::getRootPath() ?>/GroupView.php?GroupID=<?= $grp_ID ?>"><?= $grp_Name ?></a></h3>

                                                    <div class="card-tools pull-right">
                                                        <div class="label bg-gray"><?= InputUtils::escapeHTML(gettext($roleName)) ?></div>
                                                    </div>
                                                </div>
                                                <?php
                                                // If this group has associated special properties, display those with values and prop_PersonDisplay flag set.
                                                if ($grp_hasSpecialProps) {
                                                    // Get the special properties for this group
                                                    $sSQL = 'SELECT groupprop_master.* FROM groupprop_master WHERE grp_ID = ' . $grp_ID . " AND prop_PersonDisplay = 'true' ORDER BY prop_ID";
                                                    $rsPropList = RunQuery($sSQL);

                                                    $sSQL = 'SELECT * FROM groupprop_' . $grp_ID . ' WHERE per_ID = ' . $iPersonID;
                                                    $rsPersonProps = RunQuery($sSQL);
                                                    $aPersonProps = mysqli_fetch_array($rsPersonProps, MYSQLI_BOTH);

                                                    echo '<div class="card-body">';

                                                    while ($aProps = mysqli_fetch_array($rsPropList)) {
                                                        extract($aProps);
                                                        $currentData = trim($aPersonProps[$prop_Field]);
                                                        if (strlen($currentData) > 0) {
                                                            $sRowClass = AlternateRowStyle($sRowClass);
                                                            if ($type_ID == 11) {
                                                                $prop_Special = $sPhoneCountry;
                                                            }
                                                            echo '<strong>' . $prop_Name . '</strong>: ' . displayCustomField($type_ID, $currentData, $prop_Special) . '<br/>';
                                                        }
                                                    }

                                                    echo '</div><!-- /.box-body -->';
                                                } ?>
                                                <div class="card-footer">
                                                    <code>
                                                        <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                                                        ?>
                                                            <a href="<?= SystemURLs::getRootPath() ?>/GroupView.php?GroupID=<?= $grp_ID ?>" class="btn btn-secondary" role="button"><i class="fa-solid fa-list"></i></a>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-secondary"><?= gettext('Action') ?></button>
                                                                <button type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown">
                                                                    <span class="caret"></span>
                                                                    <span class="sr-only">Toggle Dropdown</span>
                                                                </button>
                                                                <ul class="dropdown-menu" role="menu">
                                                                    <li><a class="changeRole" data-groupid="<?= $grp_ID ?>"><?= gettext('Change Role') ?></a></li>
                                                                    <?php if ($grp_hasSpecialProps) {
                                                                    ?>
                                                                        <li><a href="<?= SystemURLs::getRootPath() ?>/GroupPropsEditor.php?GroupID=<?= $grp_ID ?>&PersonID=<?= $iPersonID ?>"><?= gettext('Update Properties') ?></a></li>
                                                                    <?php
                                                                    } ?>
                                                                </ul>
                                                            </div>
                                                            <div class="btn-group">
                                                                <button data-groupid="<?= $grp_ID ?>" data-groupname="<?= $grp_Name ?>" type="button" class="btn btn-danger groupRemove" data-toggle="dropdown"><i class="fa-solid fa-trash-can"></i></button>
                                                            </div>
                                                        <?php
                                                        } ?>
                                                    </code>
                                                </div>
                                                <!-- /.box-footer-->
                                            </div>
                                            <!-- /.box -->
                                        </div>
                                <?php
                                        // NOTE: this method is crude.  Need to replace this with use of an array.
                                        $sAssignedGroups .= $grp_ID . ',';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="properties">
                        <?php
                        $sAssignedProperties = ','; ?>
                        <?php if (mysqli_num_rows($rsAssignedProperties) === 0) : ?>
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No property assignments.') ?></span>
                            </div>
                        <?php else : ?>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?= gettext('Type') ?></th>
                                        <th><?= gettext('Property') ?></th>
                                        <th><?= gettext('Value') ?></th>
                                        <?php if ($bOkToEdit) : ?>
                                            <th class="text-right"><?= gettext('Actions') ?></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    while ($aRow = mysqli_fetch_array($rsAssignedProperties)) {
                                        $pro_Prompt = '';
                                        $r2p_Value = '';
                                        extract($aRow); ?>
                                        <tr>
                                            <td><span class="badge badge-info"><?= $prt_Name ?></span></td>
                                            <td><strong><?= $pro_Name ?></strong></td>
                                            <td><?= InputUtils::escapeHTML($r2p_Value) ?></td>
                                            <?php if ($bOkToEdit) { ?>
                                                <td class="text-right">
                                                    <button class="btn btn-sm btn-danger remove-property-btn" data-property_id="<?= $pro_ID ?>" title="<?= gettext('Remove Property') ?>">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </td>
                                            <?php } ?>
                                        </tr>
                                    <?php
                                        $sAssignedProperties .= $pro_ID . ',';
                                    } ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if ($bOkToEdit && mysqli_num_rows($rsProperties) !== 0) : ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fa-solid fa-plus-circle mr-2"></i><?= gettext('Assign a New Property') ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="<?= SystemURLs::getRootPath() . '/api/properties/persons/assign' ?>" id="assign-property-form">
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label for="input-person-properties"><?= gettext('Select Property') ?></label>
                                                <select name="PropertyId" id="input-person-properties" class="form-control select2" data-placeholder="<?= gettext('Choose a property...') ?>">
                                                    <option value=""></option>
                                                            <?php
                                                            $assignedPropertiesArray = [];
                                                            foreach ($assignedProperties as $assignedProperty) {
                                                                $assignedPropertiesArray[] = $assignedProperty->getPropertyId();
                                                            }
                                                            while ($aRow = mysqli_fetch_array($rsProperties)) {
                                                                extract($aRow);
                                                                $attributes = "value=\"{$pro_ID}\" ";
                                                                if (!empty($pro_Prompt)) {
                                                                    $pro_Value = '';
                                                                    foreach ($assignedProperties as $assignedProperty) {
                                                                        if ($assignedProperty->getPropertyId() == $pro_ID) {
                                                                            $pro_Value = $assignedProperty->getPropertyValue();
                                                                        }
                                                                    }
                                                                    $attributes .= "data-pro_Prompt=\"{$pro_Prompt}\" data-pro_Value=\"{$pro_Value}\" ";
                                                                }

                                                                $optionText = $pro_Name;
                                                                if (in_array($pro_ID, $assignedPropertiesArray)) {
                                                                    $optionText = $pro_Name . ' (' . gettext('assigned') . ')';
                                                                }
                                                                echo "<option {$attributes}>{$optionText}</option>";
                                                            } ?>
                                                </select>
                                            </div>
                                            <div id="prompt-box" class="col-md-6">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <button id="assign-property-btn" type="button" class="btn btn-primary">
                                                    <i class="fa-solid fa-check mr-1"></i><?= gettext('Assign Property') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane" id="volunteer">
                        <?php
                        $sAssignedVolunteerOpps = ',';
                        if (mysqli_num_rows($rsAssignedVolunteerOpps) === 0) {
                        ?>
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No volunteer opportunity assignments.') ?></span>
                            </div>
                        <?php
                        } else {
                            echo '<table class="table table-hover">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>' . gettext('Name') . '</th>';
                            echo '<th>' . gettext('Description') . '</th>';
                            if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
                                echo '<th class="text-right">' . gettext('Actions') . '</th>';
                            }
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';

                            while ($aRow = mysqli_fetch_array($rsAssignedVolunteerOpps)) {
                                extract($aRow);
                                echo '<tr>';
                                echo '<td><strong>' . InputUtils::escapeHTML($vol_Name) . '</strong></td>';
                                echo '<td>' . InputUtils::escapeHTML($vol_Description) . '</td>';

                                if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
                                    echo '<td class="text-right">';
                                    echo '<a class="btn btn-sm btn-danger" href="' . SystemURLs::getRootPath() . '/PersonView.php?PersonID=' . $per_ID . '&RemoveVO=' . $vol_ID . '" title="' . gettext('Remove') . '">';
                                    echo '<i class="fa-solid fa-trash"></i>';
                                    echo '</a>';
                                    echo '</td>';
                                }

                                echo '</tr>';
                                $sAssignedVolunteerOpps .= $vol_ID . ',';
                            }
                            echo '</tbody>';
                            echo '</table>';
                        } ?>

                        <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled() && $rsVolunteerOpps->num_rows) : ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fa-solid fa-plus-circle mr-2"></i><?= gettext('Assign a New Volunteer Opportunity') ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="PersonView.php?PersonID=<?= $iPersonID ?>">
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label for="input-volunteer-opportunities"><?= gettext('Select Opportunities') ?></label>
                                                <select id="input-volunteer-opportunities" name="VolunteerOpportunityIDs[]" multiple class="form-control select2" data-placeholder="<?= gettext('Choose opportunities...') ?>">
                                                    <?php
                                                    while ($aRow = mysqli_fetch_array($rsVolunteerOpps)) {
                                                        extract($aRow);
                                                        if (strlen(strstr($sAssignedVolunteerOpps, ',' . $vol_ID . ',')) === 0) {
                                                            echo '<option value="' . InputUtils::escapeAttribute($vol_ID) . '">' . InputUtils::escapeHTML($vol_Name) . '</option>';
                                                        }
                                                    } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <button type="submit" name="VolunteerOpportunityAssign" class="btn btn-primary">
                                                    <i class="fa-solid fa-check mr-1"></i><?= gettext('Assign Opportunities') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane" id="mailchimp">
                        <table class="table">
                            <tr>
                                <th><?= gettext("Type") ?></th>
                                <th><?= gettext("Email") ?></th>
                                <th><?= gettext("Lists") ?></th>
                            </tr>
                            <tr>
                                <td>Home</td>
                                <td><?= $person->getEmail() ?></td>
                                <td id="<?= md5($person->getEmail()) ?>"> ... <?= gettext("loading") ?> ... </td>
                            </tr>
                            <?php if (!empty($person->getWorkEmail())) { ?>
                                <tr>
                                    <td>Work</td>
                                    <td><?= $person->getWorkEmail() ?></td>
                                    <td id="<?= md5($person->getWorkEmail()) ?>"> ... <?= gettext("loading") ?> ... </td>
                                </tr>
                            <?php } ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal -->
        <div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog" aria-labelledby="delete-Image-label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="delete-Image-label"><?= gettext('Confirm Delete') ?></h4>
                    </div>

                    <div class="modal-body">
                        <p><?= gettext('You are about to delete the profile photo, this procedure is irreversible.') ?></p>

                        <p><?= gettext('Do you want to proceed?') ?></p>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= gettext("Cancel") ?></button>
                        <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>
                    </div>
                </div>
            </div>
        </div>
        <script src="<?= SystemURLs::assetVersioned('/skin/js/MemberView.js') ?>"></script>
        <script src="<?= SystemURLs::assetVersioned('/skin/js/PersonView.js') ?>"></script>
        <script nonce="<?= SystemURLs::getCSPNonce() ?>">
            window.CRM.currentPersonID = <?= $iPersonID ?>;
            window.CRM.plugin.mailchimp = <?= $mailchimp->isActive() ? "true" : "false" ?>;

            $("#deletePhoto").click(function() {
                window.CRM.deletePhoto("person", window.CRM.currentPersonID);
            });

            $(document).ready(function() {
                $("#input-volunteer-opportunities").select2();
                $("#input-person-properties").select2();

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

                // Initialize Uppy photo uploader
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

                    $("#uploadImageButton").click(function(e) {
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

            });
        </script>

<!-- Person View Floating Action Buttons -->
<div class="fab-container fab-person-view" id="fab-person-view">
    <?php if ($bOkToEdit) { ?>
    <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $iPersonID ?>" class="fab-button fab-edit" title="<?= gettext('Edit Person') ?>">
        <span class="fab-label"><?= gettext('Edit Person') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-pen"></i>
        </div>
    </a>
    <?php } ?>
    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?PersonID=<?= $iPersonID ?>" class="fab-button fab-note" title="<?= gettext('Add New') . ' ' . gettext('Note') ?>">
        <span class="fab-label"><?= gettext('Add New') . ' ' . gettext('Note') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-sticky-note"></i>
        </div>
    </a>
    <?php } ?>
</div>

<?php
require_once __DIR__ . '/Include/Footer.php';
