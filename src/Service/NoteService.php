<?php

class NoteService
{

  function addNote($personID, $familyID, $private, $text, $type = "system")
  {
    requireUserGroupMembership("bNotes");
    $sSQL = "INSERT INTO note_nte (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
                VALUES (" . $personID . "," . $familyID . "," . $private . ",'" . $text . "'," .
      $_SESSION['iUserID'] . ",'" . date("YmdHis") . "', '". $type ."')";

    //Execute the SQL
    RunQuery($sSQL);

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

  // Get the notes for this person
  function getNotesByPerson($personId, $admin)
  {
    $sSQL = "SELECT nte_ID, nte_Private, nte_Text, nte_Type,
	              nte_EnteredBy , nte_DateEntered, nte_EnteredBy, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName,
	              nte_EditedBy , nte_DateLastEdited, nte_EditedBy, b.per_FirstName AS EditedFirstName, b.per_LastName AS EditedLastName
              FROM note_nte
                LEFT JOIN person_per a ON nte_EnteredBy = a.per_ID
                LEFT JOIN person_per b ON nte_EditedBy = b.per_ID
              WHERE nte_per_ID = " . $personId;

    // Admins should see all notes, private or not.  Otherwise, only get notes marked non-private or private to the current user.
    if (!$admin) {
      $sSQL .= " AND (nte_Private = 0 OR nte_Private = " . $_SESSION['iUserID'] . ")";
    }
    $sSQL .= " order by nte_DateEntered desc";
    $rsNotes = RunQuery($sSQL);
    $notesArray = array();
    while ($aRow = mysql_fetch_array($rsNotes)) {
      extract($aRow);
      $note['id'] = $nte_ID;
      $note['private'] = $nte_Private;
      $note['text'] = $nte_Text;
      $note['type'] = $nte_Type;

      if ($nte_DateLastEdited != "") {
        $note['lastUpdateDatetime'] = $nte_DateLastEdited;
        $note['lastUpdateByName'] = $EditedFirstName . " " . $EditedLastName;
        $note['lastUpdateById'] = $nte_EditedBy;
      } else {
        $note['lastUpdateDatetime'] = $nte_DateEntered;
        $note['lastUpdateByName'] = $EnteredFirstName . " " . $EnteredLastName;
        $note['lastUpdateById'] = $nte_EnteredBy;
      }
      array_push($notesArray, $note);
    }
    return $notesArray;
  }


  // Get the notes for this person
  function getNotesByFamily($familyId, $admin)
  {
    $sSQL = "SELECT nte_ID, nte_Private, nte_Text, nte_Type,
	              nte_EnteredBy , nte_DateEntered, nte_EnteredBy, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName,
	              nte_EditedBy , nte_DateLastEdited, nte_EditedBy, b.per_FirstName AS EditedFirstName, b.per_LastName AS EditedLastName
              FROM note_nte
                LEFT JOIN person_per a ON nte_EnteredBy = a.per_ID
                LEFT JOIN person_per b ON nte_EditedBy = b.per_ID
              WHERE nte_fam_ID = " . $familyId;

    // Admins should see all notes, private or not.  Otherwise, only get notes marked non-private or private to the current user.
    if (!$admin) {
      $sSQL .= " AND (nte_Private = 0 OR nte_Private = " . $_SESSION['iUserID'] . ")";
    }
    $sSQL .= " order by nte_DateEntered desc";
    $rsNotes = RunQuery($sSQL);
    $notesArray = array();
    while ($aRow = mysql_fetch_array($rsNotes)) {
      extract($aRow);
      $note['id'] = $nte_ID;
      $note['private'] = $nte_Private;
      $note['text'] = $nte_Text;
      $note['type'] = $nte_Type;

      if ($nte_DateLastEdited != "") {
        $note['lastUpdateDatetime'] = $nte_DateLastEdited;
        $note['lastUpdateByName'] = $EditedFirstName . " " . $EditedLastName;
        $note['lastUpdateById'] = $nte_EditedBy;
      } else {
        $note['lastUpdateDatetime'] = $nte_DateEntered;
        $note['lastUpdateByName'] = $EnteredFirstName . " " . $EnteredLastName;
        $note['lastUpdateById'] = $nte_EnteredBy;
      }
      array_push($notesArray, $note);
    }
    return $notesArray;
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
