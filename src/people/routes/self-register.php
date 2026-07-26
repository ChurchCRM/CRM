<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/self-register', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Self Registrations'),
        'sPageSubtitle' => gettext('Families and people created via self registration'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Self Registrations')],
        ]),
    ];

    return $renderer->render($response, 'self-register.php', $pageArgs);
});
