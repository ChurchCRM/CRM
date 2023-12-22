<?php

/*******************************************************************************
 *
 *  filename    : NoteDelete.php
 *  last change : 2003-01-07
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Notes permission
// Otherwise, re-direct them to the main menu.
if (!AuthenticationManager::getCurrentUser()->isNotesEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Set the page title
$sPageTitle = gettext('Note Delete Confirmation');

//Get the NoteID from the querystring
$iNoteID = InputUtils::legacyFilterInput($_GET['NoteID'], 'int');

//Get the data on this note
$sSQL = 'SELECT * FROM note_nte WHERE nte_ID = ' . $iNoteID;
$rsNote = RunQuery($sSQL);
extract(mysqli_fetch_array($rsNote));

//If deleting a note for a person, set the PersonView page as the redirect
if ($nte_per_ID > 0) {
    $sReroute = 'PersonView.php?PersonID=' . $nte_per_ID;
} elseif ($nte_fam_ID > 0) {
    //If deleting a note for a family, set the FamilyView page as the redirect
    $sReroute = 'v2/family/' . $nte_fam_ID;
}

//Do we have confirmation?
if (isset($_GET['Confirmed'])) {
    $note = NoteQuery::create()->findPk($iNoteID);
    $note->delete();

    //Send back to the page they came from
    RedirectUtils::redirect($sReroute);
}

require 'Include/Header.php';

?>
<div class="card card-warning">
  <div class="card-header with-border">
    <?= gettext('Please confirm deletion of this note') ?>:
  </div>
  <div class="card-body">
    <?= $nte_Text ?>
  </div>
  <div class="card-footer">
    <a class="btn btn-default" href="<?php echo $sReroute ?>"><?= gettext('Cancel') ?></a>
    <a class="btn btn-danger" href="NoteDelete.php?Confirmed=Yes&NoteID=<?php echo $iNoteID ?>"><?= gettext('Yes, delete this record') ?></a> <?= gettext('(this action cannot be undone)') ?>
  </div>

<?php require 'Include/Footer.php' ?>
