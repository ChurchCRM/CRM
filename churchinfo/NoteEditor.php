<?php
/*******************************************************************************
 *
 *  filename    : NoteEditor.php
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

// Security: User must have Notes permission
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bNotes'])
{
	Redirect("Menu.php");
	exit;
}

//Set the page title
$sPageTitle = gettext("Note Editor");

if (isset($_GET["PersonID"]))
	$iPersonID = FilterInput($_GET["PersonID"],'int');
else
	$iPersonID = 0;

if (isset($_GET["FamilyID"]))
	$iFamilyID = FilterInput($_GET["FamilyID"],'int');
else
	$iFamilyID = 0;

//To which page do we send the user if they cancel?
if ($iPersonID > 0)
{
	$sBackPage = "PersonView.php?PersonID=" . $iPersonID;
}
else
{
	$sBackPage = "FamilyView.php?FamilyID=" . $iFamilyID;
}

//Has the form been submitted?
if (isset($_POST["Submit"]))
{
	//Initialize the ErrorFlag
	$bErrorFlag = false;

	//Assign all variables locally
	$iNoteID = FilterInput($_POST["NoteID"],'int');
	$sNoteText = FilterInput($_POST["NoteText"],'htmltext');

	//If they didn't check the private box, set the value to 0
	if (isset($_POST["Private"]))
		$bPrivate = 1;
	else
		$bPrivate = 0;

	//Did they enter text for the note?
	if ($sNoteText == "")
	{
		$sNoteTextError = "<br><span style=\"color: red;\">You must enter text for this note.</span>";
		$bErrorFlag = True;
	}

	//Were there any errors?
	if (!$bErrorFlag)
	{
		//Are we adding or editing?
		if ($iNoteID <= 0)
		{
			$sSQL = "INSERT INTO note_nte (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered) VALUES (" . $iPersonID . "," . $iFamilyID . "," . $bPrivate . ",'" . $sNoteText . "'," . $_SESSION['iUserID'] . ",'" . date("YmdHis") . "')";
		}

		else
		{
			$sSQL = "UPDATE note_nte SET nte_Private = " . $bPrivate . ", nte_Text = '" . $sNoteText . "', nte_DateLastEdited = '" . date("YmdHis") . "', nte_EditedBy = " . $_SESSION['iUserID'] . " WHERE nte_ID = " . $iNoteID;
		}

		//Execute the SQL
		RunQuery($sSQL);

		//Send them back to whereever they came from
		Redirect($sBackPage);
	}
}

else
{
	//Are we adding or editing?
	if (isset($_GET["NoteID"]))
	{
		//Get the NoteID from the querystring
		$iNoteID = FilterInput($_GET["NoteID"],'int');

		//Get the data for this note
		$sSQL = "SELECT * FROM note_nte WHERE nte_ID = " . $iNoteID;
		$rsNote = RunQuery($sSQL);
		extract(mysql_fetch_array($rsNote));

		//Assign everything locally
		$sNoteText = $nte_Text;
		$bPrivate = $nte_Private;
		$iPersonID = $nte_per_ID;
		$iFamilyID = $nte_fam_ID;
	}
}

require "Include/Header.php";

?>

<form method="post">

<p align="center">
	<input type="hidden" name="PersonID" value="<?php echo $iPersonID; ?>">
	<input type="hidden" name="FamilyID" value="<?php echo $iFamilyID; ?>">
	<input type="hidden" name="NoteID" value="<?php echo $iNoteID; ?>">
	<textarea name="NoteText" cols="70" rows="10"><?php echo $sNoteText; ?></textarea>
	<?php echo $sNoteTextError; ?>
</p>

<p align="center">
	<input type="checkbox" value="1" name="Private" <?php if ($nte_Private != 0) { echo "checked"; } ?>>&nbsp;<?php echo gettext("Private"); ?>
</p>

<p align="center">
	<input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Save") . '"'; ?>>
	&nbsp;
	<input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='<?php echo $sBackPage; ?>';">
	</form>
</p>

<?php
require "Include/Footer.php";
?>
