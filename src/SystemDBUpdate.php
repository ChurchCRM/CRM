<?php

use ChurchCRM\Service\SystemService;
use ChurchCRM\Service\UpgradeService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Bootstrapper;
use ChurchCRM\Utils\LoggerUtils;

// Include the function library
require 'Include/Config.php';
$bSuppressSessionTests = true; // DO NOT MOVE
require 'Include/Functions.php';

if (Bootstrapper::isDBCurrent()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

if (isset($_GET['upgrade']) && InputUtils::FilterString($_GET['upgrade']) == "true") {
    try {
        LoggerUtils::getAppLogger()->info("Beginning database upgrade");
        UpgradeService::upgradeDatabaseVersion();
        LoggerUtils::getAppLogger()->info("Complete database upgrade; redirecting to Main menu");
        RedirectUtils::Redirect('Menu.php');
        exit;
    } catch (Exception $ex) {
        $errorMessage = $ex->getMessage();
        LoggerUtils::getAppLogger()->error("Error updating database: " .$errorMessage);
    }
}

// Set the page title and include HTML header
$sPageTitle = gettext('System Upgrade');
require 'Include/HeaderNotLoggedIn.php'; ?>

<p></br></p>

<div class="error-page">


    <h2 class="headline text-yellow">426</h2>


    <div class="error-content">
        <div class="row">
            <h3><i class="fa fa-warning text-yellow"></i> <?= gettext('Upgrade Required') ?></h3>
            <p>
                <?= gettext("Current DB Version" . ": " . SystemService::getDBVersion()) ?> <br/>
                <?= gettext("Current Software Version" . ": " . SystemService::getInstalledVersion()) ?> <br/>
            </p>
        </div>
    </div>
    <?php if (empty($errorMessage)) {
    ?>
        <div class="row center-block">
                <p></br></p>
                <form>
                    <input type="hidden" name="upgrade" value="true"/>
                    <button type="submit" class="btn btn-primary btn-block btn-flat" id="upgradeDatabase"><i
                            class="fa fa-database"></i> <?= gettext('Upgrade database') ?></button>
                </form>
        </div>
    <?php
} else {
        ?>
        <div class="main-box-body clearfix" id="globalMessage">
            <div class="callout callout-danger fade in" id="globalMessageCallOut">
                <i class="fa fa-warning fa-fw fa-lg"></i> <?= $errorMessage ?>
            </div>
        </div>
    <?php
    } ?>
</div>


<?php require 'Include/FooterNotLoggedIn.php'; ?>
