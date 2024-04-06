<?php

/*******************************************************************************
 *
 *  filename    : PersonView.php
 *  last change : 2003-04-14
 *  description : Displays all the information about a single person
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Utils\InputUtils;

;

$timelineService = new TimelineService();
$mailchimp = new MailChimpService();

// Get the person ID from the querystring
$iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');

$person = PersonQuery::create()->findPk($iPersonID);

if (empty($person)) {
    header('Location: ' . SystemURLs::getRootPath() . '/v2/person/not-found?id=' . $iPersonID);
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext('Person Profile');
require 'Include/Header.php';


$iRemoveVO = 0;
if (array_key_exists('RemoveVO', $_GET)) {
    $iRemoveVO = InputUtils::legacyFilterInput($_GET['RemoveVO'], 'int');
}

if (isset($_POST['VolunteerOpportunityAssign']) && AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    $volIDs = $_POST['VolunteerOpportunityIDs'];
    if ($volIDs) {
        foreach ($volIDs as $volID) {
            AddVolunteerOpportunity($iPersonID, $volID);
        }
    }
}

// Service remove-volunteer-opportunity (these links set RemoveVO)
if ($iRemoveVO > 0 && AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    RemoveVolunteerOpportunity($iPersonID, $iRemoveVO);
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

$sFamilyInfoBegin = '<span style="color: red;">';
$sFamilyInfoEnd = '</span>';

// Assign the values locally, after selecting whether to display the family or person information

//Get an unformatted mailing address to pass as a parameter to a google maps search
SelectWhichAddress($Address1, $Address2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, false);
$sCity = SelectWhichInfo($per_City, $fam_City, false);
$sState = SelectWhichInfo($per_State, $fam_State, false);
$sZip = SelectWhichInfo($per_Zip, $fam_Zip, false);
$sCountry = SelectWhichInfo($per_Country, $fam_Country, false);
$plaintextMailingAddress = $person->getAddress();

//Get a formatted mailing address to use as display to the user.
SelectWhichAddress($Address1, $Address2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, true);
$sCity = SelectWhichInfo($per_City, $fam_City, true);
$sState = SelectWhichInfo($per_State, $fam_State, true);
$sZip = SelectWhichInfo($per_Zip, $fam_Zip, true);
$sCountry = SelectWhichInfo($per_Country, $fam_Country, true);
$formattedMailingAddress = $person->getAddress();

$sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, false);
$sHomePhone = SelectWhichInfo(
    ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy),
    ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy),
    true
);
$sHomePhoneUnformatted = SelectWhichInfo(
    ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy),
    ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy),
    false
);
$sWorkPhone = SelectWhichInfo(
    ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $dummy),
    ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy),
    true
);
$sWorkPhoneUnformatted = SelectWhichInfo(
    ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $dummy),
    ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy),
    false
);
$sCellPhone = SelectWhichInfo(
    ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $dummy),
    ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy),
    true
);
$sCellPhoneUnformatted = SelectWhichInfo(
    ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $dummy),
    ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy),
    false
);
$sEmail = SelectWhichInfo($per_Email, $fam_Email, true);
$sUnformattedEmail = SelectWhichInfo($per_Email, $fam_Email, false);

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
            <div class="card-body box-profile">
                <div class="image-container">
                    <div class="text-center">
                        <img src="<?= SystemURLs::getRootPath() . '/api/person/' . $person->getId() . '/photo' ?>" class="initials-image profile-user-img img-fluid img-circle">
                        <p />

                        <?php if ($bOkToEdit) : ?>
                            <div class="buttons">
                                <a id="view-larger-image-btn" class="hide" title="<?= gettext("View Photo") ?>">
                                    <i class="fa fa-search-plus"></i>
                                </a>&nbsp;
                                <a class="" data-toggle="modal" data-target="#upload-image" title="<?= gettext("Upload Photo") ?>">
                                    <i class="fa fa-camera"></i>
                                </a>&nbsp;
                                <a data-toggle="modal" data-target="#confirm-delete-image" title="<?= gettext("Delete Photo") ?>">
                                    <i class="fa fa-trash-can"></i>
                                </a>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>
                <h3 class="profile-username text-center">
                    <?= $person->getFullName() ?> [<?= $person->getId() ?>]
                </h3>
                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <?php $genderClass = "fa-question";
                        if ($person->isMale()) {
                            $genderClass =  "fa-male";
                        } elseif ($person->isFemale()) {
                            $genderClass =  "fa-female";
                        } ?>
                        <b><?= gettext('Gender') ?></b> <a class="float-right"><i class="fa <?= $genderClass ?>"></i></a>
                    </li>
                    <li class="list-group-item">
                        <b><?= gettext('Family Role') ?></b> <a class="float-right"><?= empty($sFamRole) ? gettext('Undefined') : gettext($sFamRole); ?></a>
                        <a id="edit-role-btn" data-person_id="<?= $person->getId() ?>" data-family_role="<?= $person->getFamilyRoleName() ?>" data-family_role_id="<?= $person->getFmrId() ?>" class="btn btn-xs">
                            <i class="fas fa-pen"></i>
                        </a>
                    </li>
                    <li class="list-group-item">
                        <b><?= gettext($sClassName) ?></b>
                        <a class="float-right">
                            <?php if ($per_MembershipDate) {
                                echo gettext(' Since:')  . ' ' . FormatDate($per_MembershipDate, false);
                            } ?>
                        </a>
                    </li>

                    <?php if ($bOkToEdit) { ?>
                        <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $per_ID ?>" class="btn btn-primary btn-block" id="EditPerson"><b><?php echo gettext('Edit'); ?></b></a>
                    <?php } ?>

                </ul>
            </div>
            <!-- /.box-body -->
        </div>
        <!-- /.box -->

        <!-- About Me Box -->
        <div class="card card-primary">
            <div class="card-header with-border">
                <h3 class="card-title text-center"><?php echo gettext('About Me'); ?></h3>
            </div>
            <!-- /.box-header -->
            <div class="card-body">
                <ul class="fa-ul">
                    <li><i class="fa-li fa fa-people-roof"></i><?php echo gettext('Family:'); ?> <span>
                            <?php
                            if ($fam_ID != '') {
                                ?>
                                <a href="<?= SystemURLs::getRootPath() ?>/v2/family/<?= $fam_ID ?>"><?= $fam_Name ?> </a>
                                <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $fam_ID ?>" class="table-link">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <?php
                            } else {
                                echo gettext('(No assigned family)');
                            } ?>
                        </span></li>
                    <?php if (!empty($formattedMailingAddress)) {
                        ?>
                        <li><i class="fa-li fa fa-home"></i><?php echo gettext('Address'); ?>: <span>
                                <a href="https://maps.google.com/?q=<?= $plaintextMailingAddress ?>" target="_blank">
                                    <?= $formattedMailingAddress ?>
                                </a>
                            </span></li>
                        <?php
                    }
                    if ($dBirthDate) {
                        ?>
                        <li>
                            <i class="fa-li fa fa-calendar"></i><?= gettext('Birth Date') ?>: <?= $dBirthDate ?>
                            <?php if (!$person->hideAge()) {
                                ?>
                                (<span></span><?= $person->getAge() ?>)
                                <?php
                            } ?>
                        </li>
                        <?php
                    }
                    if (!SystemConfig::getValue('bHideFriendDate') && $per_FriendDate != '') { /* Friend Date can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa fa-tasks"></i><?= gettext('Friend Date') ?>: <span><?= FormatDate($per_FriendDate, false) ?></span></li>
                        <?php
                    }
                    if ($sCellPhone) {
                        ?>
                        <li><i class="fa-li fa fa-mobile-phone"></i><?= gettext('Mobile Phone') ?>: <span><a href="tel:<?= $sCellPhoneUnformatted ?>"><?= $sCellPhone ?></a></span></li>
                        <?php
                    }
                    if ($sHomePhone) {
                        ?>
                        <li><i class="fa-li fa fa-phone"></i><?= gettext('Home Phone') ?>: <span><a href="tel:<?= $sHomePhoneUnformatted ?>"><?= $sHomePhone ?></a></span></li>
                        <?php
                    }
                    if ($sEmail != '') {
                        ?>
                        <li><i class="fa-li fa fa-envelope"></i><?= gettext('Email') ?>: <span><a href="mailto:<?= $sUnformattedEmail ?>"><?= $sEmail ?></a></span></li>
                        <?php
                    }
                    if ($sWorkPhone) {
                        ?>
                        <li><i class="fa-li fa fa-phone"></i><?= gettext('Work Phone') ?>: <span><a href="tel:<?= $sWorkPhoneUnformatted ?>"><?= $sWorkPhone ?></a></span></li>
                        <?php
                    } ?>
                    <?php if ($per_WorkEmail != '') {
                        ?>
                        <li><i class="fa-li fa fa-envelope"></i><?= gettext('Work/Other Email') ?>: <span><a href="mailto:<?= $per_WorkEmail ?>"><?= $per_WorkEmail ?></a></span></li>
                        <?php
                    }

                    if (strlen($per_Facebook) > 0) {
                        ?>
                        <li><i class="fa-li fa-brands fa-facebook-official"></i>Facebook: <span><a href="https://www.facebook.com/<?= InputUtils::filterString($per_Facebook) ?> " target="_blank"><?= $per_Facebook ?></a></span></li>
                        <?php
                    }

                    if (strlen($per_Twitter) > 0) {
                        ?>
                        <li><i class="fa-li fa-brands fa-x-twitter"></i>X: <span><a href="https://www.twitter.com/<?= InputUtils::filterString($per_Twitter) ?>" target="_blank"><?= $per_Twitter ?></a></span></li>
                        <?php
                    }

                    if (strlen($per_LinkedIn) > 0) {
                        ?>
                        <li><i class="fa-li fa-brands fa-linkedin"></i>LinkedIn: <span><a href="https://www.linkedin.com/in/<?= InputUtils::FiltersTring($per_LinkedIn) ?>" target="_blank"><?= $per_LinkedIn ?></a></span></li>
                        <?php
                    }

                    // Display the side custom fields
                    while ($Row = mysqli_fetch_array($rsCustomFields)) {
                        extract($Row);
                        $currentData = trim($aCustomData[$custom_Field]);
                        $displayIcon = "fa fa-tag";
                        $displayLink = "";
                        if ($currentData != '') {
                            if ($type_ID == 9) {
                                $displayIcon = "fa fa-user";
                                $displayLink = SystemURLs::getRootPath() . '/PersonView.php?PersonID=' . $currentData;
                            } elseif ($type_ID == 11) {
                                $custom_Special = $sPhoneCountry;
                                $displayIcon = "fa-phone";
                                $displayLink = "tel:" . $temp_string;
                            }
                            echo '<li><i class="fa-li ' . $displayIcon . '"></i>' . $custom_Name . ': <span>';
                            $temp_string = nl2br((displayCustomField($type_ID, $currentData, $custom_Special)));
                            if ($displayLink) {
                                echo "<a href=\"" . $displayLink . "\">" . $temp_string . "</a>";
                            } else {
                                echo $temp_string;
                            }
                            echo '</span></li>';
                        }
                    } ?>
                </ul>
            </div>
        </div>
        <div class="alert alert-info alert-dismissable">
            <i class="fa fa-fw fa-tree"></i> <?php echo gettext('indicates items inherited from the associated family record.'); ?>
        </div>
    </div>
    <div class="col-lg-9 col-md-9 col-sm-9">
        <div class="row">
            <a class="btn btn-app" id="printPerson" href="<?= SystemURLs::getRootPath() ?>/PrintView.php?PersonID=<?= $iPersonID ?>"><i class="fa fa-print"></i> <?= gettext("Printable Page") ?></a>
            <a class="btn btn-app AddToPeopleCart" id="AddPersonToCart" data-cartpersonid="<?= $iPersonID ?>"><i class="fa fa-cart-plus"></i><span class="cartActionDescription"><?= gettext("Add to Cart") ?></span></a>
            <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) {
                ?>
                <a class="btn btn-app" id="editWhyCame" href="<?= SystemURLs::getRootPath() ?>/WhyCameEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa fa-question-circle"></i> <?= gettext("Edit \"Why Came\" Notes") ?></a>
                <a class="btn btn-app" id="addNote" href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa fa-sticky-note"></i> <?= gettext("Add a Note") ?></a>
                <?php
            }
            if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                ?>
                <a class="btn btn-app" id="addGroup"><i class="fa fa-users"></i> <?= gettext("Assign New Group") ?></a>
                <?php
            } ?>
            <a class="btn btn-app" role="button" href="<?= SystemURLs::getRootPath() ?>/v2/people"><i class="fa fa-list"></i> <?= gettext("List Members") ?></span></a>
            <?php
            if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) {
                ?>
                <a id="deletePersonBtn" class="btn btn-app bg-maroon delete-person" data-person_name="<?= $person->getFullName() ?>" data-person_id="<?= $iPersonID ?>"><i class="fa fa-trash-can"></i> <?= gettext("Delete this Record") ?></a>
                <?php
            }
            ?>
            <br />
            <?php
            if (AuthenticationManager::getCurrentUser()->isAdmin()) {
                if (!$person->isUser()) {
                    ?>
                    <a class="btn btn-app" href="<?= SystemURLs::getRootPath() ?>/UserEditor.php?NewPersonID=<?= $iPersonID ?>"><i class="fa fa-person-chalkboard"></i> <?= gettext('Make User') ?></a>
                    <?php
                } else {
                    ?>
                    <a class="btn btn-app" href="<?= SystemURLs::getRootPath() ?>/UserEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa fa-user-secret"></i> <?= gettext('Edit User') ?></a>
                    <a class="btn btn-app" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $iPersonID ?>"><i class="fa fa-eye"></i> <?= gettext('View User') ?></a>
                    <a class="btn btn-app" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $iPersonID ?>/changePassword"><i class="fa fa-key"></i> <?= gettext("Change Password") ?></a>
                    <?php
                }
            } elseif ($person->isUser() && $person->getId() == AuthenticationManager::getCurrentUser()->getId()) {
                ?>
                <a class="btn btn-app" href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $iPersonID ?>"><i class="fa fa-eye"></i> <?= gettext('View User') ?></a>
                <a class="btn btn-app" href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword"><i class="fa fa-key"></i> <?= gettext("Change Password") ?></a>
                <?php
            } ?>
        </div>

        <div class="card">
            <div class="card-header p-2">
                <ul class="nav nav-pills">
                    <li class="nav-item"><a class="nav-link active" id="nav-item-family" href="#family" data-toggle="tab"><?= gettext('Family') ?></a></li>
                    <li class="nav-item"><a class="nav-link" id="nav-item-timeline" href="#timeline" data-toggle="tab"><?= gettext('Timeline') ?></a></li>
                    <li class="nav-item"><a class="nav-link" id="nav-item-groups" href="#groups" data-toggle="tab"><?= gettext('Assigned Groups') ?></a></li>
                    <li class="nav-item"><a class="nav-link" id="nav-item-volunteer" href="#volunteer" data-toggle="tab"><?= gettext('Volunteer Opportunities') ?></a></li>
                    <li class="nav-item"><a class="nav-link" id="nav-item-properties" href="#properties" data-toggle="tab"><?= gettext('Assigned Properties') ?></a></li>
                    <?php if ($mailchimp->isActive()) { ?>
                        <li class="nav-item"><a class="nav-link" id="nav-item-mailchimp" href="#mailchimp" data-toggle="tab"><?= gettext('Mailchimp') ?></a></li>
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

                                                <img style="width:40px; height:40px;display:inline-block" src="<?= $sRootPath . '/api/person/' . $familyMember->getId() . '/thumbnail' ?>" class="initials-image profile-user-img img-responsive img-circle no-border">
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
                                            <td style="width: 20%;">
                                                <a class="AddToPeopleCart" data-cartpersonid="<?= $tmpPersonId ?>">
                                                    <i class="fa fa-cart-plus "></i>
                                                </a>
                                                <?php if ($bOkToEdit) {
                                                    ?>
                                                    <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $tmpPersonId ?>">
                                                        <i class="fas fa-pen "></i>
                                                    </a>
                                                    <a class="delete-person" data-person_name="<?= $familyMember->getFullName() ?>" data-person_id="<?= $familyMember->getId() ?>" data-view="family">
                                                        <i class="fa fa-trash-can btn-danger"></i>
                                                    </a>
                                                    <?php
                                                } ?>
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
                                                    <a href="<?= $item["editLink"] ?>"><button type="button" class="btn btn-xs btn-primary"><i class="fa fa-pen"></i></button></a>
                                                    <?php
                                                }
                                                if (isset($item["deleteLink"])) {
                                                    ?>
                                                    <a href="<?= $item["deleteLink"] ?>"><button type="button" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button></a>
                                                    <?php
                                                } ?>
                                                &nbsp;
                                                <?php
                                            } ?>
                                            <i class="fa fa-clock"></i> <?= $item['datetime'] ?></span>

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

                    <div class="tab-pane" id="groups">
                        <div class="main-box clearfix">
                            <div class="main-box-body clearfix">
                                <?php
                                //Was anything returned?
                                if (mysqli_num_rows($rsAssignedGroups) == 0) {
                                    ?>
                                    <br>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No group assignments.') ?></span>
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
                                                        <div class="label bg-gray"><?= gettext($roleName) ?></div>
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
                                                            <a href="<?= SystemURLs::getRootPath() ?>/GroupView.php?GroupID=<?= $grp_ID ?>" class="btn btn-default" role="button"><i class="fa fa-list"></i></a>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-default"><?= gettext('Action') ?></button>
                                                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
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
                                                                <button data-groupid="<?= $grp_ID ?>" data-groupname="<?= $grp_Name ?>" type="button" class="btn btn-danger groupRemove" data-toggle="dropdown"><i class="fa fa-trash-can"></i></button>
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
                        <div class="main-box clearfix">
                            <div class="main-box-body clearfix">
                                <?php
                                $sAssignedProperties = ','; ?>
                                <?php if (mysqli_num_rows($rsAssignedProperties) == 0) : ?>
                                    <br>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No property assignments.') ?></span>
                                    </div>
                                <?php else : ?>
                                    <table class="table table-condensed dt-responsive" id="assigned-properties-table" width="100%">
                                        <thead>
                                            <tr class="TableHeader">
                                                <th><?= gettext('Type') ?></th>
                                                <th><?= gettext('Name') ?></th>
                                                <th><?= gettext('Value') ?></th>
                                                <?php if ($bOkToEdit) : ?>
                                                    <th><?= gettext('Remove') ?></th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            //Loop through the rows
                                            while ($aRow = mysqli_fetch_array($rsAssignedProperties)) {
                                                $pro_Prompt = '';
                                                $r2p_Value = '';
                                                extract($aRow); ?>
                                                <tr>
                                                    <td><?= $prt_Name ?></td>
                                                    <td><?= $pro_Name ?></td>
                                                    <td><?= $r2p_Value ?></td>
                                                    <?php if ($bOkToEdit) { ?>
                                                        <td>
                                                            <a class="btn remove-property-btn" data-property_id="<?= $pro_ID ?>">
                                                                <i class="fa fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    <?php } ?>
                                                </tr>
                                                <?php
                                                $sAssignedProperties .= $pro_ID . ',';
                                            } ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>

                                <?php if ($bOkToEdit && mysqli_num_rows($rsProperties) != 0) : ?>
                                    <div class="alert alert-info">
                                        <div>
                                            <h4><strong><?= gettext('Assign a New Property') ?>:</strong></h4>

                                            <form method="post" action="<?= SystemURLs::getRootPath() . '/api/properties/persons/assign' ?>" id="assign-property-form">
                                                <div class="row">
                                                    <div class="form-group col-xs-12 col-md-7">
                                                        <select name="PropertyId" id="input-person-properties" class="form-control select2" style="width:100%" data-placeholder="Select ...">
                                                            <option disabled selected> -- <?= gettext('select an option') ?> -- </option>
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
                                                    <div id="prompt-box" class="col-xs-12 col-md-7">

                                                    </div>
                                                    <div class="form-group col-xs-12 col-md-7">
                                                        <input id="assign-property-btn" type="button" class="btn btn-primary" value="<?= gettext('Assign') ?>" name="Submit">
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="volunteer">
                        <div class="main-box clearfix">
                            <div class="main-box-body clearfix">
                                <?php

                                //Initialize row shading
                                $sRowClass = 'RowColorA';

                                $sAssignedVolunteerOpps = ',';

                                //Was anything returned?
                                if (mysqli_num_rows($rsAssignedVolunteerOpps) == 0) {
                                    ?>
                                    <br>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No volunteer opportunity assignments.') ?></span>
                                    </div>
                                    <?php
                                } else {
                                    echo '<table class="table table-condensed dt-responsive" id="assigned-volunteer-opps-table" width="100%">';
                                    echo '<thead>';
                                    echo '<tr class="TableHeader">';
                                    echo '<th>' . gettext('Name') . '</th>';
                                    echo '<th>' . gettext('Description') . '</th>';
                                    if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
                                        echo '<th>' . gettext('Remove') . '</th>';
                                    }
                                    echo '</tr>';
                                    echo '</thead>';
                                    echo '<tbody>';

                                    // Loop through the rows
                                    while ($aRow = mysqli_fetch_array($rsAssignedVolunteerOpps)) {
                                        extract($aRow);

                                        // Alternate the row style
                                        $sRowClass = AlternateRowStyle($sRowClass);

                                        echo '<tr class="' . $sRowClass . '">';
                                        echo '<td>' . $vol_Name . '</a></td>';
                                        echo '<td>' . $vol_Description . '</a></td>';

                                        if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
                                            echo '<td><a class="SmallText" href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=' . $per_ID . '&RemoveVO=' . $vol_ID . '">' . gettext('Remove') . '</a></td>';
                                        }

                                        echo '</tr>';

                                        // NOTE: this method is crude.  Need to replace this with use of an array.
                                        $sAssignedVolunteerOpps .= $vol_ID . ',';
                                    }
                                    echo '</tbody>';
                                    echo '</table>';
                                } ?>

                                <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled() && $rsVolunteerOpps->num_rows) : ?>
                                    <div class="alert alert-info">
                                        <div>
                                            <h4><strong><?= gettext('Assign a New Volunteer Opportunity') ?>:</strong></h4>

                                            <form method="post" action="PersonView.php?PersonID=<?= $iPersonID ?>">
                                                <div class="row">
                                                    <div class="form-group col-xs-12 col-md-7">
                                                        <select id="input-volunteer-opportunities" name="VolunteerOpportunityIDs[]" multiple class="form-control select2" style="width:100%" data-placeholder="Select ...">
                                                            <?php
                                                            while ($aRow = mysqli_fetch_array($rsVolunteerOpps)) {
                                                                extract($aRow);
                                                                //If the property doesn't already exist for this Person, write the <OPTION> tag
                                                                if (strlen(strstr($sAssignedVolunteerOpps, ',' . $vol_ID . ',')) == 0) {
                                                                    echo '<option value="' . $vol_ID . '">' . $vol_Name . '</option>';
                                                                }
                                                            } ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group col-xs-12 col-md-7">
                                                        <input type="submit" value="<?= gettext('Assign') ?>" name="VolunteerOpportunityAssign" class="btn btn-primary">
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
        <div id="photoUploader">

        </div>

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
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
                        <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>
                    </div>
                </div>
            </div>
        </div>
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery-photo-uploader/PhotoUploader.js"></script>
        <script src="<?= SystemURLs::getRootPath() ?>/skin/js/MemberView.js"></script>
        <script src="<?= SystemURLs::getRootPath() ?>/skin/js/PersonView.js"></script>
        <script nonce="<?= SystemURLs::getCSPNonce() ?>">
            window.CRM.currentPersonID = <?= $iPersonID ?>;
            window.CRM.plugin.mailchimp = <?= $mailchimp->isActive() ? "true" : "false" ?>;


            $("#deletePhoto").click(function() {
                window.CRM.APIRequest({
                    method: "DELETE",
                    path: "person/<?= $iPersonID ?>/photo"
                }).done(function(data) {
                    location.reload();
                });
            });

            window.CRM.photoUploader = $("#photoUploader").PhotoUploader({
                url: window.CRM.root + "/api/person/<?= $iPersonID ?>/photo",
                maxPhotoSize: window.CRM.maxUploadSize,
                photoHeight: <?= SystemConfig::getValue("iPhotoHeight") ?>,
                photoWidth: <?= SystemConfig::getValue("iPhotoWidth") ?>,
                done: function(e) {
                    window.location.reload();
                }
            });

            $("#uploadImageButton").click(function() {
                window.CRM.photoUploader.show();
            });


            $(document).ready(function() {
                $("#input-volunteer-opportunities").select2();
                $("#input-person-properties").select2();

                $("#assigned-volunteer-opps-table").DataTable(window.CRM.plugin.dataTable);
                $("#assigned-properties-table").DataTable(window.CRM.plugin.dataTable);


                contentExists(window.CRM.root + "/api/person/" + window.CRM.currentPersonID + "/photo", function(success) {
                    if (success) {
                        $("#view-larger-image-btn").removeClass('hide');

                        $("#view-larger-image-btn").click(function() {
                            bootbox.alert({
                                title: "<?= gettext('Photo') ?>",
                                message: '<img class="img-rounded img-responsive center-block" src="<?= SystemURLs::getRootPath() ?>/api/person/' + window.CRM.currentPersonID + '/photo" />',
                                backdrop: true
                            });
                        });
                    }
                });

            });
        </script>

        <?php require 'Include/Footer.php' ?>
