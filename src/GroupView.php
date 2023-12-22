<?php
/* * *****************************************************************************
 *
 *  filename    : GroupView.php
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
 *
 *  Additional Contributors:
 *  2006-2007 Ed Davis
 *	2017 Philippe Logel
 *
 *
 *  Copyright Contributors
 *
 *
 * **************************************************************************** */

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

//Get the GroupID out of the querystring
$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');

if ($iGroupID < 1) {
    RedirectUtils::redirect('GroupList.php');
}

//Get the data on this group
$thisGroup = GroupQuery::create()->findOneById($iGroupID);

//Look up the default role name
$defaultRole = ListOptionQuery::create()->filterById($thisGroup->getRoleListId())->filterByOptionId($thisGroup->getDefaultRole())->findOne();

//Get the group's type name
if ($thisGroup->getType() > 0) {
    $sGroupType = ListOptionQuery::create()->filterById(3)->filterByOptionId($thisGroup->getType())->findOne()->getOptionName();
} else {
    $sGroupType = gettext('Unassigned');
}

//Get the Properties assigned to this Group
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
        FROM record2property_r2p
        LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
        LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
        WHERE pro_Class = 'g' AND r2p_record_ID = " . $iGroupID .
        ' ORDER BY prt_Name, pro_Name';
$rsAssignedProperties = RunQuery($sSQL);

//Get all the properties
$sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'g' ORDER BY pro_Name";
$rsProperties = RunQuery($sSQL);

// Get data for the form as it now exists..
$sSQL = 'SELECT * FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';
$rsPropList = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsPropList);

//Set the page title
$sPageTitle = gettext('Group View') . ' : ' . $thisGroup->getName();

require 'Include/Header.php';
?>

<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title"><?= gettext('Group Functions') ?></h3>
  </div>
  <div class="card-body">

    <?php
    if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
        echo '<a class="btn btn-app" href="GroupEditor.php?GroupID=' . $thisGroup->getId() . '"><i class="fas fa-pen"></i>' . gettext('Edit this Group') . '</a>';
        echo '<button class="btn btn-app"  id="deleteGroupButton"><i class="fa fa-trash"></i>' . gettext('Delete this Group') . '</button>'; ?>

        <?php
        if ($thisGroup->getHasSpecialProps()) {
            echo '<a class="btn btn-app" href="GroupPropsFormEditor.php?GroupID=' . $thisGroup->getId() . '"><i class="fa fa-list-alt"></i>' . gettext('Edit Group-Specific Properties Form') . '</a>';
        }
    }?>

    <a class="btn btn-app" id="AddGroupMembersToCart" data-groupid="<?= $thisGroup->getId() ?>"><i class="fa fa-users"></i><?= gettext('Add Group Members to Cart') ?></a>
    <a class="btn btn-app" href="MapUsingGoogle.php?GroupID=<?= $thisGroup->getId() ?>"><i class="fa fa-map-marker"></i><?= gettext('Map this group') ?></a>

    <?php

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

        if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
        // Display link
            ?>
        <div class="btn-group">
          <a  class="btn btn-app" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><i class="fa fa-paper-plane"></i><?= gettext('Email Group') ?></a>
          <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
            <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
          </ul>
        </div>

        <div class="btn-group">
          <a class="btn btn-app" href="mailto:?bcc=<?= mb_substr($sEmailLink, 0, -3) ?>"><i class="fa-regular fa-paper-plane"></i><?= gettext('Email (BCC)') ?></a>
          <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
            <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:?bcc=') ?>
          </ul>
        </div>

            <?php
        }
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
    if ($sPhoneLink) {
        if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
            // Display link
            echo '<a class="btn btn-app" href="javascript:void(0)" onclick="allPhonesCommaD()"><i class="fa fa-mobile-phone"></i>' . gettext('Text Group') . '</a>';
            echo '<script nonce="' . SystemURLs::getCSPNonce() . '">function allPhonesCommaD() {prompt("' . gettext("Press CTRL + C to copy all group members\' phone numbers") . '", "' . mb_substr($sPhoneLink, 0, -2) . '")};</script>';
        }
    }
    ?>
  </div>
</div>

