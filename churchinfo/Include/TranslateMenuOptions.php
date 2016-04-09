<?php

/*******************************************************************************
 *
 *  filename    : TranslateMenuOptions.php
 *  last change : 2012-02-19
 *  description : utility to translate all the menu options to the selected language
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

function TranslateMenuOptions()
{
  $sSQL = "SELECT content_english from menuconfig_mcf";
  $rsMenuOptions = RunQuery($sSQL);

  while ($myrow = mysql_fetch_row($rsMenuOptions)) {
    $optStr = $myrow[0];
    $sSQL = "update menuconfig_mcf set content='" . gettext($optStr) . "' where content_english='" . $optStr . "'";
    RunQuery($sSQL);
  }
}

?>
