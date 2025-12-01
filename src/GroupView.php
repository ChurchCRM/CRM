<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Get the GroupID out of the querystring
$iGroupID = (int) InputUtils::legacyFilterInput($_GET['GroupID'], 'int');

if ($iGroupID < 1) {
    RedirectUtils::redirect('GroupList.php');
}

// Get the data on this group
$thisGroup = GroupQuery::create()->findOneById($iGroupID);

// Look up the default role name
$defaultRole = ListOptionQuery::create()->filterById($thisGroup->getRoleListId())->filterByOptionId($thisGroup->getDefaultRole())->findOne();

// Get the group's type name
if ($thisGroup->getType() > 0) {
    $sGroupType = ListOptionQuery::create()->filterById(3)->filterByOptionId($thisGroup->getType())->findOne()->getOptionName();
} else {
    $sGroupType = gettext('Unassigned');
}

// Get the Properties assigned to this Group
$sSQL = 'SELECT * FROM record2property_r2p LEFT JOIN property_pro ON r2p_pro_ID = pro_ID LEFT JOIN propertytype_prt ON pro_prt_ID = prt_ID WHERE pro_Class = "g" AND r2p_record_ID = ' . $iGroupID . ' ORDER BY prt_Name, pro_Name';
$rsAssignedProperties = RunQuery($sSQL);

// Get all the properties
$sSQL = 'SELECT * FROM property_pro WHERE pro_Class = "g" ORDER BY pro_Name';
$rsProperties = RunQuery($sSQL);

// Get data for the form as it now exists..
$sSQL = 'SELECT * FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';
$rsPropList = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsPropList);

$sPageTitle = gettext('Group View') . ' : ' . InputUtils::escapeHTML($thisGroup->getName());

require_once 'Include/Header.php';

// Store email and phone data for later use in buttons
// Email Group link
// Note: This will email entire group, even if a specific role is currently selected.
$sSQL = "SELECT per_Email, fam_Email, lst_OptionName as virt_RoleName
    FROM person_per
    LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
    LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
    LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
    INNER JOIN list_lst on  grp_RoleListID = lst_ID AND p2g2r_rle_ID = lst_OptionID
WHERE per_ID NOT IN
    (SELECT per_ID
        FROM person_per
        INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
        INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email')
    AND p2g2r_grp_ID = " . $iGroupID;
$rsEmailList = RunQuery($sSQL);
$sEmailLink = '';
$roleEmails = [];
$sMailtoDelimiter = AuthenticationManager::getCurrentUser()->getUserConfigString("sMailtoDelimiter");
while (list($per_Email, $fam_Email, $virt_RoleName) = mysqli_fetch_row($rsEmailList)) {
    $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);
    if ($sEmail) {
        /* if ($sEmailLink) // Don't put delimiter before first email
    $sEmailLink .= $sMailtoDelimiter; */
        // Add email only if email address is not already in string
        if (!stristr($sEmailLink, $sEmail)) {
            $sEmailLink .= $sEmail .= $sMailtoDelimiter;
            $roleEmails[$virt_RoleName] = $sEmailLink;
        }
    }
}
if ($sEmailLink) {
    // Add default email if default email has been set and is not already in string
    if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($sEmailLink, SystemConfig::getValue('sToEmailAddress'))) {
        $sEmailLink .= $sMailtoDelimiter . SystemConfig::getValue('sToEmailAddress');
    }
    $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
}

// Group Text Message Comma Delimited - added by RSBC
// Note: This will provide cell phone numbers for the entire group, even if a specific role is currently selected.
$sSQL = "SELECT per_CellPhone, fam_CellPhone
    FROM person_per
    LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
    LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
    LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