<div class="card">
    <div class="card-body">
        <?= $thisGroup->getDescription() ?>
        <p/><p/><p/>
        <button class="btn btn-success" type="button">
            <?= gettext('Type of Group') ?> <span class="badge"> <?= $sGroupType ?> </span>
        </button>
        <button class="btn btn-info" type="button">
        <?php if ($defaultRole !== null) {
            ?>
            <?= gettext('Default Role') ?> <span class="badge"><?= $defaultRole->getOptionName() ?></span>
            <?php
        } ?>
        </button>
        <button class="btn btn-primary" type="button">
            <?= gettext('Total Members') ?> <span class="badge" id="iTotalMembers"></span>
        </button>
    </div>
</div>


<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title"><?= gettext('Quick Settings') ?></h3>
  </div>
  <div class="card-body">
      <form>
          <div class="col-sm-3"> <b><?= gettext('Status') ?>:</b> <input data-size="small" id="isGroupActive" type="checkbox" data-toggle="toggle" data-on="<?= gettext('Active') ?>" data-off="<?= gettext('Disabled') ?>"> </div>
          <div class="col-sm-3"> <b><?= gettext('Email export') ?>:</b> <input data-size="small" id="isGroupEmailExport" type="checkbox" data-toggle="toggle" data-on="<?= gettext('Include') ?>" data-off="<?= gettext('Exclude') ?>"></div>
      </form>
  </div>
