<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';
require_once __DIR__ . '/Include/QuillEditorHelper.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security: User must have Notes permission
// Otherwise, re-direct them to the main menu.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isNotesEnabled(), 'Notes');

$sPageTitle = gettext('Note Editor');
$sPageSubtitle = gettext('Add or edit notes for people and families');

if (isset($_GET['PersonID'])) {
    $iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');
} else {
    $iPersonID = 0;
}

if (isset($_GET['FamilyID'])) {
    $iFamilyID = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');
} else {
    $iFamilyID = 0;
}

// To which page do we send the user if they cancel?
if ($iPersonID > 0) {
    $sBackPage = 'PersonView.php?PersonID=' . $iPersonID;
} else {
    $sBackPage = 'v2/family/' . $iFamilyID;
}

// Has the form been submitted?
if (isset($_POST['Submit'])) {
    //Initialize the ErrorFlag
    $bErrorFlag = false;

    // Assign all variables locally
    $iNoteID = InputUtils::legacyFilterInput($_POST['NoteID'], 'int');
    $sNoteText = InputUtils::sanitizeHTML($_POST['NoteTextInput']);

    // If they didn't check the private box, set the value to 0
    if (isset($_POST['Private'])) {
        $bPrivate = 1;
    } else {
        $bPrivate = 0;
    }

    // Did they enter text for the note?
    if ($sNoteText === '') {
        $sNoteTextError = '<br><span class="text-danger">You must enter text for this note.</span>';
        $bErrorFlag = true;
    }

    // Were there any errors?
    if (!$bErrorFlag) {
        // Are we adding or editing?
        if ($iNoteID <= 0) {
            $note = new Note();
            $note->setPerId($iPersonID);
            $note->setFamId($iFamilyID);
            $note->setPrivate($bPrivate);
            $note->setText($sNoteText);
            $note->setType('note');
            $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
            $note->save();
        } else {
            $note = NoteQuery::create()->findPk($iNoteID);
            $note->setPrivate($bPrivate);
            $note->setText($sNoteText);
            $note->setDateLastEdited(new DateTime());
            $note->setEditedBy(AuthenticationManager::getCurrentUser()->getId());
            $note->save();
        }

        // Send them back to wherever they came from
        RedirectUtils::redirect($sBackPage);
    }
} else {
    // Are we adding or editing?
    if (isset($_GET['NoteID'])) {
        // Get the NoteID from the querystring
        $iNoteID = InputUtils::legacyFilterInput($_GET['NoteID'], 'int');
        $dbNote = NoteQuery::create()->findPk($iNoteID);

        // Assign everything locally
        $sNoteText = $dbNote->getText();
        $bPrivate = $dbNote->getPrivate();
        $iPersonID = $dbNote->getPerId();
        $iFamilyID = $dbNote->getFamId();
    }
}
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Note Editor')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="PersonID" value="<?= $iPersonID ?>">
      <input type="hidden" name="FamilyID" value="<?= $iFamilyID ?>">
      <input type="hidden" name="NoteID" value="<?= $iNoteID ?>">

      <div class="mb-3">
        <?= getQuillEditorContainer('NoteText', 'NoteTextInput', $sNoteText, 'w-100', '300px') ?>
        <?= $sNoteTextError ?>
      </div>

      <div class="mb-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="1" name="Private" id="Private" <?php if (($bPrivate ?? 0) !== 0) {
              echo 'checked';
          } ?>>
          <label class="form-check-label" for="Private"><?= gettext('Private') ?></label>
        </div>
      </div>

      <div class="d-flex gap-2">
        <input type="submit" class="btn btn-success" name="Submit" value="<?= gettext('Save') ?>">
        <input type="button" class="btn btn-secondary" name="Cancel" value="<?= gettext('Cancel') ?>" onclick="javascript:document.location='<?= $sBackPage ?>';">
      </div>
    </form>
  </div>
</div>

<?= getQuillEditorInitScript('NoteText', 'NoteTextInput', gettext("Enter note text here...")) ?>
<?php
require_once __DIR__ . '/Include/Footer.php';
