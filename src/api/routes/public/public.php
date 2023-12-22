<?php

use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public', function (RouteCollectorProxy $group): void {
    $group->get('/echo', 'getEcho');
});

function getEcho(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, ['message' => 'echo']);
}
