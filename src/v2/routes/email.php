<?php

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/email', function (RouteCollectorProxy $group): void {
    $group->get('/dashboard', 'getEmailDashboardMVC');
    $group->get('/duplicate', 'getDuplicateEmailsMVC');
    $group->get('/missing', 'getFamiliesWithoutEmailsMVC');
    $group->get('', 'getEmailDashboardMVC');
    $group->get('/', 'getEmailDashboardMVC');
});

function getEmailDashboardMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/email/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('eMail Dashboard'),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}

function getDuplicateEmailsMVC(Request $request, Response $response, array $args): Response
{
    return renderPage($response, 'templates/email/', 'duplicate.php', _('Duplicate Emails'));
}

function getFamiliesWithoutEmailsMVC(Request $request, Response $response, array $args): Response
{
    return renderPage($response, 'templates/email/', 'without.php', _('Families Without Emails'));
}
