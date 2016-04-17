<?php
/*******************************************************************************
 *
 *  filename    : PropertyTypeEditor.php
 *  last change : 2003-01-07
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
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

//Set the page title
$sPageTitle = gettext("Property Type Editor");

//Get the PropertyID
$iPropertyTypeID = 0;
if (array_key_exists ("PropertyTypeID", $_GET))
	$iPropertyTypeID = FilterInput($_GET["PropertyTypeID"],'int');

$sClass = "";
$sNameError = "";
$bError = false;

//Was the form submitted?
if (isset($_POST["Submit"]))
{
	$sName = FilterInput($_POST["Name"]);
	$sDescription = FilterInput($_POST["Description"]);
	$sClass = FilterInput($_POST["Class"],'char',1);

	//Did they enter a name?
	if (strlen($sName) < 1 )
	{
		$sNameError = "<font color=\"red\">" . gettext("You must enter a name") . "</font>";
		$bError = True;
	}

	//If no errors, let's update
	if (!$bError)
	{
		//Vary the SQL depending on if we're adding or editing
		if ($iPropertyTypeID == "")
		{
			$sSQL = "INSERT INTO propertytype_prt (prt_Class,prt_Name,prt_Description) VALUES ('" . $sClass . "','" . $sName . "','" . $sDescription . "')";
		}
		else
		{
			$sSQL = "UPDATE propertytype_prt SET prt_Class = '" . $sClass . "', prt_Name = '" . $sName . "', prt_Description = '" . $sDescription . "' WHERE prt_ID = " . $iPropertyTypeID;
		}

		//Execute the SQL
		RunQuery($sSQL);

		//Route back to the list
		Redirect("PropertyTypeList.php");
	}
} elseif ($iPropertyTypeID > 0) {
	//Get the data on this property
	$sSQL = "SELECT * FROM propertytype_prt WHERE prt_ID = " . $iPropertyTypeID;
	$rsProperty = mysql_fetch_array(RunQuery($sSQL));
	extract($rsProperty);

	//Assign values locally
	$sName = $prt_Name;
	$sDescription = $prt_Description;
	$sClass = $prt_Class;
} else {
	$sName = "";
	$sDescription = "";
	$sClass = "";
}

require "Include/Header.php";

?>
<div class="box box-body">
<form method="post" action="PropertyTypeEditor.php?PropertyTypeID=<?= $iPropertyTypeID ?>">

<table class="table">
	<tr>
		<td align="right"><b><?= gettext("Class:") ?></b></td>
		<td>
			<select name="Class">
				<option value="p" <?php if($sClass == "p") {echo "selected";} ?>><?= gettext("Person") ?></option>
				<option value="f" <?php if($sClass == "f") {echo "selected";} ?>><?= gettext("Family") ?></option>
				<option value="g" <?php if($sClass == "g") {echo "selected";} ?>><?= gettext("Group") ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<td align="right"><b><?= gettext("Name:") ?></b></td>
		<td><input type="text" name="Name" value="<?= htmlentities(stripslashes($sName),ENT_NOQUOTES, "UTF-8") ?>" size="40"> 			<?= $sNameError ?>
		</td>
	</tr>
	<tr>
		<td align="right" valign="top"><b><?= gettext("Description:") ?></b></td>
		<td><textarea name="Description" cols="60" rows="10"><?= htmlentities(stripslashes($sDescription),ENT_NOQUOTES, "UTF-8") ?></textarea></td>
	</tr>
	<tr>
		<td colspan="2" align="center">
			<input type="submit" class="btn btn-primary" name="Submit" <?= 'value="' . gettext("Save") . '"' ?>>&nbsp;<input type="button" class="btn btn-default" name="Cancel" <?= 'value="' . gettext("Cancel") . '"' ?> onclick="document.location='PropertyTypeList.php';">
		</td>
	</tr>
</table>

</form>
</div>

<?php require "Include/Footer.php" ?>
