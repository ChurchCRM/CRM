<?php

function deletePersonPhoto($personId) {
    $validextensions = array("jpeg", "jpg", "png");
    $finalFileName = "Images/Person/" . $personId;
    $finalFileNameThumb = "Images/Person/thumbnails/" . $personId;
    while (list(, $ext) = each($validextensions)) {
        $tmpFile = $finalFileName .".".$ext;
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        $tmpFile = $finalFileNameThumb .".".$ext;
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }
}

function getPersonPhoto($personId, $gender, $famRole) {
    $validextensions = array("jpeg", "jpg", "png");
    $hasFile = false;
    while (list(, $ext) = each($validextensions)) {
        $photoFile = "Images/Person/thumbnails/" . $personId . ".".$ext;
        if (file_exists($photoFile)) {
            $hasFile = true;
            break;
        }
    }

    if (!$hasFile)  {
        if ($gender == 1 && $famRole =="Child") {
            $photoFile = "img/kid_boy-128.png";
        } else if ($gender == 2 && $famRole !="Child") {
            $photoFile = "img/woman-128.png";
        } else if ($gender == 2 && $famRole =="Child") {
            $photoFile = "img/kid_girl-128.png";
        } else {
            $photoFile = "img/man-128.png";
        }
    }
    return $photoFile;
}

function getGenderIcon($gender) {
    $icon = "?";
    if ($gender == 1)
        $icon = "<i class=\"fa fa-male\"></i>";
    if ($gender == 2)
        $icon = "<i class=\"fa fa-female\"></i>";

    return $icon;
}

function getRoleLabel($famRole) {
    $label = "<span class=\"label ";
    if ($famRole == "Head of Household") {
        $label = $label . "label-success";
    }
    if ($famRole == "Spouse") {
        $label = $label . "label-info";
    }
    if ($famRole == "Child") {
        $label = $label . "label-default";
    }
    $label = $label. "\">".$famRole."</span>";
    return $label;
}