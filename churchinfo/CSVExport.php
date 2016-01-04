<?php
/*******************************************************************************
 *
 *  filename    : CSVExport.php
 *  description : options for creating csv file
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 *  General Public License for mote details.
 *
 *  http://www.gnu.org/licenses
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// If user does not have CSV Export permission, redirect to the menu.
if (!$bExportCSV) 
{
    Redirect("Menu.php");
    exit;
}

//Get Classifications for the drop-down
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
$rsClassifications = RunQuery($sSQL);

//Get Family Roles for the drop-down
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence";
$rsFamilyRoles = RunQuery($sSQL);

// Get all the Groups
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

$sSQL = "SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order";
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysql_num_rows($rsCustomFields);

$sSQL = "SELECT family_custom_master.* FROM family_custom_master ORDER BY fam_custom_Order";
$rsFamCustomFields = RunQuery($sSQL);
$numFamCustomFields = mysql_num_rows($rsFamCustomFields);

// Get Field Security List Matrix
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence";
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsSecurityGrp))
{
    extract ($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}


// Set the page title and include HTML header
$sPageTitle = gettext("CSV Export");
require "Include/Header.php";

?>

<form method="post" action="CSVCreateFile.php">

<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tr>
    <td width="20%" valign="top" align="left">
        <h3><?php echo gettext("Standard Fields"); ?></h3>
        <table cellpadding="4" align="left">

        <tr>
            <td class="LabelColumn"><?php echo gettext("Last Name:"); ?></td>
            <td class="TextColumn"><?php echo gettext("Required"); ?></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Title:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="Title" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("First Name:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="FirstName" value="1" checked></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Middle Name:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="MiddleName" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Suffix:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="Suffix" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Address1:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="Address1" value="1" checked></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Address2:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="Address2" value="1" checked></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("City:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="City" value="1" checked></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("State:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="State" value="1" checked></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Zip:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="Zip" value="1" checked></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Envelope:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="Envelope" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Country:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="Country" value="1" checked></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Home Phone:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="HomePhone" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Work Phone:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="WorkPhone" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Mobile Phone:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="CellPhone" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Email:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="Email" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Work/Other Email:"); ?></td>
            <td class="TextColumn"><input type="checkbox" name="WorkEmail" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Membership Date:"); ?></td>
            <td class="TextColumnWithBottomBorder"><input type="checkbox" name="MembershipDate" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("* Birth / Anniversary Date:"); ?></td>
            <td class="TextColumnWithBottomBorder"><input type="checkbox" name="BirthdayDate" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("* Age / Years Married:"); ?></td>
            <td class="TextColumnWithBottomBorder"><input type="checkbox" name="Age" value="1"></td>
        </tr>

        <tr>
            <td class="LabelColumn"><?php echo gettext("Family Role:"); ?></td>
            <td class="TextColumnWithBottomBorder"><input type="checkbox" name="PrintFamilyRole" value="1"></td>
        </tr>

        <tr>
            <td colspan="2"><?php echo gettext("* Depends whether using person or family output method"); ?></td>
        </tr>

  </table>
    </td>

    <?php if (($numCustomFields > 0) or ($numFamCustomFields > 0)) {?>
    <td width="20%" valign="top"><table border="0">
    <?php if ($numCustomFields > 0) { ?>
    <tr><td width="100%" valign="top" align="left">
        <h3><?php echo gettext("Custom Person Fields"); ?></h3>
        <table cellpadding="4" align="left">
        <?php
            // Display the custom fields
            while ($Row = mysql_fetch_array($rsCustomFields)) {
                extract($Row);
                if (($aSecurityType[$custom_FieldSec] == 'bAll') or ($_SESSION[$aSecurityType[$custom_FieldSec]]))
                {
                    echo "<tr><td class=\"LabelColumn\">" . $custom_Name . "</td>";
                    echo "<td class=\"TextColumn\"><input type=\"checkbox\" name=" . $custom_Field . " value=\"1\"></td></tr>";
                }
            }
        ?>
        </table>
    </td></tr>
    <?php } ?>

    <?php if ($numFamCustomFields > 0) { ?>
    <tr><td width="100%" valign="top" align="left">
        <h3><?php echo gettext("Custom Family Fields"); ?></h3>
        <table cellpadding="4" align="left">
        <?php
            // Display the family custom fields
            while ($Row = mysql_fetch_array($rsFamCustomFields)) {
                extract($Row);
                if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') or ($_SESSION[$aSecurityType[$fam_custom_FieldSec]]))
                {
                    echo "<tr><td class=\"LabelColumn\">" . $fam_custom_Name . "</td>";
                    echo "<td class=\"TextColumn\"><input type=\"checkbox\" name=" . $fam_custom_Field . " value=\"1\"></td></tr>";
                }
            }
        ?>
        </table>
    </td></tr>
    <?php } ?>
        </table></td>
    <?php } ?>

    <td valign="top" align="left">

    <h3><?php echo gettext("Filters"); ?></h3>

    <table cellpadding="4" align="left">
    <tr>
        <td class="LabelColumn"><?php echo gettext("Records to export:"); ?></td>
        <td class="TextColumnWithBottomBorder">
            <select name="Source">
                <option value="filters"><?php echo gettext("Based on filters below.."); ?></option>
                <option value="cart" <?php if (array_key_exists ("Source", $_GET) and $_GET["Source"] == 'cart') echo "selected";?>><?php echo gettext("People in Cart (filters ignored)"); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Classification:"); ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?php echo gettext("Use Ctrl Key to select multiple"); ?></div>
            <select name="Classification[]" size="5" multiple>
                <?php
                while ($aRow =mysql_fetch_array($rsClassifications))
                {
                    extract($aRow);
                    ?>
                    <option value="<?php echo $lst_OptionID ?>"><?php echo $lst_OptionName ?></option>
                    <?php
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Family Role:"); ?></td>
        <td class="TextColumn">
            <div class="SmallText"><?php echo gettext("Use Ctrl Key to select multiple"); ?></div>
            <select name="FamilyRole[]" size="5" multiple>
                <?php
                while ($aRow = mysql_fetch_array($rsFamilyRoles))
                {
                    extract($aRow);
                    ?>
                    <option value="<?php echo $lst_OptionID ?>"><?php echo $lst_OptionName ?></option>
                    <?php
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Gender:"); ?></td>
        <td class="TextColumn">
            <select name="Gender">
                <option value="0"><?php echo gettext("Don't Filter"); ?></option>
                <option value="1"><?php echo gettext("Male"); ?></option>
                <option value="2"><?php echo gettext("Female"); ?></option>
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
    <tr>
        <td class="LabelColumn"><?php echo gettext("Membership Date:"); ?></td>
        <td class="TextColumn">
            <table border=0 cellpadding=0 cellspacing=0>
            <tr><td><b>
                <?php echo gettext("From:"); ?>&nbsp;</b></td><td><input id="MembershipDate1" type="text" name="MembershipDate1" size="11" maxlength="10">
            </td></tr>
            <tr><td><b>
                <?php echo gettext("To:"); ?>&nbsp;</b></td><td><input id="MembershipDate2" type="text" name="MembershipDate2" size="11" maxlength="10" value="<?php echo(date("Y-m-d")); ?>">
            </td></tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Birthday Date:"); ?></td>
        <td class="TextColumn">
            <table border=0 cellpadding=0 cellspacing=0>
                <tr>
                    <td>
                        <b><?php echo gettext("From:"); ?>&nbsp;</b>
                    </td>
                    <td>
                        <input type="text" name="BirthDate1" size="11" maxlength="10" id="BirthdayDate1">
                    </td>
                </tr>
                <tr>
                    <td>
                        <b><?php echo gettext("To:"); ?>&nbsp;</b>
                    </td>
                    <td>
                        <input type="text" name="BirthDate2" size="11" maxlength="10" value="<?php echo(date("Y-m-d")); ?>"  id="BirthdayDate2">
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Anniversary Date:"); ?></td>
        <td class="TextColumn">
            <table border=0 cellpadding=0 cellspacing=0>
            <tr><td><b>
                <?php echo gettext("From:"); ?>&nbsp;</b></td><td><input type="text" name="AnniversaryDate1" size="11" maxlength="10" id="AnniversaryDate1">
            </td></tr>
            <tr><td><b>
                <?php echo gettext("To:"); ?>&nbsp;</b></td><td><input type="text" name="AnniversaryDate2" size="11" maxlength="10" value="<?php echo(date("Y-m-d")); ?>" id="AnniversaryDate2">
            </td></tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Date Entered:"); ?></td>
        <td class="TextColumn">
            <table border=0 cellpadding=0 cellspacing=0>
            <tr><td><b>
                <?php echo gettext("From:"); ?>&nbsp;</b></td><td><input id="EnterDate1" type="text" name="EnterDate1" size="11" maxlength="10">
            </td></tr>
            <tr><td><b>
                <?php echo gettext("To:"); ?>&nbsp;</b></td><td><input id="EnterDate2" type="text" name="EnterDate2" size="11" maxlength="10" value="<?php echo(date("Y-m-d")); ?>">
            </td></tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="LabelColumn"><?php echo gettext("Output Method:"); ?></td>
        <td class="TextColumnWithBottomBorder">
            <select name="Format">
                <option value="Default"><?php echo gettext("CSV Individual Records"); ?></option>
                <option value="Rollup"><?php echo gettext("CSV Combine Families"); ?></option>
                <option value="AddToCart"><?php echo gettext("Add Individuals to Cart"); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td colspan=2 align="center"><input type="checkbox" name="SkipIncompleteAddr" value="1"><?php echo gettext("Skip records with incomplete mail address"); ?></td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td><input type="submit" class="btn" value=<?php echo "\"" . gettext("Create File") . "\""; ?> name="Submit"></td>
    </tr>
    </table>
    </td>
  </tr>
</table>
</form>
<script>
$("#MembershipDate1").datepicker({format:'yyyy-mm-dd'});
$("#MembershipDate2").datepicker({format:'yyyy-mm-dd'});
$("#BirthdayDate1").datepicker({format:'yyyy-mm-dd'});
$("#BirthdayDate2").datepicker({format:'yyyy-mm-dd'});
$("#AnniversaryDate1").datepicker({format:'yyyy-mm-dd'});
$("#AnniversaryDate2").datepicker({format:'yyyy-mm-dd'});
$("#EnterDate1").datepicker({format:'yyyy-mm-dd'});
$("#EnterDate2").datepicker({format:'yyyy-mm-dd'});
</script>
<?php
require "Include/Footer.php";
?>
