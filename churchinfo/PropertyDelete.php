<?php
/*******************************************************************************
 *
 *  filename    : PropertyDelete.php
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

if (!$_SESSION['bMenuOptions'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Property Delete Confirmation");

// Get the Type and Property
$sType = $_GET["Type"];
$iPropertyID = FilterInput($_GET["PropertyID"],'int');

//Do we have deletion confirmation?
if (isset($_GET["Confirmed"]))
{
	$sSQL = "DELETE FROM property_pro WHERE pro_ID = " . $iPropertyID;
	RunQuery($sSQL);

	$sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $iPropertyID;
	RunQuery($sSQL);

	Redirect("PropertyList.php?Type=" . $sType);
}

//Get the family record in question
$sSQL = "SELECT * FROM property_pro WHERE pro_ID = " . $iPropertyID;
$rsProperty = RunQuery($sSQL);
extract(mysql_fetch_array($rsProperty));

require "Include/Header.php";

?>

<p>
	<?php echo gettext("Please confirm deletion of this property:"); ?>
</p>

<p class="ShadedBox">
	<?php echo $pro_Name; ?>
</p>

<p>
	<?php echo gettext("Deleting this Property will also delete all assignments of this Property to any People, Family, or Group records."); ?>
</p>

<p align="center">
	<a href="PropertyDelete.php?Confirmed=Yes&PropertyID=<?php echo $iPropertyID ?>&Type=<?php echo $sType; ?>"><?php echo gettext("Yes, delete this record"); ?></a> <?php echo gettext("(this action cannot be undone)"); ?>
	 |
	<a href="PropertyList.php?Type=<?php echo $sType; ?>"><?php echo gettext("No, cancel this deletion"); ?></a>
</p>

</p>

<?php
require "Include/Footer.php";
?>
