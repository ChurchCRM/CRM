<?php

namespace ChurchCRM\dto;

class Tooltip
{
  public function gettext($text)
  {
    if ($_SESSION['bShowTooltip']) {// $bShowTooltip is initialized in Header-function.php but not useable here ??
      return gettext($text);
    } else {
      return "";
    }
  }
}