<?php
/*******************************************************************************
 *
 *  filename    : PropertyTypeEditor.php
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

//Set the page title
$sPageTitle = gettext("Property Type Editor");

//Get the PropertyID
$iPropertyTypeID = FilterInput($_GET["PropertyTypeID"],'int');

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
}
elseif (strlen($iPropertyTypeID) > 0)
{
	//Get the data on this property
	$sSQL = "SELECT * FROM propertytype_prt WHERE prt_ID = " . $iPropertyTypeID;
	$rsProperty = mysql_fetch_array(RunQuery($sSQL));
	extract($rsProperty);

	//Assign values locally
	$sName = $prt_Name;
	$sDescription = $prt_Description;
	$sClass = $prt_Class;
}

require "Include/Header.php";

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?PropertyTypeID=<?php echo $iPropertyTypeID; ?>">

<table cellpadding="4">
	<tr>
		<td align="right"><b><?php echo gettext("Class:"); ?></b></td>
		<td>
			<select name="Class">
				<option value="p" <?php if($sClass == "p") {echo "selected";} ?>><?php echo gettext("Person"); ?></option>
				<option value="f" <?php if($sClass == "f") {echo "selected";} ?>><?php echo gettext("Family"); ?></option>
				<option value="g" <?php if($sClass == "g") {echo "selected";} ?>><?php echo gettext("Group"); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<td align="right"><b><?php echo gettext("Name:"); ?></b></td>
		<td><input type="text" name="Name" value="<?php echo htmlentities(stripslashes($sName)); ?>" size="40"> 			<?php echo $sNameError; ?>
		</td>
	</tr>
	<tr>
		<td align="right" valign="top"><b><?php echo gettext("Description:"); ?></b></td>
		<td><textarea name="Description" cols="60" rows="10"><?php echo htmlentities(stripslashes($sDescription)); ?></textarea></td>
	</tr>
	<tr>
		<td colspan="2" align="center">
			<input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Save") . '"'; ?>>&nbsp;<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="document.location='PropertyTypeList.php';">
		</td>
	</tr>
</table>

</form>

<?php
require "Include/Footer.php";
?>
