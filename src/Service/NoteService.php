<?php

use ChurchCRM\Note;

class NoteService
{

  function addNote($personID, $familyID, $private, $text, $type = "system")
  {
    requireUserGroupMembership("bNotes");

    $note = new Note();
    $note->setEnteredBy($_SESSION['iUserID']);
    $note->setPerId($personID);
    $note->setFamId($familyID);
    $note->setPrivate($private);
    $note->setText($text);
    $note->setType($type);
    $note->setDateLastEdited(date("YmdHis"));
    $note->save();

  }

  function updateNote($noteId, $private, $text)
  {
    requireUserGroupMembership("bNotes");
    $sSQL = "UPDATE note_nte SET
                nte_Private = " . $private . ",
                nte_Text = '" . $text . "' ,
                nte_DateLastEdited = '" . date("YmdHis") . "',
                nte_EditedBy = " . $_SESSION['iUserID'] . "
            WHERE nte_ID = " . $noteId;

    //Execute the SQL
    RunQuery($sSQL);

  }

  function getNoteById($noteId)
  {
    requireUserGroupMembership("bNotes");
    $sSQL = "SELECT * FROM note_nte WHERE nte_ID = " . $noteId;
    $rsNote = RunQuery($sSQL);
    extract(mysql_fetch_array($rsNote));
    $note['id'] = $nte_ID;
    $note['familyId'] = $nte_fam_ID;
    $note['personId'] = $nte_per_ID;
    $note['private'] = $nte_Private;
    $note['text'] = $nte_Text;
    $note['entered'] = $nte_DateEntered;
    $note['enteredById'] = $nte_EnteredBy;
    $note['edited'] = $nte_DateLastEdited;
    $note['editedById'] = $nte_EditedBy;
    return $note;
  }

  function deleteNoteById($noteId)
  {
    requireUserGroupMembership("bNotes");
    $sSQL = "DELETE FROM note_nte WHERE nte_ID = " . $noteId;
    return RunQuery($sSQL);
  }

}
