<?php
/*******************************************************************************
 *
 *  filename    : PropertyEditor.php
 *  last change : 2003-01-07
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002 Deane Barker
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

// Security: User must have property and classification editing permission
if (!$_SESSION['bMenuOptions'])
{
	Redirect("Menu.php");
	exit;
}

//Get the PropertyID
$iPropertyID = FilterInput($_GET["PropertyID"],'int');

//Get the Type
$sType = FilterInput($_GET["Type"],'char',1);

//Based on the type, set the TypeName
switch($sType)
{
	case "p":
		$sTypeName = gettext("Person");
		break;

	case "f":
		$sTypeName = gettext("Family");
		break;

	case "g":
		$sTypeName = gettext("Group");
		break;

	default:
		Redirect("Menu.php");
		exit;
		break;
}

//Set the page title
$sPageTitle = $sTypeName . ' ' . gettext("Property Editor");

//Was the form submitted?
if (isset($_POST["Submit"]))
{
	$sName = FilterInput($_POST["Name"]);
	$sDescription = FilterInput($_POST["Description"]);
	$iClass = FilterInput($_POST["Class"],'int');
	$sPrompt = FilterInput($_POST["Prompt"]);

	//Did they enter a name?
	if (strlen($sName) < 1 )
	{
		$sNameError = "<br><font color=\"red\">" . gettext("You must enter a Name") . "</font>";
		$bError = True;
	}

	//Did they select a Type
	if (strlen($iClass) < 1)
	{
		$sClassError = "<br><font color=\"red\">" . gettext("You must select a Type") . "</font>";
		$bError = True;
	}

	//If no errors, let's update
	if (!$bError)
	{

		//Vary the SQL depending on if we're adding or editing
		if ($iPropertyID == "")
		{
			$sSQL = "INSERT INTO property_pro (pro_Class,pro_prt_ID,pro_Name,pro_Description,pro_Prompt) VALUES ('" . $sType . "'," . $iClass . ",'" . $sName . "','" . $sDescription . "','" . $sPrompt . "')";
		}
		else
		{
			$sSQL = "UPDATE property_pro SET pro_prt_ID = " . $iClass . ", pro_Name = '" . $sName . "', pro_Description = '" . $sDescription . "', pro_Prompt = '" . $sPrompt . "' WHERE pro_ID = " . $iPropertyID;
		}

		//Execute the SQL
		RunQuery($sSQL);

		//Route back to the list
		Redirect("PropertyList.php?Type=" . $sType);

	}

}
else
{

	if (strlen($iPropertyID) != 0)
	{
		//Get the data on this property
		$sSQL = "SELECT * FROM property_pro WHERE pro_ID = " . $iPropertyID;
		$rsProperty = mysql_fetch_array(RunQuery($sSQL));
		extract($rsProperty);

		//Assign values locally
		$sName = $pro_Name;
		$sDescription = $pro_Description;
		$iType = $pro_prt_ID;
		$sPrompt = $pro_Prompt;
	}

}

//Get the Property Types
$sSQL = "SELECT * FROM propertytype_prt WHERE prt_Class = '" . $sType . "' ORDER BY prt_Name";
$rsPropertyTypes = RunQuery($sSQL);

require "Include/Header.php";

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?PropertyID=<?php echo $iPropertyID; ?>&Type=<?php echo $sType; ?>">

<table cellpadding="4">
	<tr>
		<td valign="top" align="right"><b><?php echo gettext("Type:"); ?></b></td>
		<td>
			<select name="Class">
				<option value=""><?php echo gettext("Select Property Type"); ?></option>
				<?php
				while ($aRow = mysql_fetch_array($rsPropertyTypes))
				{
					extract($aRow);

					echo "<option value=\"" . $prt_ID . "\"";
					if ($iType == $prt_ID) { echo "selected"; }
					echo ">" . $prt_Name . "</option>";
				}
				?>
			</select>
			<?php echo $sClassError ?>
		</td>
	</tr>
	<tr>
		<td valign="top" align="right"><b><?php echo gettext("Name:"); ?></b></td>
		<td>
			<input type="text" name="Name" value="<?php echo htmlentities(stripslashes($sName)); ?>" size="50">
			<?php echo $sNameError ?>
		</td>
	</tr>
	<tr>
		<td align="right" valign="top"><b>"<?php echo gettext("A"); ?> <?php echo $sTypeName ?><BR><?php echo gettext("with this property.."); ?>":</b></td>
		<td><textarea name="Description" cols="60" rows="3"><?php echo htmlentities(stripslashes($sDescription)); ?></textarea></td>
	</tr>
	<tr>
		<td align="right" valign="top"><b><?php echo gettext("Prompt:"); ?></b></td>
		<td valign="top">
			<input type="text" name="Prompt" value="<?php echo htmlentities(stripslashes($sPrompt)) ?>" size="50">
			<br>
			<span class="SmallText"><?php echo gettext("Entering a Prompt value will allow the association of a free-form value."); ?></span>
		</td>
	</tr>
	<tr>
		<td colspan="2" align="center">
			<input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Save") . '"'; ?>>&nbsp;<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="document.location='PropertyList.php?Type=<?php echo $sType; ?>';">
		</td>
	</tr>
</table>

</form>

<?php
require "Include/Footer.php";
?>
