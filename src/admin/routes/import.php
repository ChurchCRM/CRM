<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/import', function (RouteCollectorProxy $group): void {
    $group->get('/csv', function (Request $request, Response $response, array $_args): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'     => SystemURLs::getRootPath(),
            'sPageTitle'    => gettext('Import from Spreadsheet'),
            'sPageSubtitle' => gettext('Upload a CSV to create families and people in bulk.'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Get Started'), '/admin/get-started'],
                [gettext('Import from Spreadsheet')],
            ]),
            'sPageHeaderButtons' => PageHeader::buttons([
                ['label' => gettext('Download CSV Template'), 'url' => '/admin/api/import/csv/families', 'icon' => 'fa-download'],
            ]),
        ];

        return $renderer->render($response, 'csv-import.php', $pageArgs);
    });
})->add(AdminRoleAuthMiddleware::class);
