<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageFundraisersRoleAuthMiddleware;

// Define RunQuery() in the Slim context. PageInit.php (which normally defines
// it) is not loaded by MVC modules; the actual implementation lives in
// FunctionsUtils::runQuery() which is autoloaded and safe to call here because
// Bootstrapper::init() (called by LoadConfigs.php) has already set up $cnInfoCentral.
if (!function_exists('RunQuery')) {
    function RunQuery(string $sSQL, bool $bStopOnError = true)
    {
        return \ChurchCRM\Utils\FunctionsUtils::runQuery($sSQL, $bStopOnError);
    }
}

// Global gate: every /fundraiser/* request requires login (AuthMiddleware) AND
// the ManageFundraisers permission (ManageFundraisersRoleAuthMiddleware).
// Individual delete routes add an inline isDeleteRecordsEnabled() check.
$app = MvcAppFactory::create('/fundraiser', [
    'dashboardUrl'  => '/fundraiser/',
    'dashboardText' => gettext('Return to Fundraiser Dashboard'),
    'roleMiddleware' => ManageFundraisersRoleAuthMiddleware::class,
]);

// Register routes
require __DIR__ . '/routes/fundraiser.php';
require __DIR__ . '/routes/paddle-num.php';
require __DIR__ . '/routes/donated-item.php';
require __DIR__ . '/routes/donors.php';
require __DIR__ . '/routes/batch-winner.php';
require __DIR__ . '/routes/reports.php';

$app->run();
