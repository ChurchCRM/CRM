<?php

/*******************************************************************************
 *
 *  filename    : DirectoryReports.php
 *  last change : 2003-09-03
 *  description : form to invoke directory report
 *
 *  https://churchcrm.io/
 *  Copyright 2003 Chris Gebhardt
 *  Copyright 2004-2012 Michael Wilt
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\RedirectUtils;

// Check for Create Directory user permission.
if (!AuthenticationManager::getCurrentUser()->isCreateDirectoryEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext('Directory reports');
require 'Include/Header.php';

?>
<div class="card card-body">
<form method="POST" action="Reports/DirectoryReport.php">

<?php

// Get classifications for the selects
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
$rsClassifications = RunQuery($sSQL);

//Get Family Roles for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence';
$rsFamilyRoles = RunQuery($sSQL);

// Get all the Groups
$sSQL = 'SELECT * FROM group_grp ORDER BY grp_Name';
$rsGroups = RunQuery($sSQL);

// Get the list of custom person fields
$sSQL = 'SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysqli_num_rows($rsCustomFields);

$aDefaultClasses = explode(',', SystemConfig::getValue('sDirClassifications'));
$aDirRoleHead = explode(',', SystemConfig::getValue('sDirRoleHead'));
$aDirRoleSpouse = explode(',', SystemConfig::getValue('sDirRoleSpouse'));
$aDirRoleChild = explode(',', SystemConfig::getValue('sDirRoleChild'));

// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

?>
<div class="table-responsive">
<table class="table" align="center" class="table">
<?php if (!array_key_exists('cartdir', $_GET)) {
    ?>
    <tr>
        <td class="LabelColumn"><?= gettext('Exclude Inactive Families') ?></td>
        <td><input type="checkbox" Name="bExcludeInactive" value="1" checked></td>
    </tr>
    <tr>
        <td class="LabelColumn"><?= gettext('Select classifications to include') ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?= gettext('Use Ctrl Key to select multiple') ?></div>
            <select name="sDirClassifications[]" size="5" multiple>
            <option value="0"><?= gettext("Unassigned") ?></option>
            <?php
            while ($aRow = mysqli_fetch_array($rsClassifications)) {
                extract($aRow);
                echo '<option value="' . $lst_OptionID . '"';
                if (in_array($lst_OptionID, $aDefaultClasses)) {
                    echo ' selected';
                }
                echo '>' . gettext($lst_OptionName) . '</option>';
            } ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?= gettext('Group Membership') ?>:</td>
        <td class="TextColumn">
            <div class="SmallText"><?= gettext('Use Ctrl Key to select multiple') ?></div>
            <select name="GroupID[]" size="5" multiple>
                <?php
                while ($aRow = mysqli_fetch_array($rsGroups)) {
                    extract($aRow);
                    echo '<option value="' . $grp_ID . '">' . $grp_Name . '</option>';
                } ?>
            </select>
        </td>
    </tr>

    <?php
}
?>

    <tr>
        <td class="LabelColumn"><?= gettext('Which role is the head of household?') ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?= gettext('Use Ctrl Key to select multiple') ?></div>
            <select name="sDirRoleHead[]" size="5" multiple>
            <?php
            while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
                extract($aRow);
                echo '<option value="' . $lst_OptionID . '"';
                if (in_array($lst_OptionID, $aDirRoleHead)) {
                    echo ' selected';
                }
                echo '>' . gettext($lst_OptionName) . '</option>';
            }
            ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?= gettext('Which role is the spouse?') ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?= gettext('Use Ctrl Key to select multiple') ?></div>
            <select name="sDirRoleSpouse[]" size="5" multiple>
            <?php
                mysqli_data_seek($rsFamilyRoles, 0);
            while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
                extract($aRow);
                echo '<option value="' . $lst_OptionID . '"';
                if (in_array($lst_OptionID, $aDirRoleSpouse)) {
                    echo ' selected';
                }
                echo '>' . gettext($lst_OptionName) . '</option>';
            }
            ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?= gettext('Which role is a child?') ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?= gettext('Use Ctrl Key to select multiple') ?></div>
            <select name="sDirRoleChild[]" size="5" multiple>
            <?php
                mysqli_data_seek($rsFamilyRoles, 0);
            while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
                extract($aRow);
                echo '<option value="' . $lst_OptionID . '"';
                if (in_array($lst_OptionID, $aDirRoleChild)) {
                    echo ' selected';
                }
                echo '>' . gettext($lst_OptionName) . '</option>';
            }
            ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?= gettext('Information to Include') ?>:</td>
        <td class="TextColumn">
            <input type="checkbox" Name="bDirAddress" value="1" checked><?= gettext('Address') ?><br>
            <input type="checkbox" Name="bDirWedding" value="1" checked><?= gettext('Wedding Date') ?><br>
            <input type="checkbox" Name="bDirBirthday" value="1" checked><?= gettext('Birthday') ?><br>

            <input type="checkbox" Name="bDirFamilyPhone" value="1" checked><?= gettext('Family Home Phone') ?><br>
            <input type="checkbox" Name="bDirFamilyWork" value="1" checked><?= gettext('Family Work Phone') ?><br>
            <input type="checkbox" Name="bDirFamilyCell" value="1" checked><?= gettext('Family Cell Phone') ?><br>
            <input type="checkbox" Name="bDirFamilyEmail" value="1" checked><?= gettext('Family Email') ?><br>

            <input type="checkbox" Name="bDirPersonalPhone" value="1" checked><?= gettext('Personal Home Phone') ?><br>
            <input type="checkbox" Name="bDirPersonalWork" value="1" checked><?= gettext('Personal Work Phone') ?><br>
            <input type="checkbox" Name="bDirPersonalCell" value="1" checked><?= gettext('Personal Cell Phone') ?><br>
            <input type="checkbox" Name="bDirPersonalEmail" value="1" checked><?= gettext('Personal Email') ?><br>
            <input type="checkbox" Name="bDirPersonalWorkEmail" value="1" checked><?= gettext('Personal Work/Other Email') ?><br>
            <input type="checkbox" Name="bDirPhoto" value="1" checked><?= gettext('Photos') ?><br>
         <?php
            if ($numCustomFields > 0) {
                while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_ASSOC)) {
                    if (($aSecurityType[$rowCustomField['custom_FieldSec']] == 'bAll') || ($_SESSION[$aSecurityType[$rowCustomField['custom_FieldSec']]])) {
                        ?>
                    <input type="checkbox" Name="bCustom<?= $rowCustomField['custom_Order'] ?>" value="1" checked><?= $rowCustomField['custom_Name'] ?><br>
                        <?php
                    }
                }
            }
            ?>

        </td>
    </tr>
    <tr>
     <td class="LabelColumn"><?= gettext('Number of Columns') ?>:</td>
     <td class="TextColumn">
            <input type="radio" Name="NumCols" value=1>1 col<br>
            <input type="radio" Name="NumCols" value=2 checked>2 cols<br>
            <input type="radio" Name="NumCols" value=3>3 cols<br>
    </td>
    </tr>
    <tr>
     <td class="LabelColumn"><?= gettext('Paper Size') ?>:</td>
     <td class="TextColumn">
            <input type="radio" name="PageSize" value="letter" checked>Letter (8.5x11)<br>
            <input type="radio" name="PageSize" value="legal">Legal (8.5x14)<br>
            <input type="radio" name="PageSize" value="a4">A4
    </td>
    </tr>
    <tr>
     <td class="LabelColumn"><?= gettext('Font Size') ?>:</td>
     <td class="TextColumn">
        <table>
        <tr>
            <td><input type="radio" Name="FSize" value=6>6<br>
            <input type="radio" Name="FSize" value=8>8<br>
            <input type="radio" Name="FSize" value=10 checked>10<br></td>

            <td><input type="radio" Name="FSize" value=12>12<br>
            <input type="radio" Name="FSize" value=14>14<br>
            <input type="radio" Name="FSize" value=16>16<br></td>
        </tr>
        </table>
    </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?= gettext('Title page') ?>:</td>
        <td class="TextColumn">
            <table>
                <tr>
                    <td><?= gettext('Use Title Page') ?></td>
                    <td><input type="checkbox" Name="bDirUseTitlePage" value="1"></td>
                </tr>
                <tr>
                    <td><?= gettext('Church Name') ?></td>
                    <td><input type="text" Name="sChurchName" value="<?= SystemConfig::getValue('sChurchName') ?>"></td>
                </tr>
                <tr>
                    <td><?= gettext('Address') ?></td>
                    <td><input type="text" Name="sChurchAddress" value="<?= SystemConfig::getValue('sChurchAddress') ?>"></td>
                </tr>
                <tr>
                    <td><?= gettext('City') ?></td>
                    <td><input type="text" Name="sChurchCity" value="<?= SystemConfig::getValue('sChurchCity') ?>"></td>
                </tr>
                <tr>
                    <td><?= gettext('State') ?></td>
                    <td><input type="text" Name="sChurchState" value="<?= SystemConfig::getValue('sChurchState') ?>"></td>
                </tr>
                <tr>
                    <td><?= gettext('Zip') ?></td>
                    <td><input type="text" Name="sChurchZip" value="<?= SystemConfig::getValue('sChurchZip') ?>"></td>
                </tr>
                <tr>
                    <td><?= gettext('Phone') ?></td>
                    <td><input type="text" Name="sChurchPhone" value="<?= SystemConfig::getValue('sChurchPhone') ?>"></td>
                </tr>
                <tr>
                    <td><?= gettext('Disclaimer') ?></td>
                    <td><textarea Name="sDirectoryDisclaimer" cols="35" rows="4"><?= SystemConfig::getValue('sDirectoryDisclaimer1') . ' ' . SystemConfig::getValue('sDirectoryDisclaimer2') ?></textarea></td>
                </tr>

            </table>
        </td>
    </tr>


</table>
</div>

<?php if (array_key_exists('cartdir', $_GET)) {
             echo '<input type="hidden" name="cartdir" value="M">';
} ?>


<p align="center">
<BR>
<input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Create Directory') ?>">
<input type="button" class="btn btn-default" name="Cancel" <?= 'value="' . gettext('Cancel') . '"' ?> onclick="javascript:document.location='Menu.php';">
</p>
</form>
</div>
<?php require 'Include/Footer.php' ?>
