<?php

/*******************************************************************************
 *
 *  filename    : PersonCustomFieldsEditor.php
 *  last change : 2003-03-28
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Editor for custom person fields
  ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page
if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

$sPageTitle = gettext('Custom Person Fields Editor');

require 'Include/Header.php'; ?>


<div class="card card-body">

  <?php

    $bErrorFlag = false;
    $bNewNameError = false;
    $bDuplicateNameError = false;
    $aNameErrors = [];

  // Does the user want to save changes to text fields?
    if (isset($_POST['SaveChanges'])) {
        // Fill in the other needed custom field data arrays not gathered from the form submit
        $sSQL = 'SELECT * FROM person_custom_master ORDER BY custom_Order';
        $rsCustomFields = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsCustomFields);

        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH);
            extract($aRow);

            $aFieldFields[$row] = $custom_Field;
            $aTypeFields[$row] = $type_ID;
            if (isset($custom_Special)) {
                $aSpecialFields[$row] = $custom_Special;
            } else {
                $aSpecialFields[$row] = 'NULL';
            }
        }

        for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
            $aNameFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$iFieldID . 'name']);

            if (strlen($aNameFields[$iFieldID]) == 0) {
                $aNameErrors[$iFieldID] = true;
                $bErrorFlag = true;
            } else {
                $aNameErrors[$iFieldID] = false;
            }

            $aFieldSecurity[$iFieldID] = $_POST[$iFieldID . 'FieldSec'];

            if (isset($_POST[$iFieldID . 'special'])) {
                $aSpecialFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$iFieldID . 'special'], 'int');

                if ($aSpecialFields[$iFieldID] == 0) {
                    $aSpecialErrors[$iFieldID] = true;
                    $bErrorFlag = true;
                } else {
                    $aSpecialErrors[$iFieldID] = false;
                }
            }
        }

        // If no errors, then update.
        if (!$bErrorFlag) {
            for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
                $sSQL = "UPDATE person_custom_master
					SET custom_Name = '" . $aNameFields[$iFieldID] . "',
						custom_Special = " . $aSpecialFields[$iFieldID] . ",
						custom_FieldSec = " . $aFieldSecurity[$iFieldID] . "
					WHERE custom_Field = '" . $aFieldFields[$iFieldID] . "';";
                RunQuery($sSQL);
            }
        }
    } else {
        // Check if we're adding a field
        if (isset($_POST['AddField'])) {
            $newFieldType = InputUtils::legacyFilterInput($_POST['newFieldType'], 'int');
            $newFieldName = InputUtils::legacyFilterInput($_POST['newFieldName']);
            $newFieldSec = $_POST['newFieldSec'];

            if (strlen($newFieldName) == 0) {
                $bNewNameError = true;
            } elseif (strlen($newFieldType) == 0 || $newFieldType < 1) {
                // This should never happen, but check anyhow.
          // $bNewTypeError = true;
            } else {
                $sSQL = 'SELECT custom_Name FROM person_custom_master';
                $rsCustomNames = RunQuery($sSQL);
                while ($aRow = mysqli_fetch_array($rsCustomNames)) {
                    if ($aRow[0] == $newFieldName) {
                        $bDuplicateNameError = true;
                        break;
                    }
                }

                if (!$bDuplicateNameError) {
                    global $cnInfoCentral;
                    // Find the highest existing field number in the table to determine the next free one.
                    // This is essentially an auto-incrementing system where deleted numbers are not re-used.
                    $fields = mysqli_query($cnInfoCentral, 'SHOW COLUMNS FROM person_custom');
                    $last = mysqli_num_rows($fields) - 1;

                    // Set the new field number based on the highest existing.  Chop off the "c" at the beginning of the old one's name.
                    // The "c#" naming scheme is necessary because MySQL 3.23 doesn't allow numeric-only field (table column) names.
                    $fields = mysqli_query($cnInfoCentral, 'SELECT * FROM person_custom');
                    $fieldInfo = mysqli_fetch_field_direct($fields, $last);
                    $newFieldNum = (int) mb_substr($fieldInfo->name, 1) + 1;

                    // If we're inserting a new custom-list type field, create a new list and get its ID
                    if ($newFieldType == 12) {
                        // Get the first available lst_ID for insertion.  lst_ID 0-9 are reserved for permanent lists.
                        $sSQL = 'SELECT MAX(lst_ID) FROM list_lst';
                        $aTemp = mysqli_fetch_array(RunQuery($sSQL));
                        if ($aTemp[0] > 9) {
                            $newListID = $aTemp[0] + 1;
                        } else {
                            $newListID = 10;
                        }

                        // Insert into the lists table with an example option.
                        $listOption = new ListOption();
                        $listOption
                            ->setId($newListID)
                            ->setOptionId(1)
                            ->setOptionSequence(1)
                            ->setOptionName(gettext('Default Option'));
                        $listOption->save();

                        $newSpecial = "'$newListID'";
                    } else {
                        $newSpecial = 'NULL';
                    }

                    // Insert into the master table
                    $newOrderID = $last + 1;
                    $sSQL = "INSERT INTO person_custom_master
						(custom_Order , custom_Field , custom_Name ,  custom_Special , custom_FieldSec, type_ID)
						VALUES ('" . $newOrderID . "', 'c" . $newFieldNum . "', '" . $newFieldName . "', " . $newSpecial . ", '" . $newFieldSec . "', '" . $newFieldType . "');";
                    RunQuery($sSQL);

                    // Insert into the custom fields table
                    $sSQL = 'ALTER TABLE person_custom ADD c' . $newFieldNum . ' ';

                    switch ($newFieldType) {
                        case 1:
                                  $sSQL .= "ENUM('false', 'true')";
                            break;
                        case 2:
                                $sSQL .= 'DATE';
                            break;
                        case 3:
                              $sSQL .= 'VARCHAR(50)';
                            break;
                        case 4:
                            $sSQL .= 'VARCHAR(100)';
                            break;
                        case 5:
                            $sSQL .= 'TEXT';
                            break;
                        case 6:
                            $sSQL .= 'YEAR';
                            break;
                        case 7:
                            $sSQL .= "ENUM('winter', 'spring', 'summer', 'fall')";
                            break;
                        case 8:
                            $sSQL .= 'INT';
                            break;
                        case 9:
                            $sSQL .= 'MEDIUMINT(9)';
                            break;
                        case 10:
                            $sSQL .= 'DECIMAL(10,2)';
                            break;
                        case 11:
                            $sSQL .= 'VARCHAR(30)';
                            break;
                        case 12:
                            $sSQL .= 'TINYINT(4)';
                    }

                    $sSQL .= ' DEFAULT NULL ;';
                    RunQuery($sSQL);

                    $bNewNameError = false;
                }
            }
        }

        // Get data for the form as it now exists..
        $sSQL = 'SELECT * FROM person_custom_master ORDER BY custom_Order';

        $rsCustomFields = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsCustomFields);

        // Create arrays of the fields.
        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH);
            extract($aRow);

            $aNameFields[$row] = $custom_Name;
            $aSpecialFields[$row] = $custom_Special;
            $aFieldFields[$row] = $custom_Field;
            $aTypeFields[$row] = $type_ID;
            $aFieldSecurity[$row] = $custom_FieldSec;
        }
    }
  // Prepare Security Group list
    $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
    $rsSecurityGrp = RunQuery($sSQL);
    $aSecurityType = [];

    $aSecurityGrp = [];
    while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
        $aSecurityGrp[] = $aRow;
        extract($aRow);
        $aSecurityType[$lst_OptionID] = $lst_OptionName;
    }
    function GetSecurityList($aSecGrp, $fld_name, $currOpt = 'bAll')
    {
        $sOptList = '<select name="' . $fld_name . '">';
        $grp_Count = count($aSecGrp);

        for ($i = 0; $i < $grp_Count; $i++) {
            $aAryRow = $aSecGrp[$i];
            //extract($aAryRow);
            $sOptList .= '<option value="' . $aAryRow['lst_OptionID'] . '"';
            //        echo "lst_OptionName:".$aAryRow['lst_OptionName']."<br>";
            if ($aAryRow['lst_OptionName'] == $currOpt) {
                $sOptList .= ' selected';
            }
            $sOptList .= '>' . $aAryRow['lst_OptionName'] . "</option>\n";
        }
        $sOptList .= '</select>';

        return $sOptList;
    }

  // Construct the form
    ?>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>" >

    function confirmDeleteField(event) {
      var answer = confirm("<?= gettext('Warning:  By deleting this field, you will irrevokably lose all person data assigned for this field!') ?>")
      if (answer) {
        window.location = href = "PersonCustomFieldsRowOps.php?Field=" + event + "&Action=delete"
        return true;
      }
      event.preventDefault ? event.preventDefault() : event.returnValue = false;
      return false;
    }
  </script>

  <div class="alert alert-warning">
    <i class="fa fa-ban"></i>
    <?= gettext("Warning: Arrow and delete buttons take effect immediately.  Field name changes will be lost if you do not 'Save Changes' before using an up, down, delete or 'add new' button!") ?>
  </div>
  <form method="post" action="PersonCustomFieldsEditor.php" name="PersonCustomFieldsEditor">
<div class="table-responsive">
    <table class="table">

      <?php
        if ($numRows == 0) {
            ?>
       <h2><?= gettext('No custom person fields have been added yet') ?></h2>
            <?php
        } else {
            ?>
        <tr>
          <td colspan="6">
            <?php
            if ($bErrorFlag) {
                echo '<span class="LargeText" style="color: red;"><BR>' . gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') . '</span>';
            } ?>
          </td>
        </tr>

        <tr>
          <th><?= gettext('Type') ?></th>
          <th><?= gettext('Name') ?></th>
          <th><?= gettext('Special option') ?></th>
          <th><?= gettext('Security Option') ?></th>
          <th><?= gettext('Delete') ?></th>
        </tr>

            <?php

            for ($row = 1; $row <= $numRows; $row++) {
                ?>
          <tr>
            <td class="TextColumn">
                <?= $aPropTypes[$aTypeFields[$row]] ?>
            </td>
            <td class="TextColumn" align="center">
              <input type="text" name="<?= $row ?>name"
                     value="<?= htmlentities(stripslashes($aNameFields[$row]), ENT_NOQUOTES, 'UTF-8') ?>" size="35"
                     maxlength="40">
                <?php
                if (array_key_exists($row, $aNameErrors) && $aNameErrors[$row]) {
                    echo '<span style="color: red;"><BR>' . gettext('You must enter a name') . ' </span>';
                } ?>
            </td>
            <td class="TextColumn" align="center">
                  <?php
                    if ($aTypeFields[$row] == 9) {
                        echo '<select name="' . $row . 'special">';
                        echo '<option value="0" selected>Select a group</option>';

                        $sSQL = 'SELECT grp_ID,grp_Name FROM group_grp ORDER BY grp_Name';
                        $rsGroupList = RunQuery($sSQL);

                        while ($aRow = mysqli_fetch_array($rsGroupList)) {
                            extract($aRow);

                            echo '<option value="' . $grp_ID . '"';
                            if ($aSpecialFields[$row] == $grp_ID) {
                                echo ' selected';
                            }
                            echo '>' . $grp_Name . '</option>';
                        }

                        echo '</select>';
                        if ($aSpecialErrors[$row]) {
                            echo '<span style="color: red;"><BR>' . gettext('You must select a group.') . '</span>';
                        }
                    } elseif ($aTypeFields[$row] == 12) {
                        echo "<a href=\"javascript:void(0)\" onClick=\"Newwin=window.open('OptionManager.php?mode=custom&ListID=$aSpecialFields[$row]','Newwin','toolbar=no,status=no,width=400,height=500')\">" . gettext('Edit List Options') . '</a>';
                    } else {
                        echo '&nbsp;';
                    } ?>

            </td>
            <td class="TextColumn" align="center" nowrap>
                  <?php
                    if (isset($aSecurityType[$aFieldSecurity[$row]])) {
                        echo GetSecurityList($aSecurityGrp, $row . 'FieldSec', $aSecurityType[$aFieldSecurity[$row]]);
                    } else {
                        echo GetSecurityList($aSecurityGrp, $row . 'FieldSec');
                    } ?>
            </td>
            <td>
              <input type="button" class="btn btn-danger" value="<?= gettext('Delete') ?>" name="delete"
                     onclick="return confirmDeleteField(<?= "'" . $aFieldFields[$row] . "'" ?>);" )">
            </td>
            <td class="TextColumn" width="5%" nowrap>
                  <?php
                    if ($row != 1) {
                        echo "<a href=\"PersonCustomFieldsRowOps.php?OrderID=$row&Field=" . $aFieldFields[$row] . '&Action=up"><i class="fa fa-arrow-up"></i></a>';
                    }
                    if ($row < $numRows) {
                        echo "<a href=\"PersonCustomFieldsRowOps.php?OrderID=$row&Field=" . $aFieldFields[$row] . '&Action=down"><i class="fa fa-arrow-down"></i></a>';
                    } ?>

            </td>
          </tr>
                <?php
            } ?>

        <tr>
          <td colspan="7">
            <table width="100%">
              <tr>
                <td width="30%"></td>
                <td width="40%" align="center" valign="bottom">
                  <input type="submit" class="btn btn-primary" value="<?= gettext('Save Changes') ?>"
                         Name="SaveChanges">
                </td>
                <td width="30%"></td>
              </tr>
            </table>
          </td>
          <td>
        </tr>
            <?php
        } ?>
      <tr>
        <td colspan="7">
          <hr>
        </td>
      </tr>
      <tr>
        <td colspan="7">
          <table width="100%">
            <tr>
              <td width="15%"></td>
              <td valign="top">
                <div><?= gettext('Type') ?>:</div>
                <?php
                echo '<select name="newFieldType">';

                for ($iOptionID = 1; $iOptionID <= count($aPropTypes); $iOptionID++) {
                    echo '<option value="' . $iOptionID . '"';
                    echo '>' . $aPropTypes[$iOptionID] . '</option>';
                }
                echo '</select>';
                ?><BR>
                <a href="<?= SystemURLs::getSupportURL() ?>"><?= gettext('Help on types..') ?></a>
              </td>
              <td valign="top">
                <div><?= gettext('Name') ?>:</div>
                <input type="text" name="newFieldName" size="30" maxlength="40">
                <?php
                if ($bNewNameError) {
                    echo '<div><span style="color: red;"><BR>' . gettext('You must enter a name') . '</span></div>';
                }
                if ($bDuplicateNameError) {
                    echo '<div><span style="color: red;"><BR>' . gettext('That field name already exists.') . '</span></div>';
                }
                ?>
                &nbsp;
              </td>
              <td valign="top" nowrap>
                <div><?= gettext('Security Option') ?></div>
                <?= GetSecurityList($aSecurityGrp, 'newFieldSec') ?>
              </td>
              <td>
                <input type="submit" class="btn btn-primary" value="<?= gettext('Add New Field') ?>" Name="AddField">
              </td>
              <td width="15%"></td>
            </tr>
          </table>
        </td>
      </tr>

    </table>
</div>
  </form>

</div>

<?php require 'Include/Footer.php'; ?>
