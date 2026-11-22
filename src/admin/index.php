<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DashboardLandingService;
use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Portal\Auth\LoginAsPerson;
// A signed-in person who is not an administrator has no Admin Dashboard.
// A signed-out visit is a normal protected page: the app sends them to staff
// sign-in and brings them back to /admin after that sign-in.
if (rtrim((string) strtok($_SERVER['REQUEST_URI'], '?'), '/') === rtrim(SystemURLs::getRootPath(), '/') . '/admin'
    && !LoginAsPerson::isActive()
    && AuthenticationManager::validateUserSessionIsActive(false)
    && !AuthenticationManager::getCurrentUser()->isAdmin()) {
    DashboardLandingService::redirect();
}

$app = MvcAppFactory::create('/admin', [
    'dashboardUrl' => '/admin/',
    'dashboardText' => gettext('Back to Admin Dashboard'),
    'roleMiddleware' => AdminRoleAuthMiddleware::class,
]);

// Register routes
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/get-started.php';
require __DIR__ . '/routes/api/demo.php';
require __DIR__ . '/routes/api/database.php';
require __DIR__ . '/routes/api/birthday-emails.php';
require __DIR__ . '/routes/api/orphaned-files.php';
require __DIR__ . '/routes/api/email-queue.php';
require __DIR__ . '/routes/api/options.php';
require __DIR__ . '/routes/api/grades.php';
require __DIR__ . '/routes/api/member-portal.php';
require __DIR__ . '/routes/api/person-sign-in.php';
require __DIR__ . '/routes/api/member-portal-reports.php';
require __DIR__ . '/routes/api/person-portal-access.php';
require __DIR__ . '/routes/api/person-login-as-person.php';
require __DIR__ . '/routes/api/family-conversion.php';
require __DIR__ . '/routes/api/system/church-logo.php';
require __DIR__ . '/routes/api/system/letterhead.php';
require __DIR__ . '/routes/api/system/tax-signature.php';
require __DIR__ . '/routes/api/system/property-definitions.php';
require __DIR__ . '/routes/api/system/property-types.php';
require __DIR__ . '/routes/api/system/system-config.php';
require __DIR__ . '/routes/api/system/system-custom-fields.php';
require __DIR__ . '/routes/api/system/system-logs.php';
require __DIR__ . '/routes/api/system/feature-toggles.php';
require __DIR__ . '/routes/api/system/telemetry-consent.php';
require __DIR__ . '/routes/api/upgrade.php';
require __DIR__ . '/routes/api/user-admin.php';
require __DIR__ . '/routes/api/import.php';
require __DIR__ . '/routes/import.php';
require __DIR__ . '/routes/export.php';
require __DIR__ . '/routes/system.php';
require __DIR__ . '/routes/people.php';
require __DIR__ . '/routes/properties.php';
require __DIR__ . '/routes/email.php';
require __DIR__ . '/routes/member-portal.php';
require __DIR__ . '/routes/ministry-settings.php';
require __DIR__ . '/routes/family-conversion.php';
require __DIR__ . '/routes/property-types.php';

$app->run();
