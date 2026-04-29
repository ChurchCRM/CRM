<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Notes permission
// Otherwise, re-direct them to the main menu.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isNotesEnabled(), 'Notes');

$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Note');

// Get the NoteID from the querystring
$iNoteID = InputUtils::legacyFilterInput($_GET['NoteID'], 'int');

// Get the data on this note
$note = NoteQuery::create()->findOneById($iNoteID);

// If deleting a note for a person, set the PersonView page as the redirect
if ($note->getPerId() > 0) {
    $sReroute = Person::getViewURIForId($note->getPerId());
} elseif ($note->getFamId() > 0) {
    // If deleting a note for a family, set the FamilyView page as the redirect
    $sReroute = Family::getFamilyViewURIForId($note->getFamId());
}

// Do we have confirmation?
if (isset($_GET['Confirmed'])) {
    $note->delete();

    // Send back to the page they came from
    RedirectUtils::redirect($sReroute);
}

require_once __DIR__ . '/Include/Header.php';

?>
<div class="card border-top border-warning border-3">
  <div class="card-header d-flex align-items-center">
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
require_once __DIR__ . '/Include/Footer.php';
