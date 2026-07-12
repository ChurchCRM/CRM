<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageFundraisersRoleAuthMiddleware;

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
