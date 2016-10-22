<?php

require "Include/Config.php";
require "Include/Functions.php";

use ChurchCRM\Note;

$redirectURL="Menu.php";
$deleted = false;

if ($_SESSION['bAddRecords'] || $bOkToEdit ) {
    $note = new Note();
    $note->setText("Profile Image Deleted");
    $note->setType("photo");
    $note->setEntered($_SESSION['iUserID']);
    if (isset($_GET['PersonID'])) {
        $id = FilterInput($_GET["PersonID"], 'int');
        $deleted = deletePhotos("Person", $id);
        $note->setPerId($id);
        $note->save();
        $redirectURL = "PersonView.php?PersonID=" . $id;
    } else if (isset($_GET['FamilyID'])) {
        $id = FilterInput($_GET["FamilyID"], 'int');
        $deleted = deletePhotos("Family", $id);
        $note->setFamId($id);
        $note->save();
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
