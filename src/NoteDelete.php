<?php
/*******************************************************************************
 *
 *  filename    : NoteDelete.php
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

// Security: User must have Notes permission
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bNotes'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Note Delete Confirmation");

//Get the NoteID from the querystring
$iNoteID = FilterInput($_GET["NoteID"],'int');

//Get the data on this note
$sSQL = "SELECT * FROM note_nte WHERE nte_ID = " . $iNoteID;
$rsNote = RunQuery($sSQL);
extract(mysql_fetch_array($rsNote));

//If deleting a note for a person, set the PersonView page as the redirect
if ($nte_per_ID > 0)
{
	$sReroute = "PersonView.php?PersonID=" . $nte_per_ID;
}

//If deleting a note for a family, set the FamilyView page as the redirect
elseif ($nte_fam_ID > 0)
{
	$sReroute = "FamilyView.php?FamilyID=" . $nte_fam_ID;
}

//Do we have confirmation?
if (isset($_GET["Confirmed"]))
{
	//Delete the specified Person record
	$sSQL = "DELETE FROM note_nte WHERE nte_ID = " . $iNoteID;
	RunQuery($sSQL);

	//Send back to the page they came from
	Redirect($sReroute);
}

require "Include/Header.php";

?>
<div class="box box-warning">
  <div class="box-header with-border">
	<?= gettext("Please confirm deletion of this note:") ?>
  </div>
  <div class="box-body">
    <?= $nte_Text ?>
  </div>
  <div class="box-footer">
    <a class="btn btn-default" href="<?php echo $sReroute ?>"><?= gettext("Cancel") ?></a>
  	<a class="btn btn-danger" href="NoteDelete.php?Confirmed=Yes&NoteID=<?php echo $iNoteID ?>"><?= gettext("Yes, delete this record") ?></a> <?= gettext("(this action cannot be undone)") ?>
  </div>

<?php require "Include/Footer.php" ?>
