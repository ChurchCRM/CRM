<?php

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

$eidQueryParam = $_GET['EID'];
$sanitizedEidQueryParam = InputUtils::filterInt($eidQueryParam);
if ($eidQueryParam !== (string) $sanitizedEidQueryParam) {
    LoggerUtils::getAppLogger()->warning('Provided event ID does not match sanitized event ID', ['providedEventId' => $eidQueryParam, 'sanitizedEventId' => $sanitizedEidQueryParam]);
}

$event = EventQuery::create()->findOneById($sanitizedEidQueryParam);
$aEventID = $event->getId();
$aEventTitle = $event->getTitle();
$aEventText = $event->getText();
?>
<html>
<head><title><?= gettext("Text from") ?> <?= $aEventID ?></title></head>
<body>
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
</table>
</body>
</html>
