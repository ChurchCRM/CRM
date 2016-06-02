<?php
/*******************************************************************************
 *
 *  filename    : NoteEditor.php
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
require "Service/NoteService.php";

// Security: User must have Notes permission
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bNotes']) {
  Redirect("Menu.php");
  exit;
}

$noteService = new NoteService();

//Set the page title
$sPageTitle = gettext("Note Editor");

if (isset($_GET["PersonID"]))
  $iPersonID = FilterInput($_GET["PersonID"], 'int');
else
  $iPersonID = 0;

if (isset($_GET["FamilyID"]))
  $iFamilyID = FilterInput($_GET["FamilyID"], 'int');
else
  $iFamilyID = 0;

//To which page do we send the user if they cancel?
if ($iPersonID > 0) {
  $sBackPage = "PersonView.php?PersonID=" . $iPersonID;
} else {
  $sBackPage = "FamilyView.php?FamilyID=" . $iFamilyID;
}

//Has the form been submitted?
if (isset($_POST["Submit"])) {
  //Initialize the ErrorFlag
  $bErrorFlag = false;

  //Assign all variables locally
  $iNoteID = FilterInput($_POST["NoteID"], 'int');
  $sNoteText = FilterInput($_POST["NoteText"], 'htmltext');

  //If they didn't check the private box, set the value to 0
  if (isset($_POST["Private"]))
    $bPrivate = 1;
  else
    $bPrivate = 0;

  //Did they enter text for the note?
  if ($sNoteText == "") {
    $sNoteTextError = "<br><span style=\"color: red;\">You must enter text for this note.</span>";
    $bErrorFlag = True;
  }

  //Were there any errors?
  if (!$bErrorFlag) {
    //Are we adding or editing?
    if ($iNoteID <= 0) {
      $noteService->addNote($iPersonID, $iFamilyID, $bPrivate, $sNoteText, "note");
    } else {
      $noteService->updateNote($iNoteID, $bPrivate, $sNoteText);
    }

    //Send them back to whereever they came from
    Redirect($sBackPage);
  }
} else {
  //Are we adding or editing?
  if (isset($_GET["NoteID"])) {
    //Get the NoteID from the querystring
    $iNoteID = FilterInput($_GET["NoteID"], 'int');

    $note = $noteService->getNoteById($iNoteID);
    //Assign everything locally
    $sNoteText = $note['text'];
    $bPrivate = $note['private'];
    $iPersonID = $note['personId'];
    $iFamilyID = $note['familyId'];
  }
}

require "Include/Header.php";

?>
<form method="post">
  <div class="box box-primary">
    <div class="box-body">

      <p align="center">
        <input type="hidden" name="PersonID" value="<?= $iPersonID ?>">
        <input type="hidden" name="FamilyID" value="<?= $iFamilyID ?>">
        <input type="hidden" name="NoteID" value="<?= $iNoteID ?>">
        <textarea name="NoteText" style="width: 100%" rows="10"><?= $sNoteText ?></textarea>
        <?= $sNoteTextError ?>
      </p>

      <p align="center">
        <input type="checkbox" value="1" name="Private" <?php if ($bPrivate != 0) {
          echo "checked";
        } ?>>&nbsp;<?= gettext("Private") ?>
      </p>
    </div>
  </div>
  <p align="center">
    <input type="submit" class="btn btn-success" name="Submit" value="<?= gettext("Save") ?>">
    &nbsp;
    <input type="button" class="btn" name="Cancel" value="<?= gettext("Cancel") ?>" onclick="javascript:document.location='<?= $sBackPage ?>';">

  </p>
</form>
<?php require "Include/Footer.php" ?>
