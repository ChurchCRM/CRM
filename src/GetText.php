<?php

use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\dto\SystemURLs;

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
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?= InputUtils::escapeHTML($aEventTitle) ?></title>
  <link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">
</head>
<body>
<div class="container-fluid p-4">
  <h4><?= InputUtils::escapeHTML($aEventTitle) ?></h4>
  <div class="text-muted small mb-3"><?= gettext('Event ID') ?>: <?= InputUtils::escapeHTML($aEventID) ?></div>
  
  <div class="lh-lg">
    <?= $aEventText ?>
  </div>
  
  <div class="mt-4">
    <button class="btn btn-secondary" onclick="window.close()">
      <?= gettext('Close') ?>
    </button>
  </div>
</div>
</body>
</html>