</div>

      <div class="card">
  <div class="card-header with-border">
    <h3 class="card-title"><?= gettext('Group Properties') ?></h3>
  </div>
  <div class="card-body">
      <table width="100%">
      <tr>
        <td>
          <b><?= gettext('Group-Specific Properties') ?>:</b>

          <?php
            if ($thisGroup->getHasSpecialProps()) {
                // Create arrays of the properties.
                for ($row = 1; $row <= $numRows; $row++) {
                    $aRow = mysqli_fetch_array($rsPropList, MYSQLI_BOTH);
                    extract($aRow);

                    $aNameFields[$row] = $prop_Name;
                    $aDescFields[$row] = $prop_Description;
                    $aFieldFields[$row] = $prop_Field;
                    $aTypeFields[$row] = $type_ID;
                }

                // Construct the table

                if (!$numRows) {
                    echo '<p><?= gettext("No member properties have been created")?></p>';
                } else {
                    ?>
              <table width="100%" cellpadding="2" cellspacing="0">
                <tr class="TableHeader">
                  <td><?= gettext('Type') ?></td>
                  <td><?= gettext('Name') ?></td>
                  <td><?= gettext('Description') ?></td>
                </tr>
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
            if (mysqli_num_rows($rsAssignedProperties) == 0) {
                // No, indicate nothing returned
                echo '<p>' . gettext('No property assignments') . '.</p>';
            } else {
                // Display table of properties?>
              <table width="100%" cellpadding="2" cellspacing="0">
                <tr class="TableHeader">
                  <td width="15%" valign="top"><b><?= gettext('Type') ?></b>
                  <td valign="top"><b><?= gettext('Name') ?></b>
                  <td valign="top"><b><?= gettext('Value') ?></td>
                  <?php
                    if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                        echo '<td valign="top"><b>' . gettext('Edit Value') . '</td>';
                        echo '<td valign="top"><b>' . gettext('Remove') . '</td>';
                    }
                    echo '</tr>';

                    $last_pro_prt_ID = '';
                    $bIsFirst = true;

                //Loop through the rows
                    while ($aRow = mysqli_fetch_array($rsAssignedProperties)) {
                        $pro_Prompt = '';
                        $r2p_Value = '';

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
                            echo '<td valign="top">&nbsp;</td>';
                        }

                        echo '<td valign="top">' . $pro_Name . '&nbsp;</td>';
                        echo '<td valign="top">' . $r2p_Value . '&nbsp;</td>';

                        if (strlen($pro_Prompt) > 0 && AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                            echo '<td valign="top"><a href="PropertyAssign.php?GroupID=' . $iGroupID . '&amp;PropertyID=' . $pro_ID . '">' . gettext('Edit Value') . '</a></td>';
                        } else {
                            echo '<td>&nbsp;</td>';
                        }

                        if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                            echo '<td valign="top"><a href="PropertyUnassign.php?GroupID=' . $iGroupID . '&amp;PropertyID=' . $pro_ID . '">' . gettext('Remove') . '</a>';
                        } else {
                            echo '<td>&nbsp;</td>';
                        }

                        echo '</tr>';

                        //Alternate the row style
                        $sRowClass = AlternateRowStyle($sRowClass);

                        $sAssignedProperties .= $pro_ID . ',';
                    }

                    echo '</table>';
            }

            if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
                echo '<form method="post" action="PropertyAssign.php?GroupID=' . $iGroupID . '">';
                echo '<p>';
                echo '<span>' . gettext('Assign a New Property:') . '</span>';
                echo '<select name="PropertyID">';

                while ($aRow = mysqli_fetch_array($rsProperties)) {
                    extract($aRow);

                    //If the property doesn't already exist for this Person, write the <OPTION> tag
                    if (strlen(strstr($sAssignedProperties, ',' . $pro_ID . ',')) == 0) {
                        echo '<option value="' . $pro_ID . '">' . $pro_Name . '</option>';
                    }
                }

                echo '</select>';
                echo ' <input type="submit" class="btn btn-success" value="' . gettext('Assign') . '" name="Submit" style="font-size: 8pt;">';
                echo '</p></form>';
            } else {
                echo '<br><br><br>';
            }
            ?>



                </td>
              </tr>
            </table>
            </div>
            </div>

            <div class="card">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Group Members:') ?></h3>
              </div>
              <div class="card-body">
                <form action="#" method="get" class="sidebar-form">
                    <label for="addGroupMember"><?= gettext('Add Group Member: ') ?></label>
                    <select id="addGroupMember" class="form-control personSearch" name="addGroupMember" style="width: 300px;">
                    </select>
                  </form>
                </div>
                <!-- START GROUP MEMBERS LISTING  -->
                <table class="table" id="membersTable">
                </table>
                <div class="card">
                  <div class="card-body">
                    <table class="table" id="depositsTable"></table>
                    <button type="button" id="deleteSelectedRows" class="btn btn-danger" disabled> <?= gettext('Remove Selected Members from group') ?> </button>
                    <div class="btn-group">
                      <button type="button" id="addSelectedToCart" class="btn btn-success"  disabled> <?= gettext('Add Selected Members to Cart') ?></button>
                      <button type="button" id="buttonDropdown" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-expanded="false" disabled>
                        <span class="caret"></span>
                        <span class="sr-only">Toggle Dropdown</span>
                      </button>
                      <ul class="dropdown-menu" role="menu">
                        <li><a id="addSelectedToGroup"   disabled> <?= gettext('Add Selected Members to Group') ?></a></li>
                        <li><a id="moveSelectedToGroup"  disabled> <?= gettext('Move Selected Members to Group') ?></a></li>
                      </ul>
                    </div>
                  </div>
                </div>
                </form>
                <!-- END GROUP MEMBERS LISTING -->

            </div>
            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
              window.CRM.currentGroup = <?= $iGroupID ?>;
              window.CRM.iProfilePictureListSize = <?= SystemConfig::getValue('iProfilePictureListSize') ?>;
              var dataT = 0;
              $(document).ready(function () {
                $('#isGroupActive').prop('checked', <?= $thisGroup->isActive() ? 'true' : 'false' ?>).change();
                $('#isGroupEmailExport').prop('checked', <?= $thisGroup->isIncludeInEmailExport() ? 'true' : 'false' ?>).change();
                $("#deleteGroupButton").click(function() {
                  console.log("click");
                  bootbox.setDefaults({
                                    locale: window.CRM.shortLocale}),
                  bootbox.confirm({
                    title: "<?= gettext("Confirm Delete Group") ?>",
                    message: '<p style="color: red">'+
                      "<?= gettext("Please confirm deletion of this group record") ?>: <?= $thisGroup->getName() ?></p>"+
                      "<p>"+
                      "<?= gettext("This will also delete all Roles and Group-Specific Property data associated with this Group record.") ?>"+
                      "</p><p>"+
                      "<?= gettext("All group membership and properties will be destroyed.  The group members themselves will not be altered.") ?></p>",
                    callback: function (result) {
                      if (result)
                      {
                          window.CRM.APIRequest({
                            method: "DELETE",
                            path: "groups/" + window.CRM.currentGroup,
                          }).done(function (data) {
                            if (data.status == "success")
                              window.location.href = window.CRM.root + "/GroupList.php";
                          });
                      }
                    }
                  });
                });
              });
            </script>
            <script src="skin/js/GroupView.js" ></script>

            <?php require 'Include/Footer.php' ?>
