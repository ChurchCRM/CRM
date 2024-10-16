<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::getCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly')) {
    RedirectUtils::redirect('v2/dashboard');
}

$sPageTitle = gettext('Pledge Reminder Report');
require_once 'Include/Header.php';

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
                <button type="button" class="btn btn-default" name="Cancel"
                        onclick="javascript:document.location='v2/dashboard';"><?= gettext('Cancel') ?></button>
            </div>
        </div>

    </form>
</div>
<?php
require_once 'Include/Footer.php';
