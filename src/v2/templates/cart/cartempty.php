<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

if (!array_key_exists('Message', $_GET)) {
    ?>
  <p class="text-center callout callout-warning"><?= gettext('You have no items in your cart.') ?> </p>
    <?php
} else {
    switch ($_GET['Message']) {
        case 'aMessage':
            $event = EventQuery::create()->findPk($_GET['iEID']);
            ?>
      <p class="text-center callout callout-info"><?= $_GET['iCount'] . ' ' . ($_GET['iCount'] == 1 ? gettext('Record') : gettext('Records')) . ' ' . gettext("Emptied into Event") . ':' ?>
          <a href="<?= SystemURLs::getRootPath()?>/EditEventAttendees.php?eventId=<?=$_GET['iEID']?>"><?=$event->getTitle()?></a> </p>
            <?php break;
    }
}
?>
<p align="center">
  <a href="<?= SystemURLs::getRootPath()?>/" class="btn btn-primary"><?=gettext('Back to Menu')?></a>
</p>
<?php

require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
