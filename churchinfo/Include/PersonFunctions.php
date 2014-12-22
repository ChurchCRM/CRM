<?php
/**
 * Created by IntelliJ IDEA.
 * User: gdawoud
 * Date: 12/20/2014
 * Time: 12:43 PM
 */
function getPersonPhoto($personId, $gender, $famRole) {
    $photoFile = "Images/Person/thumbnails/" . $personId . ".jpg";
    if (!file_exists($photoFile))  {
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