<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\StaffSignInPage;
use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Portal\Auth\LoginAsPerson;
use ChurchCRM\Utils\RedirectUtils;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;

// The exact /admin root is the staff sign-in address. It is decided here, before the
// admin app exists, so AuthMiddleware and AdminRoleAuthMiddleware keep no path exception.
if (rtrim((string) strtok($_SERVER['REQUEST_URI'], '?'), '/') === rtrim(SystemURLs::getRootPath(), '/') . '/admin'
    && !LoginAsPerson::isActive()) {
    if (!AuthenticationManager::validateUserSessionIsActive(false)) {
        $request = ServerRequestFactory::fromGlobals();
        $response = StaffSignInPage::handle($request, new Response());
        http_response_code($response->getStatusCode());
        echo $response->getBody();
        exit;
    }
    if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
        RedirectUtils::redirect('v2/dashboard');
    }
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
require __DIR__ . '/routes/api/system/church-logo.php';
require __DIR__ . '/routes/api/system/letterhead.php';
require __DIR__ . '/routes/api/system/property-definitions.php';
require __DIR__ . '/routes/api/system/property-types.php';
require __DIR__ . '/routes/api/system/system-config.php';
require __DIR__ . '/routes/api/system/system-custom-fields.php';
require __DIR__ . '/routes/api/system/system-logs.php';
require __DIR__ . '/routes/api/system/feature-toggles.php';
require __DIR__ . '/routes/api/system/telemetry-consent.php';
require __DIR__ . '/routes/api/system/volunteer-opportunities.php';
require __DIR__ . '/routes/api/volunteer-conversion.php';
require __DIR__ . '/routes/api/upgrade.php';
require __DIR__ . '/routes/api/user-admin.php';
require __DIR__ . '/routes/api/import.php';
require __DIR__ . '/routes/import.php';
require __DIR__ . '/routes/export.php';
require __DIR__ . '/routes/system.php';
require __DIR__ . '/routes/people.php';
require __DIR__ . '/routes/email.php';
require __DIR__ . '/routes/member-portal.php';
require __DIR__ . '/routes/ministry-settings.php';
require __DIR__ . '/routes/volunteer-conversion.php';
require __DIR__ . '/routes/property-types.php';

$app->run();
