<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ReportCatalog;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/reports', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer('templates/reports/');

    return $renderer->render($response, 'index.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Reports'),
        'sPageSubtitle' => gettext('Every report you can run, grouped by module'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([[gettext('Reports')]]),
        'reports'       => ReportCatalog::forUser(AuthenticationManager::getCurrentUser()),
    ]);
});
