<?php
/*******************************************************************************
 *
 *  filename    : Upgrade-Helper.php
 *  last change : 2003-03-29
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Does some maintenance on the database for certain upgrades.
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";

// Security: user must be administrator to use this page
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

$iOperation = $_POST["Operation"];
switch($iOperation)
{
	case 1:
		$sSQL = "SELECT per_ID FROM person_per";
		$rsPeople = RunQuery($sSQL);

		// Create custom fields table entries for everyone currently in the database.
		while ($person = mysql_fetch_array($rsPeople))
		{
			$sSQL = "INSERT INTO `person_custom` (`per_ID`)	VALUES ('" . $person[0] . "');";
			RunQuery($sSQL,false); // this will silently fail if record already exists; not a problem
		}
	break;

	case 2:
		$sSQL = "SELECT * FROM classification_cls ORDER BY cls_ID";
		$result = RunQuery($sSQL);
		$count = 1;
		while($aRow = mysql_fetch_array($result))
		{
			extract($aRow);
			$sSQL = "INSERT INTO list_lst VALUES (1, $cls_ID, $count,'$cls_Description')";
			RunQuery($sSQL);
			$count++;
		}
		echo "Done with classifications. ";

		$sSQL = "SELECT * FROM familyrole_fmr ORDER BY fmr_Sequence";
		$result = RunQuery($sSQL);
		$count = 1;
		while($aRow = mysql_fetch_array($result))
		{
			extract($aRow);
			$sSQL = "INSERT INTO list_lst VALUES (2, $fmr_ID, $count,'$fmr_Description')";
			RunQuery($sSQL);
			$count++;
		}
		echo "Done with family roles. ";

 		// Get the first available lst_ID for translating group-role lists.  lst_ID 0-9 are reserved for permanent lists.
		$sSQL = "SELECT MAX(lst_ID) FROM list_lst";
		$aTemp = mysql_fetch_array(RunQuery($sSQL));
		if ($aTemp[0] > 9)
			$newListID = $aTemp[0] + 1;
		else
			$newListID = 10;

		$sSQL = "SELECT grp_ID, grp_DefaultRole FROM group_grp";
		$rsGroups = RunQuery($sSQL);

		while($aGroup = mysql_fetch_array($rsGroups))
		{
			extract($aGroup);

			$sSQL = "SELECT * FROM role_rle WHERE rle_grp_ID = $grp_ID ORDER BY rle_Sequence";
			$result = RunQuery($sSQL);
			$count = 1;
			$newDefaultID = 1;

			while($aRole = mysql_fetch_array($result))
			{
				extract($aRole);
				$sSQL = "INSERT INTO list_lst VALUES ($newListID, $count, $count,'$rle_Name')";
				RunQuery($sSQL);

				// if we've just processed the old grp_defaultrole, store the new OptionID so we can fix the group table
				if ($rle_ID == $grp_DefaultRole)
					$newDefaultID = $count;

				// Update any group members who were using the old rle_ID.. translate to the new one.
				$sSQL = "UPDATE person2group2role_p2g2r SET p2g2r_rle_ID=$count WHERE p2g2r_rle_ID=$rle_ID";
				RunQuery($sSQL);

				$count++;
			}

			// reset default role and set the group's RoleList_ID
			$sSQL = "UPDATE group_grp SET grp_DefaultRole=$newDefaultID, grp_RoleListID=$newListID WHERE grp_ID = $grp_ID";
			RunQuery($sSQL);

			$newListID++;
		}
		echo "Done with group roles. Import complete!";

	break;

	case 3:
		$sSQL = "DROP TABLE `role_rle`";
		RunQuery($sSQL);
		$sSQL = "DROP TABLE `classification_cls`";
		RunQuery($sSQL);
		$sSQL = "DROP TABLE `familyrole_fmr`";
		RunQuery($sSQL);
	break;

	case 4:
		$dirsToProcess = array('../Images/Person', '../Images/Family');
		foreach($dirsToProcess as $dir)
		{
			if ($dh = opendir($dir))
			{
				while (($file = readdir($dh)) != false)
				{
					if (substr($file,-3) == "jpg") {
						$srcImage=imagecreatefromjpeg($dir . '/' . $file);
						$src_w=imageSX($srcImage);
						$src_h=imageSY($srcImage);

						// Calculate thumbnail's height and width (a "maxpect" algorithm)
						$dst_max_w = 200;
						$dst_max_h = 350;
						if ($src_w > $dst_max_w) {
							$thumb_w=$dst_max_w;
							$thumb_h=$src_h*($dst_max_w/$src_w);
							if ($thumb_h > $dst_max_h) {
								$thumb_h = $dst_max_h;
								$thumb_w = $src_w*($dst_max_h/$src_h);
							}
						}
						elseif ($src_h > $dst_max_h) {
							$thumb_h=$dst_max_h;
							$thumb_w=$src_w*($dst_max_h/$src_h);
							if ($thumb_w > $dst_max_w) {
								$thumb_w = $dst_max_w;
								$thumb_h = $src_h*($dst_max_w/$src_w);
							}
						}
						else {
							if ($src_w > $src_h) {
								$thumb_w = $dst_max_w;
								$thumb_h = $src_h*($dst_max_w/$src_w);
							} elseif ($src_w < $src_h) {
								$thumb_h = $dst_max_h;
								$thumb_w = $src_w*($dst_max_h/$src_h);
							} else {
								if ($dst_max_w >= $dst_max_h) {
									$thumb_w=$dst_max_h;
									$thumb_h=$dst_max_h;
								} else {
									$thumb_w=$dst_max_w;
									$thumb_h=$dst_max_w;
								}
							}
						}
						$dstImage=ImageCreateTrueColor($thumb_w,$thumb_h);
						imagecopyresampled($dstImage,$srcImage,0,0,0,0,$thumb_w,$thumb_h,$src_w,$src_h);
						imagejpeg($dstImage,$dir . "/thumbnails/" . $file);
						imagedestroy($dstImage);
						imagedestroy($srcImage);
					}
				}
				closedir($dh);
			}
			else
				echo "couldn't open $dir";
		}
	break;

	default:
	break;
}


// Determine what work needs done..

// Check for old lists that need converted.
$bOldLists = false;
$result = mysql_list_tables($sDATABASE);
if (!$result) {
	print "DB Error, could not list tables\n";
	print 'MySQL Error: ' . mysql_error();
	exit;
}
while ($row = mysql_fetch_row($result)) {
	if ($row[0] == 'role_rle') $bOldLists = true;
}

// Check for missing custom person field entries
$sSQL = "SELECT '' FROM person_per";
$rsPeople = RunQuery($sSQL);
$sSQL = "SELECT '' FROM person_custom";
$rsCustom = RunQuery($sSQL);
if (mysql_num_rows($rsPeople) != mysql_num_rows($rsCustom))
	$bMissingCustomFields = true;

// Check if there are images but no thumbnails. (from old image handling routine)
$count = 0;
if ($dh = opendir('../Images/Person')) {
	while (($file = readdir($dh)) != false) {
		if (substr($file,-3) == "jpg")
			$count++;
	}
}
if ($dh = opendir('../Images/Person/thumbnails')) {
	while (($file = readdir($dh)) != false) {
		if (substr($file,-3) == "jpg")
			$count--;
	}
}
if ($count > 0) $bMissingThumbnails = true;

require "../Include/Header-Short.php";
?>

<br><br>
<center><h2>Upgrade-Helper:  A database maintenance utility for InfoCentral upgraders</h2></center>
<br><br>

<?php if ($bOldLists || $bMissingCustomFields || $bMissingThumbnails) { ?>
<table align="center" width="80%"><tr><td>

<?php if ($bMissingCustomFields) { ?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>" name="UpgradeHelper">
<input type="hidden" name="Operation" value="1">
<input type="submit" class="icButton" value="Perform">
Create custom fields table entries for everyone currently in the database.
</form>
<?php } ?>

<?php if ($bOldLists && $iOperation != 2) { ?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>" name="UpgradeHelper">
<input type="hidden" name="Operation" value="2">
<input type="submit" class="icButton" value="Perform">
Import your old Classifications, Family Roles, and Group Roles to the new Lists Master Table (list_lst).
</form>
<?php } ?>

<?php if ($bOldLists && $iOperation == 2) { ?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>" name="UpgradeHelper">
<input type="hidden" name="Operation" value="3">
&nbsp;&nbsp;&nbsp;<input type="submit" class="icButton" value="Perform">
Delete your old Classifications, Family Roles, and Group Roles tables.
</form>
<?php } ?>

<?php if ($bMissingThumbnails) { ?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>" name="UpgradeHelper">
<input type="hidden" name="Operation" value="4">
&nbsp;&nbsp;&nbsp;<input type="submit" class="icButton" value="Perform">
Create thumbnails of previously uploaded person and family pictures.
</form>
<?php } ?>

</td></tr></table>
<?php }
else echo "<center><h3>No additional maintenance required.</h3></center>";

require "../Include/Footer-Short.php";
?>
