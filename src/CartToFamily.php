<?php

/*******************************************************************************
 *
 *  filename    : CartToFamily.php
 *  last change : 2003-10-09
 *  description : Add cart records to a family
 *
 *  https://churchcrm.io/
 *  Copyright 2003 Chris Gebhardt
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have add records permission
if (!AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

// Was the form submitted?
if (isset($_POST['Submit']) && count($_SESSION['aPeopleCart']) > 0) {
    // Get the FamilyID
    $iFamilyID = InputUtils::legacyFilterInput($_POST['FamilyID'], 'int');

    // Are we creating a new family
    if ($iFamilyID === 0) {
        $sFamilyName = InputUtils::legacyFilterInput($_POST['FamilyName']);

        $dWeddingDate = InputUtils::legacyFilterInput($_POST['WeddingDate']);
        if (strlen($dWeddingDate) === 0) {
            $dWeddingDate = null;
        }

        $iPersonAddress = InputUtils::legacyFilterInput($_POST['PersonAddress']);

        if ($iPersonAddress != 0) {
            $sSQL = 'SELECT * FROM person_per WHERE per_ID = ' . $iPersonAddress;
            $rsPerson = RunQuery($sSQL);
            extract(mysqli_fetch_array($rsPerson));
        }

        SelectWhichAddress($sAddress1, $sAddress2, InputUtils::legacyFilterInput($_POST['Address1']), InputUtils::legacyFilterInput($_POST['Address2']), $per_Address1, $per_Address2, false);
        $sCity = SelectWhichInfo(InputUtils::legacyFilterInput($_POST['City']), $per_City);
        $sZip = SelectWhichInfo(InputUtils::legacyFilterInput($_POST['Zip']), $per_Zip);
        $sCountry = SelectWhichInfo(InputUtils::legacyFilterInput($_POST['Country']), $per_Country);

        if ($sCountry == 'United States' || $sCountry == 'Canada') {
            $sState = InputUtils::legacyFilterInput($_POST['State']);
        } else {
            $sState = InputUtils::legacyFilterInput($_POST['StateTextbox']);
        }
        $sState = SelectWhichInfo($sState, $per_State);

        // Get and format any phone data from the form.
        $sHomePhone = InputUtils::legacyFilterInput($_POST['HomePhone']);
        $sWorkPhone = InputUtils::legacyFilterInput($_POST['WorkPhone']);
        $sCellPhone = InputUtils::legacyFilterInput($_POST['CellPhone']);
        if (!isset($_POST['NoFormat_HomePhone'])) {
            $sHomePhone = CollapsePhoneNumber($sHomePhone, $sCountry);
        }
        if (!isset($_POST['NoFormat_WorkPhone'])) {
            $sWorkPhone = CollapsePhoneNumber($sWorkPhone, $sCountry);
        }
        if (!isset($_POST['NoFormat_CellPhone'])) {
            $sCellPhone = CollapsePhoneNumber($sCellPhone, $sCountry);
        }

        $sHomePhone = SelectWhichInfo($sHomePhone, $per_HomePhone);
        $sWorkPhone = SelectWhichInfo($sWorkPhone, $per_WorkPhone);
        $sCellPhone = SelectWhichInfo($sCellPhone, $per_CellPhone);
        $sEmail = SelectWhichInfo(InputUtils::legacyFilterInput($_POST['Email']), $per_Email);

        if (strlen($sFamilyName) === 0) {
            $sError = '<p class="callout callout-warning" align="center" style="color:red;">' . gettext('No family name entered!') . '</p>';
            $bError = true;
        } else {
            $familyValues = [
                'fam_Name' => $sFamilyName,
                'fam_Address1' => $sAddress1,
                'fam_Address2' => $sAddress2,
                'fam_City' => $sCity,
                'fam_State' => $sState,
                'fam_Zip' => $sZip,
                'fam_Country' => $sCountry,
                'fam_HomePhone' => $sHomePhone,
                'fam_WorkPhone' =>  $sWorkPhone,
                'fam_CellPhone' => $sCellPhone,
                'fam_Email' => $sEmail,
                'fam_WeddingDate' => $dWeddingDate,
                'fam_DateEntered' => date('YmdHis'),
                'fam_EnteredBy' => AuthenticationManager::getCurrentUser()->getId(),
            ];
            $familyValues = array_filter($familyValues, fn($var) => !empty($var));
            $familyValues = array_map(fn($var) => '"' . mysqli_real_escape_string($cnInfoCentral, $var) . '"', $familyValues);

            $sSQL = 'INSERT INTO family_fam (' . implode(',', array_keys($familyValues)) . ') VALUES (' . implode(',', array_values($familyValues)) . ')';
            RunQuery($sSQL);

            //Get the key back
            $sSQL = 'SELECT MAX(fam_ID) AS iFamilyID FROM family_fam';
            $rsLastEntry = RunQuery($sSQL);
            $famIdArray = mysqli_fetch_array($rsLastEntry);
            $iFamilyID = $famIdArray['iFamilyID'];
        }
    }

    if (!$bError) {
        // Loop through the cart array
        $iCount = 0;
        foreach ($_SESSION['aPeopleCart'] as $element) {
            $iPersonID = $element;
            $sSQL = 'SELECT per_fam_ID FROM person_per WHERE per_ID = ' . $iPersonID;
            $rsPerson = RunQuery($sSQL);
            $perFamIdArray = mysqli_fetch_array($rsPerson);
            $per_fam_ID = $perFamIdArray['per_fam_ID'];

            // Make sure they are not already in a family
            if ($per_fam_ID != 0) {
                throw new \Exception(sprintf('person (%d) is already assigned to family (%s)', $iPersonID, $per_fam_ID));
            }
            $iFamilyRoleID = InputUtils::legacyFilterInput($_POST['role' . $iPersonID], 'int');
            if (empty($iFamilyRoleID)) {
                throw new \Exception(sprintf('person (%d) does not have role in post body', $iPersonID));
            }

            $sSQL = 'UPDATE person_per SET per_fam_ID = ' . $iFamilyID . ', per_fmr_ID = ' . $iFamilyRoleID . ' WHERE per_ID = ' . $iPersonID;
            RunQuery($sSQL);
            $iCount++;
        }

        $sGlobalMessage = $iCount . ' records(s) successfully added to selected Family.';

        RedirectUtils::redirect('v2/family/' . $iFamilyID . '&Action=EmptyCart');
    }
}

// Set the page title and include HTML header
$sPageTitle = gettext('Add Cart to Family');
require 'Include/Header.php';

echo $sError;
?>
<div class="card">
<form method="post">

        <?php
        if (count($_SESSION['aPeopleCart']) > 0) {
            // Get all the families
            $sSQL = 'SELECT fam_Name, fam_ID FROM family_fam ORDER BY fam_Name';
            $rsFamilies = RunQuery($sSQL);

            // Get the family roles
            $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence';
            $rsFamilyRoles = RunQuery($sSQL);

            $sRoleOptionsHTML = '';
            while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
                $sRoleOptionsHTML .= sprintf(
                    '<option value="%s">%s</option>',
                    $aRow['lst_OptionID'],
                    $aRow['lst_OptionName']
                );
            }

            $cartString = convertCartToString($_SESSION['aPeopleCart']);
            $sSQL = <<<SQL
SELECT
    per_Title,
    per_FirstName,
    per_MiddleName,
    per_LastName,
    per_Suffix,
    per_fam_ID,
    per_ID
FROM person_per
WHERE per_ID IN ($cartString)
ORDER BY per_LastName
SQL;
            $rsCartItems = RunQuery($sSQL);

            echo "<table class='table'>";
            echo '<tr>';
            echo '<td>&nbsp;</td>';
            echo '<td><b>' . gettext('Name') . '</b></td>';
            echo '<td align="center"><b>' . gettext('Assign Role') . '</b></td>';

            $count = 1;
            while ($aRow = mysqli_fetch_array($rsCartItems)) {
                $sRowClass = AlternateRowStyle($sRowClass);

                extract($aRow);

                echo '<tr class="' . $sRowClass . '">';
                echo '<td align="center">' . $count++ . '</td>';
                echo "<td><img src='" . SystemURLs::getRootPath() . '/api/person/' . $per_ID . "/thumbnail' class='direct-chat-img'> &nbsp <a href=\"PersonView.php?PersonID=" . $per_ID . '">' . FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 1) . '</a></td>';

                echo '<td align="center">';
                if ($per_fam_ID == 0) {
                    echo '<select name="role' . $per_ID . '">' . $sRoleOptionsHTML . '</select>';
                } else {
                    echo gettext('Already in a family');
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</table>'; ?>
    </div>
    <div class="card">
<div class="table-responsive">
<table align="center" class="table table-hover">
    <tr>
        <td class="LabelColumn"><?= gettext('Add to Family') ?>:</td>
        <td class="TextColumn">
                    <?php
                    // Create the family select drop-down
                    echo '<select name="FamilyID">';
                    echo '<option value="0">' . gettext('Create new family') . '</option>';
                    while ($aRow = mysqli_fetch_array($rsFamilies)) {
                        echo sprintf('<option value="%s">%s</option>', $aRow['fam_ID'], $aRow['fam_Name']);
                    }
                    echo '</select>'; ?>
        </td>
    </tr>

    <tr>
        <td></td>
        <td><p class="MediumLargeText"><?= gettext('If adding a new family, enter data below.') ?></p></td>
    </tr>


    <tr>
        <td class="LabelColumn"><?= gettext('Family Name') ?>:</td>
        <td class="TextColumnWithBottomBorder"><input type="text" Name="FamilyName" value="<?= $sName ?>" maxlength="48"><span style="color: red;"><?= $sNameError ?></span></td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Wedding Date') ?>:</td>
        <td class="TextColumnWithBottomBorder"><input type="text" Name="WeddingDate" value="<?= $dWeddingDate ?>" maxlength="10" id="sel1" size="15"  class="form-control pull-right active date-picker"><span style="color: red;"><?php echo '<BR>' . $sWeddingDateError ?></span></td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Use address/contact data from') ?>:</td>
        <td class="TextColumn">
                    <?php
                    echo '<select name="PersonAddress">';
                    echo '<option value="0">' . gettext('Only the new data below') . '</option>';

                    mysqli_data_seek($rsCartItems, 0);
                    while ($aRow = mysqli_fetch_array($rsCartItems)) {
                        if ($per_fam_ID == 0) {
                            echo sprintf('<option value="%s">%s %s</option>', $aRow['per_ID'], $aRow['per_FirstName'], $aRow['per_LastName']);
                        }
                    }

                    echo '</select>'; ?>
        </td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Address') ?> 1:</td>
        <td class="TextColumn"><input type="text" Name="Address1" value="<?= $sAddress1 ?>" size="50" maxlength="250"></td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Address') ?> 2:</td>
        <td class="TextColumn"><input type="text" Name="Address2" value="<?= $sAddress2 ?>" size="50" maxlength="250"></td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('City') ?>:</td>
        <td class="TextColumn"><input type="text" Name="City" value="<?= $sCity ?>" maxlength="50"></td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('State') ?>:</td>
        <td class="TextColumn">
                    <?php require 'Include/StateDropDown.php'; ?>
            OR
            <input type="text" name="StateTextbox" value="<?php if ($sCountry != 'United States' && $sCountry != 'Canada') {
                echo $sState;
                                                          } ?>" size="20" maxlength="30">
            <BR><?= gettext('(Use the textbox for countries other than US and Canada)') ?>
        </td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Zip')?>:</td>
        <td class="TextColumn">
            <input type="text" Name="Zip" value="<?= $sZip ?>" maxlength="10" size="8">
        </td>

    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Country') ?>:</td>
        <td class="TextColumnWithBottomBorder">
                    <?php require 'Include/CountryDropDown.php' ?>
        </td>
    </tr>

    <tr>
        <td>&nbsp;</td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Home Phone') ?>:</td>
        <td class="TextColumn">
            <input type="text" Name="HomePhone" value="<?= $sHomePhone ?>" size="30" maxlength="30">
            <input type="checkbox" name="NoFormat_HomePhone" value="1" <?php if ($bNoFormat_HomePhone) {
                echo ' checked';
                                                                       } ?>><?= gettext('Do not auto-format') ?>
        </td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Work Phone') ?>:</td>
        <td class="TextColumn">
            <input type="text" name="WorkPhone" value="<?php echo $sWorkPhone ?>" size="30" maxlength="30">
            <input type="checkbox" name="NoFormat_WorkPhone" value="1" <?php if ($bNoFormat_WorkPhone) {
                echo ' checked';
                                                                       } ?>><?= gettext('Do not auto-format') ?>
        </td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Mobile Phone') ?>:</td>
        <td class="TextColumn">
            <input type="text" name="CellPhone" value="<?php echo $sCellPhone ?>" size="30" maxlength="30">
            <input type="checkbox" name="NoFormat_CellPhone" value="1" <?php if ($bNoFormat_CellPhone) {
                echo ' checked';
                                                                       } ?>><?= gettext('Do not auto-format') ?>
        </td>
    </tr>

    <tr>
        <td class="LabelColumn"><?= gettext('Email') ?>:</td>
        <td class="TextColumnWithBottomBorder"><input type="text" Name="Email" value="<?= $sEmail ?>" size="30" maxlength="50"></td>
    </tr>

</table>
</div>
<p align="center">
<BR>
<input type="submit" class="btn btn-default" name="Submit" value="<?= gettext('Add to Family') ?>">
<BR><BR>
</p>
</form>
            <?php
        } else {
                echo "<p align=\"center\" class='callout callout-warning'>" . gettext('Your cart is empty!') . '</p>';
        }
        ?>
</div>
<?php require 'Include/Footer.php'; ?>