WHERE per_ID NOT IN
    (SELECT per_ID
    FROM person_per
    INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
    INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not SMS')
AND p2g2r_grp_ID = " . $iGroupID;
$rsPhoneList = RunQuery($sSQL);
$sPhoneLink = '';
$sCommaDelimiter = ', ';
while (list($per_CellPhone, $fam_CellPhone) = mysqli_fetch_row($rsPhoneList)) {
    $sPhone = SelectWhichInfo($per_CellPhone, $fam_CellPhone, false);
    if ($sPhone) {
        /* if ($sPhoneLink) // Don't put delimiter before first phone
    $sPhoneLink .= $sCommaDelimiter; */
        // Add phone only if phone is not already in string
        if (!stristr($sPhoneLink, $sPhone)) {
            $sPhoneLink .= $sPhone .= $sCommaDelimiter;
        }
    }
}
?>


<div class="card card-info card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-info-circle"></i> <?= InputUtils::escapeHTML($thisGroup->getName()) ?></h3>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <?= InputUtils::escapeHTML($thisGroup->getDescription() ?? '') ?>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="info-box bg-success">
                    <span class="info-box-icon"><i class="fa-solid fa-layer-group"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Type of Group') ?></span>
                        <span class="info-box-number"><?= $sGroupType ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box bg-info">
                    <span class="info-box-icon"><i class="fa-solid fa-user-tag"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Default Role') ?></span>
                        <span class="info-box-number"><?= $defaultRole !== null ? $defaultRole->getOptionName() : gettext('None') ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box bg-primary">
                    <span class="info-box-icon"><i class="fa-solid fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Total Members') ?></span>
                        <span class="info-box-number" id="iTotalMembers"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <?php
                if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) { ?>
                    <a class="btn btn-app bg-info" href="GroupEditor.php?GroupID=<?= $thisGroup->getId() ?>">
                        <i class="fa-solid fa-pen fa-3x"></i><br>
                        <?= gettext('Edit this Group') ?>
                    </a>
                    <button class="btn btn-app bg-danger" id="deleteGroupButton">
                        <i class="fa-solid fa-trash fa-3x"></i><br>
                        <?= gettext('Delete this Group') ?>
                    </button>
                    <?php
                    if ($thisGroup->getHasSpecialProps()) { ?>
                        <a class="btn btn-app bg-purple" href="GroupPropsFormEditor.php?GroupID=<?= $thisGroup->getId() ?>">
                            <i class="fa-solid fa-list-alt fa-3x"></i><br>
                            <?= gettext('Edit Group-Specific Properties Form') ?>
                        </a>
                    <?php }
                } ?>
                <a class="btn btn-app bg-success" id="AddGroupMembersToCart" data-groupid="<?= $thisGroup->getId() ?>">
                    <i class="fa-solid fa-users fa-3x"></i><br>
                    <?= gettext('Add Group Members to Cart') ?>
                </a>
                <a class="btn btn-app bg-primary" href="MapUsingGoogle.php?GroupID=<?= $thisGroup->getId() ?>">
                    <i class="fa-solid fa-map-marker fa-3x"></i><br>
                    <?= gettext('Map this group') ?>
                </a>
                <?php
                // Email buttons
                if ($sEmailLink && AuthenticationManager::getCurrentUser()->isEmailEnabled()) { ?>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-app bg-teal dropdown-toggle" type="button" id="emailGroupDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fa-solid fa-paper-plane fa-3x"></i><br>
                            <?= gettext('Email Group') ?>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="emailGroupDropdown">
                            <a class="dropdown-item" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All Members') ?></a>
                            <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
                        </div>
                    </div>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-app bg-navy dropdown-toggle" type="button" id="emailGroupBccDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fa-solid fa-user-secret fa-3x"></i><br>
                            <?= gettext('Email (BCC)') ?>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="emailGroupBccDropdown">
                            <a class="dropdown-item" href="mailto:?bcc=<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All Members') ?></a>
                            <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:?bcc=') ?>
                        </div>
                    </div>
                <?php }
                // Text button
                if ($sPhoneLink && AuthenticationManager::getCurrentUser()->isEmailEnabled()) { ?>
                    <a class="btn btn-app bg-orange" href="javascript:void(0)" onclick="allPhonesCommaD()">
                        <i class="fa-solid fa-mobile-phone fa-3x"></i><br>
                        <?= gettext('Text Group') ?>
                    </a>
                    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                        function allPhonesCommaD() {
                            prompt("<?= gettext("Press CTRL + C to copy all group members' phone numbers") ?>", "<?= mb_substr($sPhoneLink, 0, -2) ?>");
                        }
                    </script>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-list-check"></i> <?= gettext('Group Properties') ?></h3>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-lg-6 col-md-12 mb-3">
                <b><?= gettext('Status') ?>:</b> 
                <br>
                <input data-size="normal" id="isGroupActive" type="checkbox" data-toggle="toggle" data-on="<?= gettext('Active') ?>" data-off="<?= gettext('Disabled') ?>">
            </div>
            <div class="col-lg-6 col-md-12 mb-3">
                <b><?= gettext('Email export') ?>:</b> 
                <br>
                <input data-size="normal" id="isGroupEmailExport" type="checkbox" data-toggle="toggle" data-on="<?= gettext('Include') ?>" data-off="<?= gettext('Exclude') ?>">
            </div>
        </div>
        <hr>
        <table width="100%">
            <tr>
                <td>
                    <b><?= gettext('Group-Specific Properties') ?>:</b>

                    <?php
                    if ($thisGroup->getHasSpecialProps()) {
                        // Create arrays of the properties.
                        $aNameFields = [];
                        $aDescFields = [];
                        $aFieldFields = [];
                        $aTypeFields = [];
                        while ($aRow = mysqli_fetch_array($rsPropList)) {
                            extract($aRow);
                            $aNameFields[$prop_ID] = $prop_Name;
                            $aDescFields[$prop_ID] = $prop_Description;
                            $aFieldFields[$prop_ID] = $prop_Field;
                            $aTypeFields[$prop_ID] = $type_ID;
                        }

                        // Construct the table

                        if (!$numRows) {
                            echo '<p><?= gettext("No member properties have been created")?></p>';
                        } else {
                            ?>
                            <table class="table w-100">
                                <thead>
                                <tr>
                                    <th><?= gettext('Type') ?></th>
                                    <th><?= gettext('Name') ?></th>
                                    <th><?= gettext('Description') ?></th>
                                </tr>
                                </thead>
                                <tbody>
                            <?php
                            $sRowClass = 'RowColorA';
                            for ($row = 1; $row <= $numRows; $row++) {
                                $sRowClass = AlternateRowStyle($sRowClass);
                                echo '<tr class="' . $sRowClass . '">';
                                echo '<td>' . $aPropTypes[$aTypeFields[$row]] . '</td>';
                                echo '<td>' . $aNameFields[$row] . '</td>';
                                echo '<td>' . $aDescFields[$row] . '&nbsp;</td>';
                                echo '</tr>';
                            }
                            echo '</tbody>';
                            echo '</table>';
                        }
                    } else {
                        echo '<p>' . gettext('Disabled for this group.') . '</p>';
                    }

                    //Print Assigned Properties
                    echo '<br><hr/>';
                    echo '<b>' . gettext('Assigned Properties') . ':</b>';
                    $sAssignedProperties = ',';

                    //Was anything returned?
                    if (mysqli_num_rows($rsAssignedProperties) === 0) {
                        // No, indicate nothing returned
                        echo '<p>' . gettext('No property assignments') . '.</p>';
                    } else {
                        // Display table of properties
                        ?>
                            <table class="table w-100">
                                <thead>
                                <tr>
                                    <th width="15%" class="align-top"><b><?= gettext('Type') ?></b></th>
                                    <th class="align-top"><b><?= gettext('Name') ?></b></th>
                                    <th class="align-top"><b><?= gettext('Value') ?></b></th>
                                <?php
                                if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                                    echo '<th class="align-top"><b>' . gettext('Edit Value') . '</th>';
                                    echo '<th class="align-top"><b>' . gettext('Remove') . '</th>';
                                }
                                echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';

                                $last_pro_prt_ID = '';
                                $bIsFirst = true;

                                //Loop through the property assignments
                                while ($aRow = mysqli_fetch_array($rsAssignedProperties)) {
                                    extract($aRow);

                                    if ($pro_prt_ID != $last_pro_prt_ID) {
                                        echo '<tr class="';
                                        if ($bIsFirst) {
                                            echo 'RowColorB';
                                        } else {
                                            echo 'RowColorC';
                                        }
                                        echo '"><td><b>' . $prt_Name . '</b></td>';

                                        $bIsFirst = false;
                                        $last_pro_prt_ID = $pro_prt_ID;
                                        $sRowClass = 'RowColorB';
                                    } else {
                                        echo '<tr class="' . $sRowClass . '">';
                                        echo '<td class="align-top">&nbsp;</td>';
                                    }

                                    echo '<td class="align-top">' . $pro_Name . '&nbsp;</td>';
                                    echo '<td class="align-top">' . $r2p_Value . '&nbsp;</td>';

                                    if (strlen($pro_Prompt) > 0 && AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                                        echo '<td class="align-top"><a href="PropertyAssign.php?GroupID=' . $iGroupID . '&amp;PropertyID=' . $pro_ID . '">' . gettext('Edit Value') . '</a></td>';
                                    } else {
                                        echo '<td>&nbsp;</td>';
                                    }

                                    if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                                        echo '<td class="align-top"><a href="PropertyUnassign.php?GroupID=' . $iGroupID . '&amp;PropertyID=' . $pro_ID . '">' . gettext('Remove') . '</a>';
                                    } else {
                                        echo '<td>&nbsp;</td>';
                                    }

                                    echo '</tr>';

                                    //Alternate the row style
                                    $sRowClass = AlternateRowStyle($sRowClass); 

                                    $sAssignedProperties .= $pro_ID . ',';
                                }

                                echo '</tbody>';
                                echo '</table>';
                    }

                    if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                        // Build the list of available properties first
                        $availableProperties = [];
                        while ($aRow = mysqli_fetch_array($rsProperties)) {
                            extract($aRow);

                            //If the property doesn't already exist for this Group, add it to the list
                            if (strlen(strstr($sAssignedProperties, ',' . $pro_ID . ',')) === 0) {
                                $availableProperties[] = ['id' => $pro_ID, 'name' => $pro_Name];
                            }
                        }

                        // Only show the form if there are properties available to assign
                        if (!empty($availableProperties)) {
                            echo '<form method="post" action="PropertyAssign.php?GroupID=' . $iGroupID . '">';
                            echo '<p>';
                            echo '<span>' . gettext('Assign a New Property:') . '</span>';
                            echo '<select name="PropertyID">';

                            foreach ($availableProperties as $prop) {
                                echo '<option value="' . $prop['id'] . '">' . $prop['name'] . '</option>';
                            }

                            echo '</select>';
                            echo ' <input type="submit" class="btn btn-success btn-sm" value="' . gettext('Assign') . '" name="Submit">';
                            echo '</p></form>';
                        }
                    } else {
                        echo '<br><br><br>';
                    }
                    ?>

                </td>
            </tr>
        </table>
    </div>
