<?php

require "Include/Config.php";
require "Include/Functions.php";

use ChurchCRM\Note;
use ChurchCRM\util\PhotoUtils;

$finalFileName = "";
$redirectURL = "";
$imageLocation = "";
$imageFor = "";

if ($_SESSION['bAddRecords'] || $bOkToEdit) {
  if (isset($_GET['PersonID'])) {
    $imageFor = "Person";
    $id = FilterInput($_GET["PersonID"], 'int');
    $redirectURL = "PersonView.php?PersonID=" . $id;
    $finalFileName = $id;
    $imageLocation = 'Images/Person/';
    PhotoUtils::deletePhotos("Person", $id);
    $uploaded = PhotoUtils::setImageFromUplad("Person", $id, $_FILES['file']);
  } else if (isset($_GET['FamilyID'])) {
    $imageFor = "Family";
    $id = FilterInput($_GET["FamilyID"], 'int');
    $redirectURL = "FamilyView.php?FamilyID=" . $id;
    $finalFileName = $id;
    $imageLocation = 'Images/Family/';
    PhotoUtils::deletePhotos("Family", $id);
    $uploaded =  PhotoUtils::setImageFromUplad("Family", $id, $_FILES['file']);
  } else if (isset($_GET['GroupID'])) {
    $imageFor = "Group";
    $id = FilterInput($_GET["GroupID"], 'int');
    $redirectURL = "GroupView.php?GroupID=" . $id;
    $finalFileName = $id;
    $imageLocation = 'Images/Group/';
    PhotoUtils::deletePhotos("Group", $id);
    $uploaded =  PhotoUtils::setImageFromUplad("Group", $id, $_FILES['file']);
  }
}

if ($uploaded) {
  Redirect($redirectURL . "&ProfileImageUploaded=true");
} else {
  Redirect($redirectURL . "&ProfileImageUploadedError=true");
}
?>
