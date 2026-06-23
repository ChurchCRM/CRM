<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';
require_once __DIR__ . '/Include/QuillEditorHelper.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
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
    $sBackPage = Person::getViewURIForId($iPersonID);
} else {
    $sBackPage = Family::getFamilyViewURIForId($iFamilyID);
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
        $currentUser = AuthenticationManager::getCurrentUser();
        // Are we adding or editing?
        if ($iNoteID <= 0) {
            // Write gate for new person note: canEditPerson() (EditRecords/Admin=any person;
            // EditSelf=own family only). Notes=1 alone is not sufficient for person notes.
            // The redirectHomeIfFalse(isNotesEnabled()) at the top already ensures the user
            // has Notes=1; canEditPerson() is the additional object-level scope check.
            if ($iPersonID > 0) {
                $targetPerson = PersonQuery::create()->findPk($iPersonID);
                $targetFamId  = $targetPerson !== null ? (int) $targetPerson->getFamId() : 0;
                if (!$currentUser->canEditPerson($iPersonID, $targetFamId)) {
                    $sNoteTextError = '<br><span class="text-danger">' . gettext('You do not have permission to add a note for this person.') . '</span>';
                    $bErrorFlag = true;
                }
            } elseif ($iFamilyID > 0 && !$currentUser->canWriteNoteOnFamily($iFamilyID)) {
                $sNoteTextError = '<br><span class="text-danger">' . gettext('You do not have permission to add a note for this family.') . '</span>';
                $bErrorFlag = true;
            }

            if (!$bErrorFlag) {
                $note = new Note();
                $note->setPerId($iPersonID);
                $note->setFamId($iFamilyID);
                $note->setPrivate($bPrivate);
                $note->setText($sNoteText);
                $note->setType('note');
                $note->setEntered($currentUser->getId());
                $note->save();
            }
        } else {
            // Fix #A: guard against stale/invalid NoteID in the POST (edit) path.
            // If the note was deleted between page load and submit, findPk() returns
            // null and calling getEnteredBy() on it would be a fatal error.
            $note = NoteQuery::create()->findPk($iNoteID);
            if ($note === null) {
                $_SESSION['sGlobalMessage'] = gettext('The note you are trying to edit no longer exists.');
                $_SESSION['sGlobalMessageClass'] = 'danger';
                RedirectUtils::redirect($sBackPage ?: SystemURLs::getRootPath() . '/');
            }

            // Admin can edit any note; author can edit their own note.
            if (!$currentUser->isAdmin() && $note->getEnteredBy() !== $currentUser->getId()) {
                $sNoteTextError = '<br><span class="text-danger">' . gettext('You do not have permission to edit this note.') . '</span>';
                $bErrorFlag = true;
            } else {
                $note->setPrivate($bPrivate);
                $note->setText($sNoteText);
                $note->setDateLastEdited(new DateTime());
                $note->setEditedBy($currentUser->getId());
                $note->save();
            }
        }

        // Send them back to wherever they came from
        RedirectUtils::redirect($sBackPage);
    }
} else {
    // Are we loading an existing note for editing?
    if (isset($_GET['NoteID'])) {
        // Get the NoteID from the querystring
        $iNoteID = InputUtils::legacyFilterInput($_GET['NoteID'], 'int');
        $dbNote = NoteQuery::create()->findPk($iNoteID);

        // Fix (load path): guard against non-existent NoteID.
        // Without this check, calling getText() / getPrivate() etc. on null is a fatal error.
        if ($dbNote === null) {
            $_SESSION['sGlobalMessage'] = gettext('Note not found.');
            $_SESSION['sGlobalMessageClass'] = 'danger';
            RedirectUtils::redirect($sBackPage ?: SystemURLs::getRootPath() . '/');
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        // Admin can edit any note; all others can only edit their own notes.
        if (!$currentUser->isAdmin() && $dbNote->getEnteredBy() !== $currentUser->getId()) {
            $_SESSION['sGlobalMessage'] = gettext('You do not have permission to edit this note.');
            $_SESSION['sGlobalMessageClass'] = 'danger';
            RedirectUtils::redirect($sBackPage ?: SystemURLs::getRootPath() . '/');
        }

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
        <?= getQuillEditorContainer('NoteText', 'NoteTextInput', $sNoteText, 'w-100', 'tall') ?>
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
