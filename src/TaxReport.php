<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

$sPageTitle = gettext('Tax Report');
$sPageSubtitle = gettext('Generate tax statement documents for donors');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('Tax Report')],
]);
require_once __DIR__ . '/Include/Header.php';

// Is this the second pass?
if (isset($_POST['Submit'])) {
    $iYear = InputUtils::legacyFilterInput($_POST['Year'], 'int');
    RedirectUtils::redirect('Reports/TaxReport.php?Year=' . $iYear);
} else {
    $iYear = date('Y') - 1;
}

?>

<div class="card-body">
    <form method="post" action="TaxReport.php">
        <div class="mb-3 row">
            <label class="col-form-label col-sm-3" for="Year"><?= gettext('Calendar Year') ?>:</label>
            <div class="col-sm-3">
                <input type="text" name="Year" id="Year" class="form-control" value="<?= $iYear ?>">
            </div>
        </div>

        <div class="mb-3">
            <button type="submit" class="btn btn-primary" name="Submit"><?= gettext('Create Report') ?></button>
            <a href="v2/dashboard" class="btn btn-secondary ms-2"><?= gettext('Cancel') ?></a>
        </div>

    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
