<?php

/*******************************************************************************
 *
 *  filename    : GetText.php
 *  last change : 2005-09-08
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2005 Todd Pillars
 *
 *  function    : Get Text from Church Events Table in popup window
  *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

$sSQL = 'SELECT * FROM events_event WHERE event_id = ' . $_GET['EID'];
$rsOpps = RunQuery($sSQL);
$aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH) || die(mysqli_error($cnInfoCentral));
extract($aRow);
$aEventID = $event_id;
$aEventTitle = $event_title;
$aEventText = $event_text;
?>
<html>
<head><title><?= gettext("Text from") ?> <?= $aEventID ?></title></head>
</html>
<table cellpadding="4" align="center" cellspacing="0" width="100%">
  <caption>
    <h3><?= gettext('Text for Event ID: ') . $aEventTitle ?></h3>
  </caption>
  <tr>
    <td><?= $aEventText ?></td>
  </tr>
  <tr>
    <td align="center" valign="bottom">
      <input type="button" name="Action" value="Close Window" class="btn btn-default" onclick="javascript:window.close()">
    </td>
  </tr>
</html>
