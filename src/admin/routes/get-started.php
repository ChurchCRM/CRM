<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Note: GET /get-started (landing page) is registered in dashboard.php
$app->group('/get-started', function (RouteCollectorProxy $group): void {

    // Manual data entry guided intro ("Start Fresh")
    $group->get('/manual', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'  => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Start Fresh'),
            'sPageSubtitle' => gettext("Great choice! Here's how to add your church members."),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Admin'), '/admin/'],
                [gettext('Get Started'), '/admin/get-started'],
                [gettext('Start Fresh')],
            ]),
        ];

        return $renderer->render($response, 'get-started-manual.php', $pageArgs);
    });
});
