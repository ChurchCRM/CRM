<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public', function (RouteCollectorProxy $group) {
    $group->get('/echo', 'getEcho');
});

function getEcho(Request $request, Response $response, array $p_args)
{
    $response->getBody()->write(json_encode(['message' => 'echo']));
    return $response->withHeader('Content-Type', 'application/json');
}
