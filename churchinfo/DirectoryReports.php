<?php
/*******************************************************************************
 *
 *  filename    : DirectoryReports.php
 *  last change : 2003-09-03
 *  description : form to invoke directory report
 *
 *  http://www.infocentral.org/
 *  Copyright 2003 Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
    Redirect("Menu.php");
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("Directory reports");
require "Include/Header.php";

?>

<form method="POST" action="Reports/DirectoryReport.php">

<?php

// Get classifications for the selects
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
$rsClassifications = RunQuery($sSQL);

//Get Family Roles for the drop-down
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence";
$rsFamilyRoles = RunQuery($sSQL);

// Get all the Groups
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Get the list of custom person fields
$sSQL = "SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order";
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysql_num_rows($rsCustomFields);

$aDefaultClasses = explode(',', $sDirClassifications);
$aDirRoleHead = explode(",",$sDirRoleHead);
$aDirRoleSpouse = explode(",",$sDirRoleSpouse);
$aDirRoleChild = explode(",",$sDirRoleChild);

$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
    while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
        $$cfg_name = $cfg_value;
    }
}


?>

<table align="center">
<?php if ($_GET['cartdir'] == null)
{
?>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Select classifications to include"); ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?php echo gettext("Use Ctrl Key to select multiple"); ?></div>
            <select name="sDirClassifications[]" size="5" multiple>
            <option value="0">Unassigned</option>
            <?php
                while ($aRow = mysql_fetch_array($rsClassifications)) {
                    extract($aRow);
                    echo "<option value=\"" . $lst_OptionID . "\"";
                    if (in_array($lst_OptionID,$aDefaultClasses)) echo " selected";
                    echo ">" . $lst_OptionName . "</option>";
                }
            ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Group Membership:"); ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?php echo gettext("Use Ctrl Key to select multiple"); ?></div>
            <select name="GroupID[]" size="5" multiple>
                <?php
                while ($aRow = mysql_fetch_array($rsGroups))
                {
                    extract($aRow);
                    echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
                }
                ?>
            </select>
        </td>
    </tr>
    
<?php
}
?>
    
    <tr>
        <td class="LabelColumn"><?php echo gettext("Which role is the head of household?"); ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?php echo gettext("Use Ctrl Key to select multiple"); ?></div>
            <select name="sDirRoleHead[]" size="5" multiple>
            <?php
                while ($aRow = mysql_fetch_array($rsFamilyRoles)) {
                    extract($aRow);
                    echo "<option value=\"" . $lst_OptionID . "\"";
                    if (in_array($lst_OptionID, $aDirRoleHead)) echo " selected";
                    echo ">" . $lst_OptionName . "</option>";
                }
            ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Which role is the spouse?"); ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?php echo gettext("Use Ctrl Key to select multiple"); ?></div>
            <select name="sDirRoleSpouse[]" size="5" multiple>
            <?php
                mysql_data_seek($rsFamilyRoles,0);
                while ($aRow = mysql_fetch_array($rsFamilyRoles)) {
                    extract($aRow);
                    echo "<option value=\"" . $lst_OptionID . "\"";
                    if (in_array($lst_OptionID, $aDirRoleSpouse)) echo " selected";
                    echo ">" . $lst_OptionName . "</option>";
                }
            ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Which role is a child?"); ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?php echo gettext("Use Ctrl Key to select multiple"); ?></div>
            <select name="sDirRoleChild[]" size="5" multiple>
            <?php
                mysql_data_seek($rsFamilyRoles,0);
                while ($aRow = mysql_fetch_array($rsFamilyRoles)) {
                    extract($aRow);
                    echo "<option value=\"" . $lst_OptionID . "\"";
                    if (in_array($lst_OptionID, $aDirRoleChild)) echo " selected";
                    echo ">" . $lst_OptionName . "</option>";
                }
            ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Information to Include:"); ?></td>
        <td class="TextColumn">
            <input type="checkbox" Name="bDirAddress" value="1" checked><?php echo gettext("Address");?><br>
            <input type="checkbox" Name="bDirWedding" value="1" checked><?php echo gettext("Wedding Date");?><br>
            <input type="checkbox" Name="bDirBirthday" value="1" checked><?php echo gettext("Birthday");?><br>

            <input type="checkbox" Name="bDirFamilyPhone" value="1" checked><?php echo gettext("Family Home Phone");?><br>
            <input type="checkbox" Name="bDirFamilyWork" value="1" checked><?php echo gettext("Family Work Phone");?><br>
            <input type="checkbox" Name="bDirFamilyCell" value="1" checked><?php echo gettext("Family Cell Phone");?><br>
            <input type="checkbox" Name="bDirFamilyEmail" value="1" checked><?php echo gettext("Family Email");?><br>

            <input type="checkbox" Name="bDirPersonalPhone" value="1" checked><?php echo gettext("Personal Home Phone");?><br>
            <input type="checkbox" Name="bDirPersonalWork" value="1" checked><?php echo gettext("Personal Work Phone");?><br>
            <input type="checkbox" Name="bDirPersonalCell" value="1" checked><?php echo gettext("Personal Cell Phone");?><br>
            <input type="checkbox" Name="bDirPersonalEmail" value="1" checked><?php echo gettext("Personal Email");?><br>
            <input type="checkbox" Name="bDirPersonalWorkEmail" value="1" checked><?php echo gettext("Personal Work/Other Email");?><br>
            <input type="checkbox" Name="bDirPhoto" value="1" checked><?php echo gettext("Photos");?><br>
         <?php 
         if ($numCustomFields > 0) {
            while ( $rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_ASSOC) ){ ?>
            <input type="checkbox" Name="bCustom<?php echo $rowCustomField['custom_Order'];?>" value="1" checked><?php echo $rowCustomField['custom_Name'];?><br>
         <?php
            }
         }
         ?>

        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Title page:"); ?></td>
        <td class="TextColumn">
            <table>
                <tr>
                    <td><?php echo gettext("Use Title Page"); ?></td>
                    <td><input type="checkbox" Name="bDirUseTitlePage" value="1"></td>
                </tr>
                <tr>
                    <td><?php echo gettext("Church Name"); ?></td>
                    <td><input type="text" Name="sChurchName" value="<?php echo $sChurchName;?>"></td>
                </tr>
                <tr>
                    <td><?php echo gettext("Address"); ?></td>
                    <td><input type="text" Name="sChurchAddress" value="<?php echo $sChurchAddress;?>"></td>
                </tr>
                <tr>
                    <td><?php echo gettext("City"); ?></td>
                    <td><input type="text" Name="sChurchCity" value="<?php echo $sChurchCity;?>"></td>
                </tr>
                <tr>
                    <td><?php echo gettext("State"); ?></td>
                    <td><input type="text" Name="sChurchState" value="<?php echo $sChurchState;?>"></td>
                </tr>
                <tr>
                    <td><?php echo gettext("Zip"); ?></td>
                    <td><input type="text" Name="sChurchZip" value="<?php echo $sChurchZip;?>"></td>
                </tr>
                <tr>
                    <td><?php echo gettext("Phone"); ?></td>
                    <td><input type="text" Name="sChurchPhone" value="<?php echo $sChurchPhone;?>"></td>
                </tr>
                <tr>
                    <td><?php echo gettext("Disclaimer"); ?></td>
                    <td><textarea Name="sDirectoryDisclaimer" cols="35" rows="4"><?php echo "$sDirectoryDisclaimer1 $sDirectoryDisclaimer2";?></textarea></td>
                </tr>

            </table>
        </td>
    </tr>


</table>

<?php if ($_GET['cartdir'] != null) echo '<input type="hidden" name="cartdir" value="M">'; ?>


<p align="center">
<BR>
<input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Create Directory") . '"'; ?>>
<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='Menu.php';">
</p>
</form>

<?php
require "Include/Footer.php";
?>
