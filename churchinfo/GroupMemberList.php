<?php
/*******************************************************************************
 *
 *  filename    : GroupMemberList.php
 *  last change : 2003-04-30
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Lewis Franklin, Chris Gebhardt
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

//Get the GroupID from the querystring
if (isset($_GET["GroupID"])) $iGroupID = FilterInput($_GET["GroupID"],'int');
if (isset($_GET["Sort"])) $iSort = FilterInput($_GET["Sort"],'int');
if (isset($_GET["Letter"]))	$sLetter = FilterInput($_GET["Letter"],'char',1);
if (isset($_GET["PrintView"])) $bPrintView = FilterInput($_GET["PrintView"],'int');

if (!empty($_GET["ShowGSP"]))
	$bShowGSP = FilterInput($_GET["ShowGSP"],'int');
else
	$bShowGSP = 0;
	
if (isset($_GET["Number"]))
{
	$_SESSION['SearchLimit'] = FilterInput($_GET["Number"],'int');
	$uSQL = "UPDATE user_usr SET usr_SearchLimit = " . $_SESSION['SearchLimit'] . " WHERE usr_per_ID = " . $_SESSION['iUserID'];
	$rsUser = RunQuery($uSQL);
}

//Are we removing someone?
if (isset($_GET["PersonToRemove"]) && $_SESSION['bManageGroups'])
{
	$iRemovedPerson = FilterInput($_GET["PersonToRemove"],'int');
	RemoveFromGroup($iRemovedPerson,$iGroupID);
	Redirect("PersonView.php?PersonID=" . $iRemovedPerson);
}

// Get the group's role list ID
$sSQL = "SELECT grp_RoleListID,grp_hasSpecialProps FROM group_grp WHERE grp_ID =" . $iGroupID;
$aTemp = mysql_fetch_array(RunQuery($sSQL));
$iRoleListID = $aTemp[0];
$bHasSpecialProps = ($aTemp[1] == "true");

// Get the roles
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = " . $iRoleListID . " ORDER BY lst_OptionSequence";
$rsRoles = RunQuery($sSQL);
$numRoles = mysql_num_rows($rsRoles);

//Set Page Break
$iPerPage = $_SESSION['SearchLimit'];

// Main select query
$sSQL = "SELECT per_ID, per_FirstName, per_MiddleName, per_LastName, per_Title, per_Suffix, per_Address1, per_Address2, per_City, per_State, per_Zip, per_HomePhone, per_Country, per_Email, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, fam_HomePhone, fam_Email, lst_OptionName
			FROM person_per
			LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
			LEFT JOIN list_lst ON p2g2r_rle_ID = lst_OptionID AND lst_ID = $iRoleListID
			LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
			LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
		WHERE p2g2r_grp_ID = " . $iGroupID;

if ($sLetter)
	$sSQL .= " AND per_LastName LIKE '" . $sLetter . "%'";

// Filter is not implemented
// else
//	$sSQL .= " AND per_FirstName LIKE '%" . $sFilter . "%' OR per_LastName LIKE '%" . $sFilter . "%'";

if($iSort)
	$sSQL .= " AND lst_OptionSequence = " . $iSort;

$sSQL .= " ORDER BY lst_OptionSequence ASC,per_LastName";

$sSQL_Result = RunQuery($sSQL);
$Total = mysql_num_rows($sSQL_Result);

// Append a LIMIT clause to the SQL statement
if (empty($_GET['Result_Set']))
{
	$Result_Set = 0;
	$sSQL .= " LIMIT $Result_Set, $iPerPage";
}
else
{
	$Result_Set = FilterInput($_GET['Result_Set'],'int');
	$sSQL .= " LIMIT $Result_Set, $iPerPage";
}

// Run The Query With a Limit to get result
$sSQL_Result = RunQuery($sSQL);
$sSQL_Rows = mysql_num_rows($sSQL_Result);

// Run The Full Query to Get First Letters
$sSQL = "SELECT DISTINCT LEFT(per_LastName,1) AS letter FROM person_per
		LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
		LEFT JOIN list_lst ON p2g2r_rle_ID = lst_OptionID AND lst_ID = $iRoleListID
		WHERE p2g2r_grp_ID = " . $iGroupID;
if($iSort)
	$sSQL .= " AND lst_OptionSequence = " . $iSort;
$sSQL .= " ORDER BY letter";

$rsLetters = RunQuery($sSQL);

//Initialize the Row Style
$sRowClass = "RowColorA";
if (!$bPrintView) {
	require "Include/Header-Minimal.php";
} else {
	$sPageTitle = gettext("Group Member List");
	require "Include/Header-Short.php";
}


// Create Filter Links
if (!$bPrintView) {
	echo "<form action=\"GroupMemberList.php\" method=\"get\" name=\"LoginForm\">";
	echo "<input type=\"hidden\" name=\"GroupID\" value=" . $iGroupID . ">";
	echo "<input type=\"hidden\" name=\"ShowGSP\" value=" . $bShowGSP . ">";
	echo "<select class=\"SmallText\" name=\"Sort\">";
	echo "<option value=\"0\"  selected>" . gettext("View All") . "</option>";
	for ($row = 1; $row <= $numRoles; $row++)
	{
		$aRow = mysql_fetch_array($rsRoles, MYSQL_BOTH);
		extract($aRow);
		$aName[$row] = $lst_OptionName;
		$aSeq[$row] = $lst_OptionSequence;
		if ($aSeq[$row] == $iSort)
			$sSortName = $aName[$row];
		echo "<option value=" . $aSeq[$row] . ">" . gettext("Show only") . " " . $aName[$row] . "</option>";
	}
	echo "</select>";
	echo "<input type=\"submit\" class=\"icTinyButton\" value=\"" . gettext("Go") . "\">";
	// Display Filter
	if ($sSortName)
		echo "<font color=red> &nbsp; &nbsp; Currently showing only $sSortName </font>";
	echo "</form>";
	
	// Create Sort Links
	echo "<div align=\"center\">";
	echo "<a href=\"GroupMemberList.php?ShowGSP=$bShowGSP&GroupID=" . $iGroupID;
	if($iSort) echo "&Sort=$iSort";
	echo "\">" . gettext("View All") . "</a>";
	while ($aLetter = mysql_fetch_array($rsLetters))
	{
		echo "&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"GroupMemberList.php?GroupID=" . $iGroupID;
		if($iSort) echo "&Sort=$iSort";
		echo "&Letter=" . $aLetter[0] . "&ShowGSP=$bShowGSP\">" . $aLetter[0] . "</a>";
	}
	echo "</div>";

	echo "<br><div align=\"center\">";
	echo "<form method=\"get\" action=\"GroupMemberList.php\" name=\"ListNumber\">";

	// Create Next / Prev Links and $Result_Set Value
	if ($Total > $iPerPage)
	{
		// Show previous-page link unless we're at the first page
		if ($Result_Set < $Total && $Result_Set > 0)
		{
			$thisLinkResult = $Result_Set - $iPerPage;
			echo "<a href=\"GroupMemberList.php?Result_Set=$thisLinkResult&GroupID=$iGroupID&Sort=$iSort&Letter=$sLetter&ShowGSP=$bShowGSP\">". gettext("Previous Page") . "</A>&nbsp;&nbsp;";
		}

		// Calculate starting and ending Page-Number Links
		$Pages = ceil($Total / $iPerPage);
		$startpage =  (ceil($Result_Set / $iPerPage)) - 6;
		if ($startpage <= 2)
			$startpage = 1;
		$endpage = (ceil($Result_Set / $iPerPage)) + 9;
		if ($endpage >= ($Pages - 1))
			$endpage = $Pages;
		
		// Show Link "1 ..." if startpage does not start at 1
		if ($startpage != 1)
			echo "<a href=\"GroupMemberList.php?Result_Set=0&GroupID=$iGroupID&Sort=$iSort&Letter=$sLetter&ShowGSP=$bShowGSP\">1</a> ... \n";

		// Display page links
		if ($Pages > 1)
		{
			for ($c = $startpage; $c <= $endpage; $c++)
			{
				$b = $c - 1;
				$thisLinkResult = $iPerPage * $b;
				if ($thisLinkResult != $Result_Set)
					echo "&nbsp;&nbsp;<a href=\"GroupMemberList.php?Result_Set=$thisLinkResult&GroupID=$iGroupID&Sort=$iSort&Letter=$sLetter&ShowGSP=$bShowGSP\">$c</a>&nbsp;\n";
				else
					echo "&nbsp;&nbsp;[ " . $c . " ]&nbsp;&nbsp;";
			}
		}

		// Show Link "... xx" if endpage is not the maximum number of pages
		if ($endpage != $Pages)
		{
			$thisLinkResult = ($Pages - 1) * $iPerPage;
			echo " ... <a href=\"GroupMemberList.php?Result_Set=$thisLinkResult&GroupID=$iGroupID&Sort=$iSort&Letter=$sLetter&ShowGSP=$bShowGSP\">$Pages</a>\n";
		}

		// Show next-page link unless we're at the last page
		if ($Result_Set >= 0 && $Result_Set < $Total)
		{
			$thisLinkResult=$Result_Set+$iPerPage;
			if ($thisLinkResult<$Total)
				echo "&nbsp;&nbsp;<a href=\"GroupMemberList.php?Result_Set=$thisLinkResult&GroupID=$iGroupID&Sort=$iSort&Letter=$sLetter&ShowGSP=$bShowGSP\">" . gettext("Next Page") . "</a>";
		}

		// Record number per page Drop-down box
		if($Result_Set > 0)
			echo "<input type=\"hidden\" name=\"Result_Set\" value=\"" . $Result_Set . "\">";
		if(isset($iGroupID))
			echo "<input type=\"hidden\" name=\"GroupID\" value=\"" . $iGroupID . "\">";
		if(isset($sSort))
			echo "<input type=\"hidden\" name=\"Sort\" value=\"" . $iSort . "\">";
		if(isset($sLetter))
			echo "<input type=\"hidden\" name=\"Letter\" value='" . $sLetter . "'\">";
		if(isset($bShowGSP))
			echo "<input type=\"hidden\" name=\"ShowGSP\" value='". $bShowGSP ."'\">";
		if ($_SESSION['SearchLimit'] == "5")
			$sLimit5 = "selected";
		if ($_SESSION['SearchLimit'] == "10")
			$sLimit10 = "selected";
		if ($_SESSION['SearchLimit'] == "20")
			$sLimit20 = "selected";
		if ($_SESSION['SearchLimit'] == "25")
			$sLimit25 = "selected";
		if ($_SESSION['SearchLimit'] == "50")
			$sLimit50 = "selected";
		echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;". gettext("Display:") . "&nbsp;
		<select class=\"SmallText\" name=\"Number\">
			<option value=\"5\" $sLimit5>5</option>
			<option value=\"10\" $sLimit10>10</option>
			<option value=\"20\" $sLimit20>20</option>
			<option value=\"25\" $sLimit25>25</option>
			<option value=\"50\" $sLimit50>50</option>
		</select>&nbsp;
		<input type=\"submit\" class=\"icTinyButton\" value=\"". gettext("Go") ."\">
		</form>";		
				
		echo "</div><div align=\"center\">";
		echo "<a href=\"GroupMemberList.php?PrintView=1&GroupID=$iGroupID&Sort=$iSort&Letter=$sLetter&ShowGSP=$bShowGSP\" target=\"_top\">" . gettext("Print Page") . "</a>";
	}

	if ($bHasSpecialProps)
	{
		if ($bShowGSP)
			echo "&nbsp; | &nbsp;<a href=\"GroupMemberList.php?Result_Set=$Result_Set&ShowGSP=0&GroupID=$iGroupID&Sort=$iSort&Letter=$sLetter\">" . gettext("Hide Group-Specific Properties") . "</a>";
		else
			echo "&nbsp; | &nbsp;<a href=\"GroupMemberList.php?Result_Set=$Result_Set&ShowGSP=1&GroupID=$iGroupID&Sort=$iSort&Letter=$sLetter\">" . gettext("Show Group-Specific Properties") . "</a>";
	}
	echo "</div><br>";

} ?>

<table cellpadding="2" align="left" cellspacing="0" width="100%">
	<tr class="TableHeader">
		<td><?php echo gettext("Name"); ?></td>
		<td><?php echo gettext("Group Role"); ?></td>
		<td><?php echo gettext("Address"); ?></td>
		<td><?php echo gettext("City"); ?></td>
		<td><?php echo gettext("State"); ?></td>
		<td><?php echo gettext("ZIP"); ?></td>
		<td><?php echo gettext("Home Phone"); ?></td>
		<td><?php echo gettext("E-mail"); ?></td>
	</tr>
	<?php
	//Loop through the members
	while ($aRow = mysql_fetch_array($sSQL_Result))
	{
		$per_Title = "";
		$per_FirstName = "";
		$per_MiddleName = "";
		$per_LastName = "";
		$per_Suffix = "";
		$per_Address1 = "";
		$per_Address2 = "";
		$per_City = "";
		$per_State = "";
		$per_Zip = "";
		$per_Country = "";
		$per_HomePhone = "";
		$per_Email = "";
		$fam_Name = "";
		$fam_Address1 = "";
		$fam_Address2 = "";
		$fam_City = "";
		$fam_State = "";
		$fam_Zip = "";
		$fam_Country = "";
		$fam_HomePhone = "";
		$fam_Email = "";

		extract($aRow);

		//Alternate the row color
		$sRowClass = AlternateRowStyle($sRowClass);

		// Assign the values locally, after selecting whether to display the family or person information
		SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, False);
		$sCity = SelectWhichInfo($per_City, $fam_City, False);
		$sState = SelectWhichInfo($per_State, $fam_State, False);
		$sZip = SelectWhichInfo($per_Zip, $fam_Zip, False);
		$sCountry = SelectWhichInfo($per_Country, $fam_Country, False);
		$sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone,$sCountry,$dummy),
						ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), False);
		$sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
		//Display the row
		?>
	<tr class="<?php echo $sRowClass; ?>">
		<td><?php
			if(!$bPrintView)
				echo "<a target=\"_top\" href=\"PersonView.php?PersonID=$per_ID\">" . FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0) . "</a>";
			else
				echo FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0); ?>
		</td>		
		<td><?php
			if ($_SESSION['bManageGroups'] && !$bPrintView) echo "<a target=\"_top\" href=\"MemberRoleChange.php?GroupID=" . $iGroupID . "&PersonID=" . $per_ID . "&Return=1\">";
			echo $lst_OptionName;
			if ($_SESSION['bManageGroups'] && !$bPrintView) echo "</a>";
		?></td>
		<td><?php echo $sAddress1;?><?php if ($sAddress1 != "" && $sAddress2 != "") { echo ", "; } ?><?php if ($sAddress2 != "") echo $sAddress2; ?>&nbsp;</td>
		<td><?php echo $sCity ?>&nbsp;</td>
		<td><?php echo $sState ?>&nbsp;</td>
		<td><?php echo $sZip ?>&nbsp;</td>
		<td><?php echo $sHomePhone ?>&nbsp;</td>
		<td><?php echo $sEmail;?>&nbsp;</td>
	</tr>
	<?php
		if ($bHasSpecialProps && $bShowGSP)
		{
			$firstRow = true;
			// Get the special properties for this group
			$sSQL = "SELECT groupprop_master.* FROM groupprop_master
				WHERE grp_ID = " . $iGroupID . " AND prop_PersonDisplay = 'true' ORDER BY prop_ID";
			$rsPropList = RunQuery($sSQL);

			$sSQL = "SELECT * FROM groupprop_" . $iGroupID . " WHERE per_ID = " . $per_ID;
			$rsPersonProps = RunQuery($sSQL);
			$aPersonProps = mysql_fetch_array($rsPersonProps, MYSQL_BOTH);

			while ($aProps = mysql_fetch_array($rsPropList))
			{
				extract($aProps);
				$currentData = trim($aPersonProps[$prop_Field]);
				if (strlen($currentData) > 0)
				{
					// only create the properties table if it's actually going to be used
					if ($firstRow) {
						echo "<tr><td colspan=\"3\"><table width=\"100%\"><tr><td width=\"15%\"></td><td><table width=\"90%\" cellspacing=\"0\">";
						echo "<tr class=\"TinyTableHeader\"><td>" . gettext("Property") . "</td><td>" . gettext("Value") . "</td></tr>";
						$firstRow = false;
					}
					$sRowClass = AlternateRowStyle($sRowClass);
					if ($type_ID == 11) $prop_Special = $sCountry;
					echo "<tr class=\"$sRowClass\"><td>" . $prop_Name . "</td><td>" . displayCustomField($type_ID, $currentData, $prop_Special) . "</td></tr>";
				}
			}
			if (!$firstRow) echo "</table></td></tr></table></td></tr>";
		}
	}
	?>
</table>
</body>
</html>
