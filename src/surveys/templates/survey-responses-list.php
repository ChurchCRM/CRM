<?php
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';
//Set the page title
$sPageTitle = gettext("Survey Responses List");
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/**
 * @var $sessionUser \ChurchCRM\User
 */
$sessionUser = $_SESSION['user'];

?>

<div class="box">
  <div class="box-body">
      <table id="surveyDefinitions" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
          <thead>
          <tr>
              <th><?= gettext('Response Date Time') ?></th>
              <th><?= gettext('Survey Definition Name') ?></th>
          </tr>
          </thead>
          <tbody>

      </table>
  </div>
</div>


<script nonce="<?= SystemURLs::getCSPNonce() ?>">
</script>


<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>