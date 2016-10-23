<?php

require "Include/Config.php";
require "Include/Functions.php";

use ChurchCRM\Note;

$finalFileName = "";
$redirectURL = "";
$imageLocation = "";
$imageFor = "";

if (isset($_GET['PersonID'])) {
  $imageFor = "Person";
  $id = FilterInput($_GET["PersonID"], 'int');
  $redirectURL = "PersonView.php?PersonID=" . $id;
  $finalFileName = $id;
  $imageLocation = 'Images/Person/';
  deletePhotos("Person", $id);
} else if (isset($_GET['FamilyID'])) {
  $imageFor = "Family";
  $id = FilterInput($_GET["FamilyID"], 'int');
  $redirectURL = "FamilyView.php?FamilyID=" . $id;
  $finalFileName = $id;
  $imageLocation = 'Images/Family/';
  deletePhotos("Family", $id);
} else if (isset($_GET['GroupID'])) {
  $imageFor = "Group";
  $id = FilterInput($_GET["GroupID"], 'int');
  $redirectURL = "GroupView.php?GroupID=" . $id;
  $finalFileName = $id;
  $imageLocation = 'Images/Group/';
  deletePhotos("Group", $id);
}

$imageLocationThumb = $imageLocation . "thumbnails/";

$uploaded = false;
if ($_SESSION['bAddRecords'] || $bOkToEdit) {
  $foo = new upload($_FILES['file']);
  $foo->allowed = array('"image/png"', 'image/jpg', 'image/jpeg');

  if ($foo->uploaded) {
    $foo->file_new_name_body = $finalFileName;
    $foo->file_overwrite = true;
    $foo->Process($imageLocation);
    if (!$foo->processed) {
      echo 'error : ' . $foo->error;
    }
    
    $exif = exif_read_data($foo->file_dst_pathname);
    if ( !empty($exif['Orientation']) ) {
      switch ( $exif['Orientation'] ) {
        case 3:
          $foo->image_rotate = 180;
          break;
        
        case 6:
          $foo->image_rotate =  90;
          break;
        
        case 8:
          $foo->image_rotate = 270;
          break;
      }
    }
    
    $foo->file_new_name_body = $finalFileName;
    $foo->file_overwrite = true;
    $foo->image_resize = true;
    $foo->image_ratio_fill = true;
    $foo->image_y = 250;
    $foo->image_x = 250;
    $foo->Process($imageLocationThumb);
    if (!$foo->processed) {
      echo 'error : ' . $foo->error;
    } else {
      $note = new Note();
      $note->setText("Profile Image Uploaded");
      $note->setType("photo");
      $note->setEntered($_SESSION['iUserID']);
      if (isset($_GET['PersonID'])) {
        $note->setPerId($id);
        $note->save();
      } else if (isset($_GET['FamilyID'])) {
        $note->setFamId($id);
        $note->save();
      }
      $uploaded = true;
    }
  } else {
    echo $foo->error;
  }
  $foo->Clean();
}

if ($uploaded) {
  Redirect($redirectURL . "&ProfileImageUploaded=true");
} else {
  Redirect($redirectURL . "&ProfileImageUploadedError=true");
}
?>
