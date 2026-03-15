<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

$sPageTitle = gettext('Tax Report');
require_once __DIR__ . '/Include/Header.php';

// Is this the second pass?
if (isset($_POST['Submit'])) {
    $iYear = InputUtils::legacyFilterInput($_POST['Year'], 'int');
    RedirectUtils::redirect('Reports/TaxReport.php?Year=' . $iYear);
} else {
    $iYear = date('Y') - 1;
}

?>

<div class="card card-body">
    <form method="post" action="TaxReport.php">
        <div class="form-group row">
            <label class="col-form-label col-sm-3" for="Year"><?= gettext('Calendar Year') ?>:</label>
            <div class="col-sm-3">
                <input type="text" name="Year" id="Year" class="form-control" value="<?= $iYear ?>">
            </div>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary" name="Submit"><?= gettext('Create Report') ?></button>
            <a href="v2/dashboard" class="btn btn-secondary ml-2"><?= gettext('Cancel') ?></a>
        </div>

    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
