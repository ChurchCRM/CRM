<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\Middleware\Request\Auth\EmailRoleAuthMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/email', function (RouteCollectorProxy $group): void {
    $group->get('/dashboard', 'getEmailDashboardMVC');
    $group->get('/duplicate', 'getDuplicateEmailsMVC');
    $group->get('/missing', 'getPeopleWithoutEmailsMVC');
    $group->get('', 'getEmailDashboardMVC');
    $group->get('/', 'getEmailDashboardMVC');
})->add(EmailRoleAuthMiddleware::class);

function getEmailDashboardMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/email/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Email Dashboard'),
        'sPageSubtitle' => gettext('Manage email tools and SMTP configuration'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Communication')],
            [gettext('Email')],
        ]),
        'sSettingsCollapseId' => 'emailSettings',
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Debug'), 'url' => '/admin/system/debug/email', 'icon' => 'fa-stethoscope', 'adminOnly' => true],
            ['label' => gettext('Email Settings'), 'collapse' => '#emailSettings', 'icon' => 'fa-sliders', 'adminOnly' => true],
        ]),
        'bEmailEnabled' => SystemConfig::getBooleanValue('bEnabledEmail'),
        'bSmtpConfigured' => SystemConfig::hasValidMailServerSettings(),
        'bMailchimpConfigured' => PluginManager::getPlugin('mailchimp')?->isConfigured() ?? false,
        // Recent sends across everyone (admins only): the first place to look when "nobody got the email".
        'recentEmails' => AuthenticationManager::getCurrentUser()->isAdmin()
            ? (new EmailLogService())->getRecent(null, 1, 20)
            : null,
        'failedEmailCount' => AuthenticationManager::getCurrentUser()->isAdmin()
            ? (new EmailLogService())->getRecent(EmailLogService::STATUS_FAILED, 1, 1)['total']
            : 0,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}

function getDuplicateEmailsMVC(Request $request, Response $response, array $args): Response
{
    return renderPage($response, 'templates/email/', 'duplicate.php', _('Duplicate Emails'));
}

function getPeopleWithoutEmailsMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/email/');

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('People Without Emails'),
        'sPageSubtitle' => gettext('People with no personal or work email on record'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Communication')],
            [gettext('Email'), '/v2/email/dashboard'],
            [gettext('People Without Emails')],
        ]),
    ];

    return $renderer->render($response, 'without.php', $pageArgs);
}
