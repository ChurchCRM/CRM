<?php

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
            $photoFile = "Images/Person/kid_boy-128.png";
        } else if ($gender == 2 && $famRole !="Child") {
            $photoFile = "Images/Person/woman-128.png";
        } else if ($gender == 2 && $famRole =="Child") {
            $photoFile = "Images/Person/kid_girl-128.png";
        } else {
            $photoFile = "Images/Person/man-128.png";
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
    } else  if ($famRole == "Spouse") {
        $label = $label . "label-info";
    } else if ($famRole == "Child") {
        $label = $label . "label-warning";
    } else {
        $label = $label . "label-default";
    }
    $label = $label. "\">".$famRole."</span>";
    return $label;
}