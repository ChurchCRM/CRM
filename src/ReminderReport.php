<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

$sPageTitle = gettext('Pledge Reminder Report');
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

<div class="card card-body">
    <form class="form-horizontal" method="post" action="Reports/ReminderReport.php">
        <div class="form-group">
            <label class="control-label col-sm-2" for="FYID"><?= gettext('Fiscal Year') ?>:</label>
            <div class="col-sm-2">
                <?php PrintFYIDSelect('FYID', $iFYID) ?>
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-8">
                <button type="submit" class="btn btn-primary" name="Submit"><?= gettext('Create Report') ?></button>
                <button type="button" class="btn btn-secondary" name="Cancel"
                        onclick="javascript:document.location='v2/dashboard';"><?= gettext('Cancel') ?></button>
            </div>
        </div>

    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
