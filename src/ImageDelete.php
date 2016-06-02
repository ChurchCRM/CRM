<?php

require "Include/Config.php";
require "Include/Functions.php";
require "Service/NoteService.php";

$noteService = new NoteService();
$redirectURL="Menu.php";
$deleted = false;

if ($_SESSION['bAddRecords'] || $bOkToEdit ) {
    if (isset($_GET['PersonID'])) {
        $id = FilterInput($_GET["PersonID"], 'int');
        $deleted = deletePhotos("Person", $id);
        $noteService->addNote($id, "0", 0, "Profile Image Deleted", "photo");
        $redirectURL = "PersonView.php?PersonID=" . $id;
    } else if (isset($_GET['FamilyID'])) {
        $id = FilterInput($_GET["FamilyID"], 'int');
        $deleted = deletePhotos("Family", $id);
        $noteService->addNote("0", $id, 0, "Profile Image Deleted", "photo");
        $redirectURL = "FamilyView.php?FamilyID=" . $id;
    } else if (isset($_GET['GroupID'])) {
        $id = FilterInput($_GET["GroupID"], 'int');
        $deleted = deletePhotos("Group", $id);
        $redirectURL = "GroupView.php?GroupID=" . $id;
    }
    if ($deleted) {
        $redirectURL = $redirectURL."&ProfileImageDeleted=true";
    }
}

Redirect($redirectURL);

?>
