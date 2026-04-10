<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

$sPageTitle = gettext('Pledge Reminder Report');
$sPageSubtitle = gettext('Generate pledge reminder letters');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('Pledge Reminders')],
]);
require_once __DIR__ . '/Include/Header.php';

// Is this the second pass?
if (isset($_POST['Submit'])) {
    $iFYID = InputUtils::legacyFilterInput($_POST['FYID'], 'int');
    $_SESSION['idefaultFY'] = $iFYID;
    RedirectUtils::redirect('Reports/ReminderReport.php?FYID=' . $_SESSION['idefaultFY']);
} else {
    $iFYID = $_SESSION['idefaultFY'];
}

?>

<div class="card">
  <div class="card-body">
    <form method="post" action="Reports/ReminderReport.php">
      <div class="mb-3">
        <label class="form-label" for="FYID"><?= gettext('Fiscal Year') ?>:</label>
        <?php FiscalYearUtils::renderYearSelect('FYID', $iFYID) ?>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" name="Submit"><?= gettext('Create Report') ?></button>
        <button type="button" class="btn btn-secondary" name="Cancel"
                onclick="javascript:document.location='v2/dashboard';"><?= gettext('Cancel') ?></button>
      </div>
    </form>
  </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
