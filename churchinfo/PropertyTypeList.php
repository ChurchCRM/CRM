<?php
/*******************************************************************************
 *
 *  filename    : PropertyTypeList.php
 *  last change : 2003-03-27
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

// Set the page title
$sPageTitle = gettext("Property Type List");

// Get the properties types
$sSQL = "SELECT prt_ID, prt_Class, prt_Name, COUNT(pro_ID) AS Properties FROM propertytype_prt LEFT JOIN property_pro ON pro_prt_ID = prt_ID GROUP BY prt_ID, prt_Class, prt_Name";
$rsPropertyTypes = RunQuery($sSQL);

require "Include/Header.php";

//Display the new property link
if ($_SESSION['bMenuOptions'])
{
	echo "<p align=\"center\"><a href=\"PropertyTypeEditor.php\">" . gettext("Add a New Property Type") . "</a></p>";
}

//Start the table
echo "<table cellspacing=\"0\" cellpadding=\"4\" align=\"center\">";
echo "<tr class=\"TableHeader\">";
echo "<td>" . gettext("Name") . "</td>";
echo "<td>" . gettext("Class") . "</td>";
echo "<td align=\"center\">" . gettext("Properties") . "</td>";
if ($_SESSION['bMenuOptions'])
{
	echo "<td>" . gettext("Edit") . "</td>";
	echo "<td>" . gettext("Delete") . "</td>";
}
echo "</tr>";

//Initalize the row shading
$sRowClass = "RowColorA";

//Loop through the records
while ($aRow = mysql_fetch_array($rsPropertyTypes))
{
	extract($aRow);

	$sRowClass = AlternateRowStyle($sRowClass);

	echo "<tr class=\"" . $sRowClass . "\">";
	echo "<td>" . $prt_Name . "</td>";
	echo "<td>";
	switch($prt_Class) { case "p": echo gettext("Person"); break; case "f": echo gettext("Family"); break; case "g": echo gettext("Group"); break;}
	echo "<td align=\"center\">" . $Properties . "</td>";
	if ($_SESSION['bMenuOptions'])
	{
		echo "<td><a href=\"PropertyTypeEditor.php?PropertyTypeID=" . $prt_ID . "\">" . gettext("Edit") . "</a></td>";
		if ($Properties == 0)
			echo "<td><a href=\"PropertyTypeDelete.php?PropertyTypeID=" . $prt_ID . "\">" . gettext("Delete") . "</a></td>";
		else
			echo "<td><a href=\"PropertyTypeDelete.php?PropertyTypeID=" . $prt_ID . "&Warn\">" . gettext("Delete") . "</a></td>";
	}
	echo "</tr>";
}

//End the table
echo "</table>";

require "Include/Footer.php";

?>
