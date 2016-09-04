<?php

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