</div>

<div class="card card-success card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-users"></i> <?= gettext('Group Members') ?></h3>
    </div>
    <div class="card-body">
        <form action="#" method="get" class="mb-3">
            <label for="addGroupMember"><?= gettext('Add Group Member: ') ?></label>
            <select id="addGroupMember" class="form-control personSearch" name="addGroupMember" style="width: 300px;">
            </select>
        </form>
        <!-- START GROUP MEMBERS LISTING  -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm" id="membersTable">
            </table>
        </div>
    </div>
    <div class="card-footer">
        <button type="button" id="deleteSelectedRows" class="btn btn-danger" disabled> <?= gettext('Remove Selected Members from group') ?> </button>
            <div class="btn-group">
                <button type="button" id="addSelectedToCart" class="btn btn-success" disabled> <?= gettext('Add Selected Members to Cart') ?></button>
                <button type="button" id="buttonDropdown" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-expanded="false" disabled>
                    <span class="caret"></span>
                    <span class="sr-only">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu" role="menu">
                    <li><a id="addSelectedToGroup" disabled> <?= gettext('Add Selected Members to Group') ?></a></li>
                    <li><a id="moveSelectedToGroup" disabled> <?= gettext('Move Selected Members to Group') ?></a></li>
                </ul>
            </div>
        </div>
    </div>
    <!-- END GROUP MEMBERS LISTING -->

