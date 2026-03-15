<?php

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Redirect /groups root to /groups/dashboard
$app->get('/', function (Request $request, Response $response) {
    return $response
        ->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/groups/dashboard')
        ->withStatus(302);
});

// Match /groups/dashboard - Groups Dashboard (replaces GroupList.php)
$app->get('/dashboard', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Group Listing'),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
