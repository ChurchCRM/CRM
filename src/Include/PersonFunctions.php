<?php

function getGenderIcon($gender)
{
  $icon = "";
  if ($gender == 1)
    $icon = "<i class=\"fa fa-male\"></i>";
  if ($gender == 2)
    $icon = "<i class=\"fa fa-female\"></i>";

  return $icon;
}

function getRoleLabel($famRole)
{
  $label = "<span class=\"label ";
  if ($famRole == "Head of Household") {
    $label = $label . "label-success";
  } else if ($famRole == "Spouse") {
    $label = $label . "label-info";
  } else if ($famRole == "Child") {
    $label = $label . "label-warning";
  } else {
    $label = $label . "label-default";
  }
  $label = $label . "\">" . $famRole . "</span>";
  return $label;
}
