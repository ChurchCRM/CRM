<?php
/*******************************************************************************
 *
 *  filename    : CSVExport.php
 *  last change : 2003-01-17
 *  description : options for creating csv file
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
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

	<?php if ($numCustomFields > 0) { ?>
	<td width="20%" valign="top" align="left">
		<h3><?php echo gettext("Custom Fields"); ?></h3>
		<table cellpadding="4" align="left">
		<?php
			// Display the custom fields
			while ($Row = mysql_fetch_array($rsCustomFields)) {
				extract($Row);
				$currentData = trim($aCustomData[$custom_Field]);
				echo "<tr><td class=\"LabelColumn\">" . $custom_Name . "</td>";
				echo "<td class=\"TextColumn\"><input type=\"checkbox\" name=" . $custom_Field . " value=\"1\"></td></tr>";
			}
		?>
		</table>
	</td>
	<?php } ?>

    <td valign="top" align="left">

	<h3><?php echo gettext("Filters"); ?></h3>

	<table cellpadding="4" align="left">
	<tr>
		<td class="LabelColumn"><?php echo gettext("Records to export:"); ?></td>
		<td class="TextColumnWithBottomBorder">
			<select name="Source">
				<option value="filters"><?php echo gettext("Based on filters below.."); ?></option>
				<option value="cart" <?php if ($_GET["Source"] == 'cart') echo "selected";?>><?php echo gettext("People in Cart (filters ignored)"); ?></option>
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
			<tr><td><b><?php echo gettext("From:"); ?>&nbsp;</b></td><td><input id="sell" type="text" name="MembershipDate1" size="11" maxlength="10">
			<input type="image" value="cal" onclick="return showCalendar('sell', 'y-mm-dd');"  src="Images/calendar.gif"></td></tr>
			<tr><td><b><?php echo gettext("To:"); ?>&nbsp;</b></td><td><input id="DateField" type="text" name="MembershipDate2" size="11" maxlength="10" value="<?php echo(date("Y-m-d")); ?>">
			<input type="image" value="cal" onclick="return showCalendar('DateField', 'y-mm-dd');" src="Images/calendar.gif"></td></tr>
			<tr><td>&nbsp;</td><td><div class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></div></td></tr>
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
						<input id="BD1" type="text" name="BirthDate1" size="11" maxlength="10">
						<input type="image" value="cal" onclick="return showCalendar('BD1', 'y-mm-dd');"  src="Images/calendar.gif">
					</td>
				</tr>
				<tr>
					<td>
				  		<b><?php echo gettext("To:"); ?>&nbsp;</b>
					</td>
		        	<td>
						<input id="BD2" type="text" name="BirthDate2" size="11" maxlength="10" value="<?php echo(date("Y-m-d")); ?>">
						<input type="image" value="cal" onclick="return showCalendar('BD2', 'y-mm-dd');"  src="Images/calendar.gif">
					</td>
				</tr>
				<tr><td>&nbsp;</td><td><div class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></div></td></tr>
			</table>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Anniversary Date:"); ?></td>
		<td class="TextColumn">
			<table border=0 cellpadding=0 cellspacing=0>
			<tr><td><b><?php echo gettext("From:"); ?>&nbsp;</b></td><td><input id="AD1" type="text" name="AnniversaryDate1" size="11" maxlength="10">
			<input type="image" value="cal" onclick="return showCalendar('AD1', 'y-mm-dd');"  src="Images/calendar.gif"></td></tr>
			<tr><td><b><?php echo gettext("To:"); ?>&nbsp;</b></td><td><input id="AD2" type="text" name="AnniversaryDate2" size="11" maxlength="10" value="<?php echo(date("Y-m-d")); ?>">
			<input type="image" value="cal" onclick="return showCalendar('AD2', 'y-mm-dd');" src="Images/calendar.gif"></td></tr>
			<tr><td>&nbsp;</td><td><div class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></div></td></tr>
			</table>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Date Entered:"); ?></td>
		<td class="TextColumn">
			<table border=0 cellpadding=0 cellspacing=0>
			<tr><td><b><?php echo gettext("From:"); ?>&nbsp;</b></td><td><input id="ED1" type="text" name="EnterDate1" size="11" maxlength="10">
			<input type="image" value="cal" onclick="return showCalendar('ED1', 'y-mm-dd');"  src="Images/calendar.gif"></td></tr>
			<tr><td><b><?php echo gettext("To:"); ?>&nbsp;</b></td><td><input id="ED2" type="text" name="EnterDate2" size="11" maxlength="10" value="<?php echo(date("Y-m-d")); ?>">
			<input type="image" value="cal" onclick="return showCalendar('ED2', 'y-mm-dd');" src="Images/calendar.gif"></td></tr>
			<tr><td>&nbsp;</td><td><div class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></div></td></tr>
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
		<td><input type="submit" class="icButton" value=<?php echo "\"" . gettext("Create File") . "\""; ?> name="Submit"></td>
	</tr>
	</table>
    </td>
  </tr>
</table>
</form>
<?php
require "Include/Footer.php";
?>
