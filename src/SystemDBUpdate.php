<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\Service\UpgradeService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\VersionUtils;

// Include the function library
require_once 'Include/Config.php';
$bSuppressSessionTests = true; // DO NOT MOVE
require_once 'Include/Functions.php';

if (Bootstrapper::isDBCurrent()) {
    RedirectUtils::redirect('v2/dashboard');
}

$logger = LoggerUtils::getAppLogger();
if (isset($_GET['upgrade']) && InputUtils::filterString($_GET['upgrade']) === "true") {
    try {
        $logger->info("Beginning database upgrade");
        UpgradeService::upgradeDatabaseVersion();
        $logger->info("Complete database upgrade; redirecting to Main menu");
        RedirectUtils::redirect('v2/dashboard');
    } catch (\Exception $ex) {
        $errorMessage = $ex->getMessage();
        $logger->error("Error updating database: " . $errorMessage, ['exception' => $ex]);
    }
}

$sPageTitle = gettext('System Upgrade');
require_once 'Include/HeaderNotLoggedIn.php'; ?>

<p></br></p>

<div class="error-page">

    <h2 class="headline text-yellow">426</h2>

    <div class="error-content">
        <div class="row">
            <h3><i class="fa-solid fa-triangle-exclamation text-yellow"></i> <?= gettext('Upgrade Required') ?></h3>
            <p>
                <?= gettext("Current DB Version" . ": " . VersionUtils::getDBVersion()) ?> <br />
                <?= gettext("Current Software Version" . ": " . VersionUtils::getInstalledVersion()) ?> <br />
            </p>
        </div>
    </div>
    <?php if (empty($errorMessage)) {
    ?>
        <div class="row center-block">
            <p></br></p>
            <form id="dbUpgradeForm">
                <input type="hidden" name="upgrade" value="true" />
                <button type="submit" class="btn btn-primary btn-block btn-flat" id="upgradeDatabase"><i
                        class="fa-solid fa-database"></i> <?= gettext('Upgrade database') ?></button>
            </form>
        </div>
    <?php
    } else {
    ?>
        <div class="card-body clearfix" id="globalMessage">
            <div class="alert alert-danger fade in" id="globalMessageCallOut">
                <i class="fa-solid fa-triangle-exclamation fa-fw fa-lg"></i> <?= $errorMessage ?>
            </div>
        </div>
    <?php
    } ?>
</div>

<?php require_once 'Include/FooterNotLoggedIn.php'; ?>