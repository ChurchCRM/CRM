<?php

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use Slim\Routing\RouteCollectorProxy;
$app->group('/person', function (RouteCollectorProxy $group) {
    $group->get('/not-found', 'viewPersonNotFound');
});

function viewPersonNotFound(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/common/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'memberType' => 'Person',
        'id'         => $request->getParam('id'),
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}
