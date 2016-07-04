<?php
/*******************************************************************************
 *
 *  filename    : PropertyEditor.php
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

$sClassError = "";
$sNameError = "";

//Get the PropertyID
$iPropertyID = 0;
if (array_key_exists ("PropertyID", $_GET))
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

$bError = false;
$iType = 0;

//Was the form submitted?
if (isset($_POST["Submit"]))
{
	$sName = addslashes(FilterInput($_POST["Name"]));
	$sDescription = addslashes(FilterInput($_POST["Description"]));
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
		if ($iPropertyID == 0)
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

	if ($iPropertyID != 0) {
		//Get the data on this property
		$sSQL = "SELECT * FROM property_pro WHERE pro_ID = " . $iPropertyID;
		$rsProperty = mysql_fetch_array(RunQuery($sSQL));
		extract($rsProperty);

		//Assign values locally
		$sName = $pro_Name;
		$sDescription = $pro_Description;
		$iType = $pro_prt_ID;
		$sPrompt = $pro_Prompt;
	} else {
		$sName = "";
		$sDescription = "";
		$iType = 0;
		$sPrompt = "";
	}

}

//Get the Property Types
$sSQL = "SELECT * FROM propertytype_prt WHERE prt_Class = '" . $sType . "' ORDER BY prt_Name";
$rsPropertyTypes = RunQuery($sSQL);

require "Include/Header.php";

?>
<div class="box box-body">
  <form method="post" action="PropertyEditor.php?PropertyID=<?= $iPropertyID ?>&Type=<?= $sType ?>">
    <div class="form-group">
        <div class="row">
            <div class="col-md-6">
                <label for="Class"><?= gettext("Type:") ?></label>
                <select  class="form-control input-small" name="Class">
                    <option value=""><?= gettext("Select Property Type") ?></option>
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
                <?= $sClassError ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <label for="Name"><?= gettext("Name:") ?></label>
                <input class="form-control input-small" type="text" name="Name" value="<?= htmlentities(stripslashes($sName),ENT_NOQUOTES, "UTF-8") ?>" size="50">
                <?php echo $sNameError ?>
           </div>
       </div>
       <div class="row">
            <div class="col-md-6">
                <label for="Description">"<?= gettext("A") ?> <?php echo $sTypeName ?><BR><?= gettext("with this property..") ?>":</label>
                <textarea class="form-control input-small" name="Description" cols="60" rows="3"><?= htmlentities(stripslashes($sDescription),ENT_NOQUOTES, "UTF-8") ?></textarea>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <label for="Prompt"><?= gettext("Prompt:") ?></label>
                <input class="form-control input-small" type="text" name="Prompt" value="<?php echo htmlentities(stripslashes($sPrompt),ENT_NOQUOTES, "UTF-8") ?>" size="50">
                <span class="SmallText"><?= gettext("Entering a Prompt value will allow the association of a free-form value.") ?></span>
            </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <input type="submit" class="btn" name="Submit" value="<?= gettext("Save") ?>">&nbsp;<input type="button" class="btn" name="Cancel" value="<?= gettext("Cancel") ?>" onclick="document.location='PropertyList.php?Type=<?= $sType ?>';">
        </div>
        </div>
    </div>
</form>
</div>

<?php require "Include/Footer.php" ?>
