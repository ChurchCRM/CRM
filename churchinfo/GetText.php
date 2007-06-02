<?php
/*******************************************************************************
 *
 *  filename    : GetText.php
 *  last change : 2005-09-08
 *  website     : http://www.terralabs.com
 *  copyright   : Copyright 2005 Todd Pillars
 *
 *  function    : Get Text from Church Events Table in popup window
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/
require "Include/Config.php";
require "Include/Functions.php";

$sSQL = "SELECT * FROM events_event WHERE event_id = ".$_GET['EID'];
$rsOpps = RunQuery($sSQL);
$aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH) or die(mysql_error());
extract($aRow);
$aEventID = $event_id;
$aEventTitle = $event_title;
$aEventText = nl2br(htmlentities(stripslashes($event_text),ENT_NOQUOTES, "UTF-8"));
?>
<html>
<head><title>Text from <?php echo $aEventID; ?></title></head>
</html>
<table cellpadding="4" align="center" cellspacing="0" width="100%">
  <caption>
    <h3><?php echo gettext("Text for Event ID: ".$aEventTitle); ?></h3>
  </caption>
  <tr>
    <td><?php echo $aEventText; ?></td>
  </tr>
  <tr>
    <td align="center" valign="bottom">
      <input type="button" name="Action" value="Close Window" class="icButton" onclick="javascript:window.close()">
    </td>
  </tr>
</html>