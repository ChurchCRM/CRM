<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Normalize and sanitize incoming query parameters used by this template
$sMessage = $_GET['Message'] ?? null;

if ($sMessage === null) {
?>
  <p class="text-center alert alert-warning"><?= gettext('You have no items in your cart.') ?> </p>
  <?php
} else {
  switch ($sMessage) {
    case 'aMessage':
      // Cast numeric values to int and escape any string output
      $iCount = (int)($_GET['iCount'] ?? 0);
      $iEID = (int)($_GET['iEID'] ?? 0);
      $event = EventQuery::create()->findPk($iEID);
      $eventTitle = $event ? InputUtils::escapeHTML($event->getTitle()) : '';
  ?>
      <p class="text-center alert alert-info"><?= $iCount . ' ' . ($iCount === 1 ? gettext('Record') : gettext('Records')) . ' ' . gettext("Emptied into Event") . ':' ?>
        <a href="<?= SystemURLs::getRootPath() ?>/Checkin.php?eventId=<?= $iEID ?>"><?= $eventTitle ?></a>
      </p>
<?php
      break;
  }
}
?>
<p class="text-center">
  <a href="<?= SystemURLs::getRootPath() ?>/" class="btn btn-primary"><?= gettext('Back to Menu') ?></a>
</p>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
