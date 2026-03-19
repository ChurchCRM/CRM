<?php

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/get-started', function (RouteCollectorProxy $group): void {

    // Get Started landing page
    $group->get('', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Get Started'),
        ];

        return $renderer->render($response, 'get-started.php', $pageArgs);
    });

    // Manual data entry guided intro ("Start Fresh")
    $group->get('/manual', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Start Fresh — Manual Data Entry'),
        ];

        return $renderer->render($response, 'get-started-manual.php', $pageArgs);
    });
});
