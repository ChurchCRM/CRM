<?php

class NoteService
{

  function addNote($personID, $familyID, $private, $text)
  {
    $sSQL = "INSERT INTO note_nte (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered)
                VALUES (" . $personID . "," . $familyID . "," . $private . ",'" . $text . "'," .
      $_SESSION['iUserID'] . ",'" . date("YmdHis") . "')";

    //Execute the SQL
    RunQuery($sSQL);

  }

  function updateNote($noteId, $private, $text)
  {
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
    $sSQL = "DELETE FROM note_nte WHERE nte_ID = " . $noteId;
    return RunQuery($sSQL);
  }

}
