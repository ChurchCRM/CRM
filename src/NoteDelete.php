<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Notes permission
// Otherwise, re-direct them to the main menu.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isNotesEnabled(), 'Notes');

$sPageTitle = gettext('Note Delete Confirmation');

// Get the NoteID from the querystring
$iNoteID = InputUtils::legacyFilterInput($_GET['NoteID'], 'int');

// Get the data on this note
$note = NoteQuery::create()->findOneById($iNoteID);

// If deleting a note for a person, set the PersonView page as the redirect
if ($note->getPerId() > 0) {
    $sReroute = 'PersonView.php?PersonID=' . $note->getPerId();
} elseif ($note->getFamId() > 0) {
    // If deleting a note for a family, set the FamilyView page as the redirect
    $sReroute = 'v2/family/' . $note->getFamId();
}

// Do we have confirmation?
if (isset($_GET['Confirmed'])) {
    $note->delete();

    // Send back to the page they came from
    RedirectUtils::redirect($sReroute);
}

require_once 'Include/Header.php';

?>
<div class="card card-warning">
  <div class="card-header with-border">
    <?= gettext('Please confirm deletion of this note') ?>:
  </div>
  <div class="card-body">
    <?= $note->getText() ?>
  </div>
  <div class="card-footer">
    <a class="btn btn-secondary" href="<?php echo $sReroute ?>"><?= gettext('Cancel') ?></a>
    <a class="btn btn-danger" href="NoteDelete.php?Confirmed=Yes&NoteID=<?php echo $iNoteID ?>"><?= gettext('Yes, delete this record') ?></a> <?= gettext('(this action cannot be undone)') ?>
  </div>
<?php
require_once 'Include/Footer.php';
