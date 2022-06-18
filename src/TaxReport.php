<?php
/*******************************************************************************
 *
 *  filename    : TaxReport.php
 *  last change : 2003-09-03
 *  description : form to invoke tax letter generation
 *




 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!AuthenticationManager::GetCurrentUser()->isAdmin() && SystemConfig::getValue('bCSVAdminOnly')) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext('Tax Report');
require 'Include/Header.php';

// Is this the second pass?
if (isset($_POST['Submit'])) {
    $iYear = InputUtils::LegacyFilterInput($_POST['Year'], 'int');
    RedirectUtils::Redirect('Reports/TaxReport.php?Year='.$iYear);
} else {
    $iYear = date('Y') - 1;
}

?>

<div class="card card-body">
    <form class="form-horizontal" method="post" action="TaxReport.php">
        <div class="form-group">
            <label class="control-label col-sm-2" for="Year"><?= gettext('Calendar Year') ?>:</label>
            <div class="col-sm-2">
                <input type="text" name="Year" id="Year" value="<?= $iYear ?>">
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-8">
                <button type="submit" class="btn btn-primary" name="Submit"><?= gettext('Create Report') ?></button>
                <button type="button" class="btn btn-default" name="Cancel"
                        onclick="javascript:document.location='Menu.php';"><?= gettext('Cancel') ?></button>
            </div>
        </div>

    </form>
</div>
<?php require 'Include/Footer.php' ?>
