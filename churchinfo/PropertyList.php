<?php
/*******************************************************************************
 *
 *  filename    : PropertyList.php
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

//Get the type to display
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
$sPageTitle = $sTypeName . ' ' . gettext("Property List");

//Get the properties
$sSQL = "SELECT * FROM property_pro, propertytype_prt WHERE prt_ID = pro_prt_ID AND pro_Class = '" . $sType . "' ORDER BY prt_Name,pro_Name";
$rsProperties = RunQuery($sSQL);

require "Include/Header.php";

if ($_SESSION['bMenuOptions'])
{
	//Display the new property link
	echo "<p align=\"center\"><a href=\"PropertyEditor.php?Type=" . $sType . "\">" . gettext("Add a New") . " " . $sTypeName . " " . gettext("Property") . "</a></p>";
}

//Start the table
echo "<table cellspacing=\"0\" cellpadding=\"4\" align=\"center\">";
echo "<tr class=\"TableHeader\">";
echo "<td valign=\"top\"><b>" . gettext("Name") . "</b></td>";
echo "<td valign=\"top\"><b>" . gettext("A") . " " . $sTypeName . " " . gettext("with this Property...") . "</b></td>";
echo "<td valign=\"top\"><b>" . gettext("Prompt") . "</b></td>";
if ($_SESSION['bMenuOptions'])
{
	echo "<td valign=\"top\"><b>" . gettext("Edit") . "</b></td>";
	echo "<td valign=\"top\"><b>" . gettext("Delete") . "</b></td>";
}
echo "</tr>";

echo "<tr><td>&nbsp;</td></tr>";

//Initalize the row shading
$sRowClass = "RowColorA";

//Loop through the records
while ($aRow = mysql_fetch_array($rsProperties))
{

	$pro_Prompt = "";
	$pro_Description = "";
	extract($aRow);


	//Did the Type change?
	if ($iPreviousPropertyType != $prt_ID)
	{

		//Write the header row
		echo $sBlankLine;
		echo "<tr class=\"RowColorA\"><td colspan=\"5\"><b>" . $prt_Name . "</b></td></tr>";
		$sBlankLine = "<tr><td>&nbsp;</td></tr>";

		//Reset the row color
		$sRowClass = "RowColorA";
	}

	$sRowClass = AlternateRowStyle($sRowClass);

	echo "<tr class=\"" . $sRowClass . "\">";
	echo "<td valign=\"top\">" . $pro_Name . "&nbsp;</td>";
	echo "<td valign=\"top\">"; if (strlen($pro_Description) > 0) { echo "..." . $pro_Description; }; echo "&nbsp;</td>";
	echo "<td valign=\"top\">" . $pro_Prompt . "&nbsp;</td>";
	if ($_SESSION['bMenuOptions'])
	{
		echo "<td valign=\"top\"><a href=\"PropertyEditor.php?PropertyID=" . $pro_ID . "&Type=" . $sType . "\">" . gettext("Edit") . "</a></td>";
		echo "<td valign=\"top\"><a href=\"PropertyDelete.php?PropertyID=" . $pro_ID . "&Type=" . $sType . "\">" . gettext("Delete") . "</a></td>";
	}
	echo "</tr>";

	//Store the PropertyType
	$iPreviousPropertyType = $prt_ID;

}

//End the table
echo "</table>";

require "Include/Footer.php";

?>
