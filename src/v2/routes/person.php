<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/person', function (RouteCollectorProxy $group): void {
    $group->get('/not-found', 'viewPersonNotFound');
});

function viewPersonNotFound(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/common/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'memberType' => 'Person',
        'id'         => SlimUtils::getURIParamInt($request, 'id'),
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}
