<?php
/*******************************************************************************
 *
 *  filename    : PropertyTypeDelete.php
 *  last change : 2003-06-04
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

// Security: User must have property and classification editing permission
if (!$_SESSION['bMenuOptions'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Property Type Delete Confirmation");

//Get the PersonID from the querystring
$iPropertyTypeID = FilterInput($_GET["PropertyTypeID"],'int');

//Do we have deletion confirmation?
if (isset($_GET["Confirmed"]))
{
	$sSQL = "DELETE FROM propertytype_prt WHERE prt_ID = " . $iPropertyTypeID;
	RunQuery($sSQL);

	$sSQL = "SELECT pro_ID FROM property_pro WHERE pro_prt_ID = " . $iPropertyTypeID;
	$result = RunQuery($sSQL);
	while ($aRow = mysql_fetch_array($result))
	{
		$sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $aRow['pro_ID'];
		RunQuery($sSQL);
	}

	$sSQL = "DELETE FROM property_pro WHERE pro_prt_ID = " . $iPropertyTypeID;
	RunQuery($sSQL);

	Redirect("PropertyTypeList.php");
}

$sSQL = "SELECT * FROM propertytype_prt WHERE prt_ID = " . $iPropertyTypeID;
$rsProperty = RunQuery($sSQL);
extract(mysql_fetch_array($rsProperty));

require "Include/Header.php";

if (isset($_GET['Warn'])) { ?>
	<p align="center" class="LargeError">
		<?php echo "<b>" . gettext("Warning") . ": </b>" . gettext("This property type is still being used by at least one property.") . "<BR>" . gettext("If you delete this type, you will also remove all properties using") . "<BR>" . gettext("it and lose any corresponding property assignments."); ?>
	</p>
<?php } ?>

<p align="center" class="MediumLargeText">
	<?php echo gettext("Please confirm deletion of this Property Type:"); ?> <b><?php echo $prt_Name; ?></b>
</p>

<p align="center">
	<a href="PropertyTypeDelete.php?Confirmed=Yes&PropertyTypeID=<?php echo $iPropertyTypeID ?>"><?php echo gettext("Yes, delete this record"); ?></a>
	&nbsp;&nbsp;
	<a href="PropertyTypeList.php?Type=<?php echo $sType; ?>"><?php echo gettext("No, cancel this deletion"); ?></a>

</p>

<?php
require "Include/Footer.php";
?>