</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentGroup = <?= $iGroupID ?>;
    window.CRM.iProfilePictureListSize = <?= SystemConfig::getValue('iProfilePictureListSize') ?>;
    var dataT = 0;
    $(document).ready(function() {
        // Wait for locales to load before setting up handlers that use bootbox
        window.CRM.onLocalesReady(function() {
            $('#isGroupActive').prop('checked', <?= $thisGroup->isActive() ? 'true' : 'false' ?>).change();
            $('#isGroupEmailExport').prop('checked', <?= $thisGroup->isIncludeInEmailExport() ? 'true' : 'false' ?>).change();
            $("#deleteGroupButton").click(function() {
                bootbox.setDefaults({
                        locale: window.CRM.shortLocale
                    }),
                    bootbox.confirm({
                        title: "<?= gettext("Confirm Delete Group") ?>",
                        message: '<p class="text-danger">' +
                            "<?= gettext("Please confirm deletion of this group record") ?>: " + <?= json_encode($thisGroup->getName(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> + "</p>" +
                            "<p>" +
                            "<?= gettext("This will also delete all Roles and Group-Specific Property data associated with this Group record.") ?>" +
                            "</p><p>" +
                            "<?= gettext("All group membership and properties will be destroyed.  The group members themselves will not be altered.") ?></p>",
                        callback: function(result) {
                            if (result) {
                                window.CRM.APIRequest({
                                    method: "DELETE",
                                    path: "groups/" + window.CRM.currentGroup,
                                }).done(function(data) {
                                    if (data.status == "success")
                                        window.location.href = window.CRM.root + "/GroupList.php";
                                });
                            }
                        }
                    });
            });
        });
    });
</script>
<script src="skin/js/GroupView.js"></script>
<?php
require_once 'Include/Footer.php';
