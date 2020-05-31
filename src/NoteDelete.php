<?php
/*******************************************************************************
 *
 *  filename    : NoteDelete.php
 *  last change : 2003-01-07
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\NoteQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

// Security: User must have Notes permission
// Otherwise, re-direct them to the main menu.
if (!AuthenticationManager::GetCurrentUser()->isNotesEnabled()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

//Set the page title
$sPageTitle = gettext('Note Delete Confirmation');

//Get the NoteID from the querystring
$iNoteID = InputUtils::LegacyFilterInput($_GET['NoteID'], 'int');

//Get the data on this note
$sSQL = 'SELECT * FROM note_nte WHERE nte_ID = '.$iNoteID;
$rsNote = RunQuery($sSQL);
extract(mysqli_fetch_array($rsNote));

//If deleting a note for a person, set the PersonView page as the redirect
if ($nte_per_ID > 0) {
    $sReroute = 'PersonView.php?PersonID='.$nte_per_ID;
}

//If deleting a note for a family, set the FamilyView page as the redirect
elseif ($nte_fam_ID > 0) {
    $sReroute = 'FamilyView.php?FamilyID='.$nte_fam_ID;
}

//Do we have confirmation?
if (isset($_GET['Confirmed'])) {
    $note = NoteQuery::create()->findPk($iNoteID);
    $note->delete();

    //Send back to the page they came from
    RedirectUtils::Redirect($sReroute);
}

require 'Include/Header.php';

?>
<div class="box box-warning">
  <div class="box-header with-border">
	<?= gettext('Please confirm deletion of this note') ?>:
  </div>
  <div class="box-body">
    <?= $nte_Text ?>
  </div>
  <div class="box-footer">
    <a class="btn btn-default" href="<?php echo $sReroute ?>"><?= gettext('Cancel') ?></a>
  	<a class="btn btn-danger" href="NoteDelete.php?Confirmed=Yes&NoteID=<?php echo $iNoteID ?>"><?= gettext('Yes, delete this record') ?></a> <?= gettext('(this action cannot be undone)') ?>
  </div>

<?php require 'Include/Footer.php' ?>
