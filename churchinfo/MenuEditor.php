<?php
/*******************************************************************************
 *
 *  filename    : MenuEditor.php
 *  last change : 2007-06-28
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2007 Frederick To
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

function DelChildren($menu) {
	global $cnInfoCentral;
	
	$sSQL = "SELECT mid, name, ismenu, content, uri, statustext, session_var, session_var_in_text, session_var_in_uri, url_parm_name, security_grp, active FROM menuconfig_mcf WHERE parent = '$menu' ORDER BY sortorder";
	
	$rsMenu = RunQuery($sSQL);
	$item_cnt = mysql_num_rows($rsMenu);
	$idx = 1;
	$ptr = 1;
	$lvl = $plvl + 1;
	while ($aRow = mysql_fetch_array($rsMenu)) {	
		DelIndividual($aRow, $idx, $lvl);
		if ($ptr < $item_cnt) {
			$idx++;
		}
		$ptr++;
	}
}
function DelIndividual($aMenu) {

	$sSQL = "DELETE FROM menuconfig_mcf WHERE mid = ".$aMenu['mid'];
	RunQuery($sSQL);
	
	if ($aMenu['ismenu']) {
		DelChildren($aMenu['name']);
	}
}

function AdjustOrder($sAdjParent,$iDelOrder) {

	$sSQL = "SELECT mid, sortorder FROM menuconfig_mcf WHERE parent = '$sAdjParent' AND sortorder > $iDelOrder ORDER BY sortorder";
	$rsTemp = RunQuery($sSQL);
	while ($aRow = mysql_fetch_array($rsTemp))
	{
		extract ($aRow);
				
		$sSQL = "UPDATE menuconfig_mcf SET sortorder = ($sortorder - 1) WHERE mid = $mid";
		RunQuery($sSQL);
	}

}
// Security: User must have Manage Groups permission
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Menu Item Editor");

$iMenuID = FilterInput($_GET["mid"],'int');
$sMode = FilterInput($_GET["mode"]);

//Is this the second pass?
if (isset($_POST["DeleteCancel"])) {
	Redirect("MenuSetup.php");
}

if (isset($_POST["DeleteSubmit"])) {
	$sSQL = "SELECT name, parent ismenu, sortorder FROM menuconfig_mcf WHERE mid = $iMenuID";
	$rsDelNode = RunQuery($sSQL);
	$aDelNode = mysql_fetch_array($rsDelNode);
	
	$sDelName = $aDelNode['name'];
	$sDelParent = $aDelNode['parent'];
	$iDelSortOrder = $aDelNode['sortorder'];
	
	$sSQL = "DELETE FROM menuconfig_mcf WHERE mid = $iMenuID";
	RunQuery($sSQL);
	
	// Delete also childrens
	if ($aDelNode['ismenu']) {
		DelChildren($sDelName);
	}
	
	AdjustOrder($sDelParent, $iDelSortOrder);
	
	Redirect("MenuSetup.php");
}
	
if (isset($_POST["MenuSubmit"]))
{
	//Assign everything locally
	$sName = FilterInput($_POST["Name"]);
	$sParent = FilterInput($_POST["NewParent"]);
	$sOrigParent = FilterInput($_POST["OrigParent"]);
	$bIsMenu = FilterInput($_POST["IsMenu"]);
	$sContent = FilterInput($_POST["Content"]);
	$sURI = FilterInput($_POST["uri"]);
	$sStatusText = FilterInput($_POST["StatusText"]);
	$sSecurityGroup = FilterInput($_POST["SecurityGroup"]);
	$sSessionVar = FilterInput($_POST["SessionVar"]);
	$bSVinText = FilterInput($_POST["SVinText"]);
	$bSVinURI = FilterInput($_POST["SVinURI"]);
	$sParmName = FilterInput($_POST["ParmName"]);
	$bActive = FilterInput($_POST["Active"]);

	// Verify menu item has already been added.
	$sSQL = "SELECT '' FROM menuconfig_mcf WHERE mid = $iMenuID";
	$rsCount = RunQuery($sSQL);
	if (mysql_num_rows($rsCount) == 0)
	{
		Redirect("MenuManager.php");
	}

	//Did they enter a Content?
	if (strlen($sContent) < 1)
	{
		$sContentError = gettext("Must provide menu text");
		$bErrorFlag = True;
	}
	
	// if session variable is required on status text or uri, it has be defined
	if ($bSVinText || $bSVinURI) {
		if (strlen($sSessionVar) < 1)
		{
			$sSessionVarError = gettext("Missing Session Variable name");;
			$bErrorFlag = True;
		}
	}
	
	// If no errors, then let's update...
	if (!$bErrorFlag)
	{
		$sSQL = "UPDATE menuconfig_mcf SET parent = '" . $sParent . "', ismenu = '" . $bIsMenu . "', content = '" . $sContent ."', uri = '" . $sURI . "', statustext = '" . $sStatusText . "', security_grp = '" . $sSecurityGroup . "', session_var_in_text = '" . $bSVinText . "', session_var_in_uri = '" . $bSVinURI . "', active = '" . $bActive . "'";
		
		if (strlen($sSessionVar) > 0) 
		{
			$sSQL .= ", session_var = '" . $sSessionVar . "'";
		} 
		else
		{
			$sSQL .= ", session_var = NULL";
		}
		
		if (strlen($sParmName) > 0)
		{
			$sSQL .= ", url_parm_name = '" . $sParmName . "'";
		}
		else
		{
			$sSQL .= ", url_parm_name = NULL";
		}
		
		$sSQL .= " WHERE mid = '" . $iMenuID ."' AND name = '" . $sName . "'";
		
		RunQuery($sSQL);
		
		if (!($sParent == $sOrigParent))
		{
			$sSQL = "SELECT sortorder FROM menuconfig_mcf WHERE mid = $iMenuID";
			$rsMenu = RunQuery($sSQL);
			$aRow = mysql_fetch_array($rsMenu);
			extract ($aRow);
			$iOldSortOrder = $sortorder;
			
			$sSQL = "SELECT '' FROM menuconfig_mcf WHERE parent = '$sParent'";
			$rsTemp = RunQuery($sSQL);
			$numRows = mysql_num_rows($rsTemp);
			$iNewSortOrder = $numRows + 1;
			
			$sSQL = "UPDATE menuconfig_mcf SET sortorder = '" . $iNewSortOrder . "' WHERE mid = '" . $iMenuID ."'";
			RunQuery($sSQL);
			
			AdjustOrder($sOrigParent, $iOldSortOrder);
//			$sSQL = "SELECT mid, sortorder FROM menuconfig_mcf WHERE parent = '$sOrigParent' AND sortorder > $iOldSortOrder ORDER BY sortorder";
//			$rsTemp = RunQuery($sSQL);
//			while ($aRow = mysql_fetch_array($rsTemp))
//			{
//				extract ($aRow);
//				
//				$sSQL = "UPDATE menuconfig_mcf SET sortorder = ($sortorder - 1) WHERE mid = $mid";
//				RunQuery($sSQL);
//			}
		}

		Redirect("MenuSetup.php");
	}

}
else
{
	if (strlen($iMenuID) < 1) 
	{
		Redirect("MenuSetup.php");
	}
	
	$sSQL = "SELECT * FROM menuconfig_mcf WHERE mid = '". $iMenuID . "'";
	$rsMenuRead = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsMenuRead);
	
	extract($aRow);
	$iMenuID = $mid;
	$sName = $name;
	$sParent = $parent;
	$sOrigParent = $parent;
	$bIsMenu = $ismenu;
	$sContent = $content;
	$sURI = $uri;
	$sStatusText = $statustext;
	$sSecurityGroup = $security_grp;
	$sSessionVar = $session_var;
	$bSVinText = $session_var_in_text;
	$bSVinURI = $session_var_in_uri;
	$sParmName = $url_parm_name;
	$bActive = $active;

}

$aSecuritySelect[$sSecurityGroup] = " selected";

$aSecurityList[] = "bAdmin";
$aSecurityList[] = "bAddRecords";
$aSecurityList[] = "bEditRecords";
$aSecurityList[] = "bDeleteRecords";
$aSecurityList[] = "bMenuOptions";
$aSecurityList[] = "bManageGroups";
$aSecurityList[] = "bFinance";
$aSecurityList[] = "bNotes";
$aSecurityList[] = "bCommunication";
$aSecurityList[] = "bCanvasser";

$sSQL = "SELECT DISTINCT ucfg_name FROM userconfig_ucfg WHERE ucfg_per_id = 0 AND ucfg_cat = 'SECURITY' ORDER by ucfg_id";
$rsSecGrpList = RunQuery($sSQL);
			
while ($aRow = mysql_fetch_array($rsSecGrpList))
{
	$aSecurityList[] = $aRow['ucfg_name'];
}

asort($aSecurityList);

array_unshift($aSecurityList, "bAll");
require "Include/Header.php";

if ($sMode == "Delete") {
?>
<form method="post" action="MenuEditor.php?mid=<?php echo $iMenuID; ?>" name="MenuEditor">

<table cellpadding="3" align="center">

	<tr>
		<td align="center" colspan="2">
		<?php echo "<span class=\"LargeText\" style=\"color: red;\">" . gettext("Are you sure to delete this menu entry? Action cannot be redone!") . "</span>"; ?>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn" width="40%"><?php echo gettext("Menu Text"); ?></td>
		<td class="TextColumn"><?php echo $sContent; ?></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("In Menu:"); ?></td>
		<td class="TextColumn"><?php echo $sParent; ?></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Open another sub-menu?"); ?></td>
		<td class="TextColumn">
		<?php 
			if ($bIsMenu == 1) { 
				echo gettext("Yes"); 
			} else { 
				echo gettext("No"); 
			} 
		?>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Link URL:"); ?></td>
		<td class="TextColumn"><?php echo $sURI; ?></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Text on Status Line:"); ?></td>
		<td class="TextColumn"><?php echo $sStatusText; ?></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Security Group:"); ?></td>
		<td class="TextColumn"><?php echo $sSecurityGroup; ?></td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Parameter"); ?></td>
		<td class="TextColumn"><?php echo $sParmName; ?></td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Session Variable Name:"); ?></td>
		<td class="TextColumn"><?php echo $sSessionVar; ?></td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Session Variable in Status"); ?></td>
		<td class="TextColumn">
		<?php 
			if ($bSVinText == 1) { 
				echo gettext("Yes"); 
			} else { 
				echo gettext("No"); 
			} 
		?>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Session Variable in Link Address"); ?></td>
		<td class="TextColumn">
		<?php 
			if ($bSVinURI == 1) { 
				echo gettext("Yes"); 
			} else { 
				echo gettext("No"); 
			} 
		?>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Currently Active?"); ?></td>
		<td class="TextColumn">
		<?php 
			if ($bActive == 1) { 
				echo gettext("Yes"); 
			} else { 
				echo gettext("No"); 
			} 
		?>
		</td>
	</tr>
	<tr>
		<td align="center" colspan="2">
			<input type="submit" class="icButton" value="<?php echo gettext("Yes"); ?>" name="DeleteSubmit">
			<input type="submit" class="icButton" value="<?php echo gettext("No"); ?>" name="DeleteCancel">
		</td>
	</tr>

</table>
</form>
<?php
} else {
?>
<form method="post" action="MenuEditor.php?mid=<?php echo $iMenuID; ?>" name="MenuEditor">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="MenuSubmit">
			<input type="Reset" class="icButton" value="<?php echo gettext("Reset"); ?>" name="MenuReset">
		</td>
	</tr>

	<tr>
		<td align="center">
		<?php if ( $bErrorFlag ) echo "<span class=\"LargeText\" style=\"color: red;\">" . gettext("Invalid fields or selections. Changes not saved! Please correct and try again!") . "</span>"; ?>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn" <?php addToolTip(gettext("Text appears on menu")); ?>><?php echo gettext("Menu Text"); ?></td>
		<td class="TextColumn"><input type="text" name="Content" id="Content" value="<?php echo htmlentities(stripslashes($sContent),ENT_NOQUOTES, "UTF-8"); ?>">
		<div><font color="red"><?php echo $sContentError; ?></font></div>
</td>
	</tr>

	<tr>
		<td class="LabelColumn"<?php addToolTip(gettext("belongs to menu...")); ?>><?php echo gettext("In Menu:"); ?></td>
		<td class="TextColumn">
		<?php 
			$sSQL = "SELECT name, content FROM menuconfig_mcf WHERE ismenu = 1 ORDER BY sortorder";
			$rsMenuList = RunQuery($sSQL);
			
			$sMenuList = "<select name=\"NewParent\">";
			while ($aRow = mysql_fetch_array($rsMenuList))
			{
				$sMenuList .= "<option value=\"" . $aRow['name'] . "\"";
//		echo "lst_OptionName:".$aAryRow['lst_OptionName']."<br>";
				if ($aRow['name'] == $sParent)
				{ 
					$sMenuList .= " selected"; 
				}
				$sMenuList .= ">" . $aRow['content']."</option>\n";
			}
			$sMenuList .= "</select>";
			echo $sMenuList;
		?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"<?php addToolTip(gettext("Does this item open another menu?")); ?>><?php echo gettext("Open another sub-menu?"); ?></td>
		<td class="TextColumnWithBottomBorder">
			<select name="IsMenu">
				<option value="1" <?php if ($bIsMenu == 1) { echo "selected"; } ?>><?php echo gettext("Yes"); ?></option>
				<option value="0" <?php if ($bIsMenu == 0) { echo "selected"; } ?>><?php echo gettext("No"); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn" <?php addToolTip(gettext("Link URL")); ?>><?php echo gettext("Link URL:"); ?></td>
		<td class="TextColumn"><input type="text" name="uri" maxlength="255" size="100" id="uri" value="<?php echo htmlentities(stripslashes($sURI),ENT_NOQUOTES, "UTF-8"); ?>"></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Text on Status Line:"); ?></td>
		<td class="TextColumn"><input type="text" name="StatusText" maxlength="255" size="100" id="StatusText" value="<?php echo htmlentities(stripslashes($sStatusText),ENT_NOQUOTES, "UTF-8"); ?>"></td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Security Group:"); ?></td>
		<td class="TextColumn">
	<?php
			$sSecGrpList = "<select name=\"SecurityGroup\">";
			for ($i=0; $i<count($aSecurityList); $i++)
			{
				$sSecGrpList .= "<option value=\"" . $aSecurityList[$i] . "\"" .$SecuritySelect[$aSecurityList[$i]] . ">" . $aSecurityList[$i] . "</option>\n";
			}
			$sSecGrpList .= "</select>";
			echo $sSecGrpList;
		?>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn" <?php addToolTip("Extra parameter to the link uri"); ?>><?php echo gettext("Parameter"); ?></td>
		<td class="TextColumn"><input type="text" name="ParmName" id="ParmName" value="<?php echo htmlentities(stripslashes($sParmName ),ENT_NOQUOTES, "UTF-8"); ?>"></td>
	</tr>

	<tr>
		<td class="LabelColumn"<?php addToolTip("Is SESSION variable required?"); ?>><?php echo gettext("Session Variable Name:"); ?></td>
		<td class="TextColumn"><input type="text" name="SessionVar" id="SessionVar" value="<?php echo htmlentities(stripslashes($sSessionVar)); ?>">
			<div><font color="red"><?php echo $sSessionVarError; ?></font></div>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"<?php addToolTip(gettext("Show session variable in status?")); ?>><?php echo gettext("Session Variable in Status"); ?></td>
		<td class="TextColumnWithBottomBorder">
			<select name="SVinText">
				<option value="1" <?php if ($bSVinText == 1) { echo "selected"; } ?>><?php echo gettext("Yes"); ?></option>
				<option value="0" <?php if ($bSVinText == 0) { echo "selected"; } ?>><?php echo gettext("No"); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"<?php addToolTip(gettext("Need evaluated session variable in Link Address?")); ?>><?php echo gettext("Session Variable in Link Address"); ?></td>
		<td class="TextColumnWithBottomBorder">
			<select name="SVinURI">
				<option value="1" <?php if ($bSVinURI == 1) { echo "selected"; } ?>><?php echo gettext("Yes"); ?></option>
				<option value="0" <?php if ($bSVinURI == 0) { echo "selected"; } ?>><?php echo gettext("No"); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<td class="LabelColumn"<?php addToolTip(gettext("Do you want to make this menu item available?")); ?>><?php echo gettext("Active?"); ?></td>
		<td class="TextColumnWithBottomBorder">
			<select name="Active">
				<option value="1" <?php if ($bActive == 1) { echo "selected"; } ?>><?php echo gettext("Yes"); ?></option>
				<option value="0" <?php if ($bActive == 0) { echo "selected"; } ?>><?php echo gettext("No"); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<td <?php if ($numCustomFields > 0) echo "colspan=\"2\""; ?> align="center">
			<?php echo "<input type=\"hidden\" Name=\"Name\" value=\"".$sName."\">"; ?>
			<?php echo "<input type=\"hidden\" Name=\"OrigParent\" value=\"".$sParent."\">"; ?>
			<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save") . '"'; ?> name="MenuSubmit">
			<input type="reset" class="icButton" <?php echo 'value="' . gettext("Reset") . '"'; ?> name="MenuReset">
		</td>
	</tr>

</table>
</form>
<?php
}

require "Include/Footer.php";
?>