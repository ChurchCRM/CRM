<?php
/*******************************************************************************
 *
 *  filename    : GroupList.php
 *  last change : 2002-03-03
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

//Set the page title
$sPageTitle = gettext("Group Listing");

require "Include/Header.php";

?>

<?php if ($_SESSION['bManageGroups']) { ?>
<p align="center">
	<a href="GroupEditor.php"><?php echo gettext("Add a New Group"); ?></a>
</p>
<?php } ?>

<?php

//Get all the User records
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Type,grp_Name";
$rsGroups = RunQuery($sSQL);

?>

<table cellpadding="4" align="center" cellspacing="0" width="50%">

	<tr class="TableHeader">
		<td><?php echo gettext("Name"); ?></td>
		<td align="center"><?php echo gettext("Members"); ?></td>
		<td align="center"><?php echo gettext("Type"); ?></td>
	</tr>

<?php

	//Set the initial row color
	$sRowClass = "RowColorA";

	//Loop through the person recordset
	while ($aRow = mysql_fetch_array($rsGroups))
	{

		extract($aRow);

		//Alternate the row color
		$sRowClass = AlternateRowStyle($sRowClass);

		//Get the count for this group
		$sSQL = "SELECT Count(*) AS iCount FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = " . $grp_ID;
		$rsMemberCount = mysql_fetch_array(RunQuery($sSQL));
		extract($rsMemberCount);
		

		//Get the group's type name
		if ($grp_Type > 0)
		{
			$sSQL = "SELECT lst_OptionName FROM list_lst WHERE lst_ID=3 AND lst_OptionID = " . $grp_Type;
			$rsGroupType = mysql_fetch_array(RunQuery($sSQL));
			$sGroupType = $rsGroupType[0];
		}
		else
			$sGroupType = gettext("Undefined");

		//Display the row
		?>
		
		<tr class="<?php echo $sRowClass ?>">
			<td><a href="GroupView.php?GroupID=<?php echo $grp_ID ?>"><?php echo $grp_Name ?></a></td>
			<td align="center"><?php echo $iCount ?></td>
			<td align="center"><?php echo $sGroupType ?></td>
		</tr>
		
		<?php
		
	}

	//Close the table
	echo "</table>";

//}

?>

<?php
require "Include/Footer.php";
?>
