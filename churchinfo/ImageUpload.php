<?php

require "Include/Config.php";
require "Include/Functions.php";
require "Include/PersonFunctions.php";
require "Include/class.upload.php";

$finalFileName="";
$redirectURL="";
$imageLocation="";
$imageLocationThumb="";


if (isset($_GET['PersonID'])) {
    $personId = FilterInput($_GET["PersonID"],'int');
    $redirectURL="PersonView.php?PersonID=".$personId;
    $finalFileName = $personId;
    $imageLocation='Images/Person/';
    $imageLocationThumb='Images/Person/thumbnails/';

}

$uploaded = false;
if ($_SESSION['bAddRecords'] || $bOkToEdit ) {
    $foo = new Upload($_FILES['file']);
    $foo->file_max_size = '102400'; // 100KB
    $foo->allowed = array('"image/png"','image/jpg','image/jpeg', 'image/gif');
    echo "here1";
    if ($foo->uploaded) {
        echo "here2";
        $foo->file_new_name_body = $finalFileName;
        $foo->file_overwrite = true;
        $foo->Process($imageLocation);
        if (!$foo->processed) {
            echo 'error : ' . $foo->error;
        }
        $foo->file_new_name_body = $finalFileName;
        $foo->file_overwrite = true;
        $foo->image_resize          = true;
        $foo->image_ratio_fill      = true;
        $foo->image_y               = 150;
        $foo->image_x               = 150;
        $foo->Process($imageLocationThumb);
        if (!$foo->processed) {
            echo 'error : ' . $foo->error;
        } else {
            $uploaded = true;
        }
    }
    $foo->Clean();
    echo $foo->log;
}

if ($uploaded) {
    Redirect($redirectURL . "&ProfileImageUploaded=true");
} else {
    Redirect($redirectURL . "&ProfileImageUploadedError=true");
}
?>