<?php
/*******************************************************************************
 *
 *  filename    : GroupView.php
 *  last change : 2003-04-15
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

//Set the page title
$sPageTitle = gettext("Group View");

//Get the GroupID out of the querystring
$iGroupID = FilterInput($_GET["GroupID"],'int');

//Do they want to add this group to their cart?
if ($_GET["Action"] == "AddGroupToCart")
{
	//Get all the members of this group
	$sSQL = "SELECT per_ID FROM person_per, person2group2role_p2g2r WHERE per_ID = p2g2r_per_ID AND p2g2r_grp_ID = " . $iGroupID;
	$rsGroupMembers = RunQuery($sSQL);

	//Loop through the recordset
	while ($aRow = mysql_fetch_array($rsGroupMembers))
	{
		extract($aRow);

		//Add each person to the cart
		AddToPeopleCart($per_ID);
	}
}

//Get the data on this group
$sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
$aGroupData = mysql_fetch_array(RunQuery($sSQL));
extract($aGroupData);

//Look up the default role name
$sSQL = "SELECT lst_OptionName FROM list_lst WHERE lst_ID = $grp_RoleListID AND lst_OptionID = " . $grp_DefaultRole;
$aDefaultRole = mysql_fetch_array(RunQuery($sSQL));
$sDefaultRole = $aDefaultRole[0];

//Get the count of members
$sSQL = "SELECT COUNT(*) AS iTotalMembers FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = " . $iGroupID;
$rsTotalMembers = mysql_fetch_array(RunQuery($sSQL));
extract($rsTotalMembers);

//Get the group's type name
if ($grp_Type > 0)
{
	$sSQL = "SELECT lst_OptionName FROM list_lst WHERE lst_ID = 3 AND lst_OptionID = " . $grp_Type;
	$rsGroupType = mysql_fetch_array(RunQuery($sSQL));
	$sGroupType = $rsGroupType[0];
}
else
	$sGroupType = gettext("Undefined");

//Get the Properties assigned to this Group
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
		FROM record2property_r2p
		LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
		LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
		WHERE pro_Class = 'g' AND r2p_record_ID = " . $iGroupID .
		" ORDER BY prt_Name, pro_Name";
$rsAssignedProperties = RunQuery($sSQL);

//Get all the properties
$sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'g' ORDER BY pro_Name";
$rsProperties = RunQuery($sSQL);

// Lookup the Group's Name from GroupID
$sSQL = "SELECT grp_Name FROM group_grp WHERE grp_ID = " . $iGroupID;
$rsGrpName = RunQuery($sSQL);
$aTemp = mysql_fetch_array($rsGrpName);

// Get data for the form as it now exists..
$sSQL = "SELECT * FROM groupprop_master WHERE grp_ID = " . $iGroupID . " ORDER BY prop_ID";
$rsPropList = RunQuery($sSQL);
$numRows = mysql_num_rows($rsPropList);

require "Include/Header.php";

if ($_SESSION['bManageGroups'])
{
	echo "<a class=\"SmallText\" href=\"GroupEditor.php?GroupID=" . $grp_ID . "\">" . gettext("Edit this Group") . "</a> | ";
	echo "<a class=\"SmallText\" href=\"GroupDelete.php?GroupID=" . $grp_ID . "\">" . gettext("Delete this Group") . "</a> | ";
	if ($grp_hasSpecialProps == 'true')
	{
		echo "<a class=\"SmallText\" href=\"GroupPropsFormEditor.php?GroupID=" . $grp_ID . "\">" . gettext("Edit Group-Specific Properties Form") . "</a> | ";
	}
}
echo "<a class=\"SmallText\" href=\"GroupView.php?Action=AddGroupToCart&GroupID=" . $grp_ID . "\">" . gettext("Add Group Members to Cart") . "</a> | ";
echo "<a class=\"SmallText\" href=\"GroupMeeting.php?GroupID=" . $grp_ID . "&Name=" . $grp_Name . "&linkBack=GroupView.php?GroupID=" . $grp_ID . "\">" . gettext("Schedule a meeting") . "</a>";

// Email Group link
// Note: This will email entire group, even if a specific role is currently selected.
$sSQL = "SELECT per_Email, fam_Email
			FROM person_per
			LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
			LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
			LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
		WHERE p2g2r_grp_ID = " . $iGroupID;
$rsEmailList = RunQuery($sSQL);
$sEmailLink = "";
while (list ($per_Email, $fam_Email) = mysql_fetch_row($rsEmailList))
{
	$sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
	if ($sEmail)
	{
		// Add email only if email address is not already in string
		if (!stristr($sEmailLink, $sEmail."," ))
			$sEmailLink .= $sEmail . ",";
	}
}
if ($sEmailLink)
{
	// Add default email if default email has been set and is not already in string
	if ($sToEmailAddress != "myReceiveEmailAddress" && !stristr($sEmailLink, $sToEmailAddress."," ))
		$sEmailLink .= $sToEmailAddress . ",";
	$sEmailLink = substr($sEmailLink,0,-1);	// Remove trailing comma
	// Display link
	echo " | <a class=\"SmallText\" href=\"mailto:".$sEmailLink."\">".gettext("Email Group")."</a>";
	echo " | <a class=\"SmallText\" href=\"mailto:?&bcc=".$sEmailLink."\">".gettext("Email (BCC)")."</a>";
}

?>
<BR><BR>
<table border="0" width="100%" cellspacing="0" cellpadding="5">
<tr>
	<td width="25%" valign="top" align="center">
		<div class="LightShadedBox">
			<b class="LargeText"><?php echo $grp_Name; ?></b>
			<br>
			<?php echo $grp_Description; ?>
			<br><br>
			<table width="98%">
				<tr>
					<td align="center"><div class="TinyShadedBox"><font size="3">
					<?php echo gettext("Total Members:"); ?> <?php echo $iTotalMembers ?>
					<br>
					<?php echo gettext("Type of Group:"); ?> <?php echo $sGroupType ?>
					<br>
					<?php echo gettext("Default Role:"); ?> <?php echo $sDefaultRole ?>
					</font></div></td>
				</tr>
			</table>
		</div>
	</td>
	<td width="75%" valign="top" align="left">

	<b><?php echo gettext("Group-Specific Properties:"); ?></b>

	<?php
	if ($grp_hasSpecialProps == 'true')
	{
		// Create arrays of the properties.
		for ($row = 1; $row <= $numRows; $row++)
		{
			$aRow = mysql_fetch_array($rsPropList, MYSQL_BOTH);
			extract($aRow);

			$aNameFields[$row] = $prop_Name;
			$aDescFields[$row] = $prop_Description;
			$aFieldFields[$row] = $prop_Field;
			$aTypeFields[$row] = $type_ID;
		}

		// Construct the table

		if (!$numRows)
		{
			echo "<p>No member properties have been created</p>";
		}
		else
		{
			?>
			<table width="100%" cellpadding="2" cellspacing="0">
			<tr class="TableHeader">
				<td><?php echo gettext("Type"); ?></td>
				<td><?php echo gettext("Name"); ?></td>
				<td><?php echo gettext("Description"); ?></td>
			</tr>
			<?php

			for ($row=1; $row <= $numRows; $row++)
			{
				$sRowClass = AlternateRowStyle($sRowClass);
				echo "<tr class=\"$sRowClass\">";
				echo "<td>" . $aPropTypes[$aTypeFields[$row]] . "</td>";
				echo "<td>" . $aNameFields[$row] . "</td>";
				echo "<td>" . $aDescFields[$row] . "&nbsp;</td>";
				echo "</tr>";
			}
			echo "</table>";
		}
	}
	else
		echo "<p>" . gettext("Disabled for this group.") . "</p>";

	//Print Assigned Properties
	echo "<br>";
	echo "<b>" . gettext("Assigned Properties:") . "</b>";
	$sAssignedProperties = ",";

	//Was anything returned?
	if (mysql_num_rows($rsAssignedProperties) == 0)
	{
		// No, indicate nothing returned
		echo "<p align\"center\">" . gettext("No property assignments.") . "</p>";
	}
	else
	{
		// Display table of properties
		?>
		<table width="100%" cellpadding="2" cellspacing="0">
		<tr class="TableHeader">
		<td width="15%" valign="top"><b><?php echo gettext("Type"); ?></b>
		<td valign="top"><b><?php echo gettext("Name"); ?></b>
		<td valign="top"><b><?php echo gettext("Value"); ?></td>
		<?php

		if ($_SESSION['bManageGroups'])
		{
			echo "<td valign=\"top\"><b>" . gettext("Edit Value") . "</td>";
			echo "<td valign=\"top\"><b>" . gettext("Remove") . "</td>";
		}
		echo "</tr>";

		$last_pro_prt_ID = "";
		$bIsFirst = true;

		//Loop through the rows
		while ($aRow = mysql_fetch_array($rsAssignedProperties))
		{
			$pro_Prompt = "";
			$r2p_Value = "";

			extract($aRow);

			if ($pro_prt_ID != $last_pro_prt_ID)
			{
				echo "<tr class=\"";
				if ($bIsFirst)
					echo "RowColorB";
				else
					echo "RowColorC";
				echo "\"><td><b>" . $prt_Name . "</b></td>";

				$bIsFirst = false;
				$last_pro_prt_ID = $pro_prt_ID;
				$sRowClass = "RowColorB";
			}
			else
			{
				echo "<tr class=\"" . $sRowClass . "\">";
				echo "<td valign=\"top\">&nbsp;</td>";
			}

			echo "<td valign=\"top\">" . $pro_Name . "&nbsp;</td>";
			echo "<td valign=\"top\">" . $r2p_Value . "&nbsp;</td>";

			if (strlen($pro_Prompt) > 0 && $_SESSION['bManageGroups'])
			{
				echo "<td valign=\"top\"><a href=\"PropertyAssign.php?GroupID=" . $iGroupID . "&PropertyID=" . $pro_ID . "\">" . gettext("Edit Value") . "</a></td>";
			}
			else
			{
				echo "<td>&nbsp;</td>";
			}

			if ($_SESSION['bManageGroups'])
			{
				echo "<td valign=\"top\"><a href=\"PropertyUnassign.php?GroupID=" . $iGroupID . "&PropertyID=" . $pro_ID . "\">" . gettext("Remove") . "</a>";
			}
			else
			{
				echo "<td>&nbsp;</td>";
			}

			echo "</tr>";

			//Alternate the row style
			$sRowClass = AlternateRowStyle($sRowClass);

			$sAssignedProperties .= $pro_ID . ",";
		}

		echo "</table>";
	}

	if ($_SESSION['bManageGroups'])
	{
		echo "<form method=\"post\" action=\"PropertyAssign.php?GroupID=" . $iGroupID . "\">";
		echo "<p class=\"SmallText\" align=\"center\">";
		echo "<span class=\"SmallText\">" . gettext("Assign a New Property:") . "</span>";
		echo "<select name=\"PropertyID\">";

		while ($aRow = mysql_fetch_array($rsProperties))
		{
			extract($aRow);

			//If the property doesn't already exist for this Person, write the <OPTION> tag
			if (strlen(strstr($sAssignedProperties,"," . $pro_ID . ",")) == 0)
			{
				echo "<option value=\"" . $pro_ID . "\">" . $pro_Name . "</option>";
			}

		}

		echo "</select>";
		echo "<input type=\"submit\" class=\"icButton\" value=\"" . gettext("Assign") . "\" name=\"Submit\" style=\"font-size: 8pt;\">";
		echo "</p></form>";
	}
	else
	{
		echo "<br><br><br>";
	}

echo "</td>";
echo "</tr>";
echo "</table>";
echo "<b>" . gettext("Group Members:") . "</b>";
?>

<iframe width="100%" height="475px" frameborder="0" align="left" marginheight="0" marginwidth="0" src="GroupMemberList.php?GroupID=<?php echo $iGroupID; ?>"></iframe>
<?php
require "Include/Footer.php";
?>
