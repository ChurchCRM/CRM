<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Slim\Middleware\Request\Auth\EmailRoleAuthMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/email', function (RouteCollectorProxy $group): void {
    $group->get('/dashboard', 'getPeopleEmailDashboard');
    $group->get('/duplicate', 'getPeopleDuplicateEmails');
    $group->get('/missing', 'getPeopleWithoutEmails');
    $group->get('', 'getPeopleEmailDashboard');
    $group->get('/', 'getPeopleEmailDashboard');
})->add(EmailRoleAuthMiddleware::class);

function getPeopleEmailDashboard(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/email/');
    $user = AuthenticationManager::getCurrentUser();

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('Email'),
        'sPageSubtitle'      => gettext('Find duplicate addresses and people with no email'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Email')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Email Settings'), 'url' => '/admin/email', 'icon' => 'fa-sliders'],
        ]),
        'bEmailEnabled'      => SystemConfig::getBooleanValue('bEnabledEmail'),
        'isAdmin'            => $user->isAdmin(),
        'emailLog'           => [
            'facets'         => (new EmailLogService())->getLogFacets($user->isAdmin() ? null : (int) $user->getId()),
            'pageUrl'        => '/people/email',
            'showSender'     => $user->isAdmin(),
            'canDeleteQueue' => false,
        ],
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}

function getPeopleDuplicateEmails(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/email/');

    return $renderer->render($response, 'duplicate.php', [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Duplicate Emails'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Email'), '/people/email'],
            [gettext('Duplicate Emails')],
        ]),
    ]);
}

function getPeopleWithoutEmails(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/email/');

    return $renderer->render($response, 'without.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('People Without Emails'),
        'sPageSubtitle' => gettext('People with no personal or work email on record'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Email'), '/people/email'],
            [gettext('People Without Emails')],
        ]),
    ]);
}
