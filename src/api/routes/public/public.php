<?php

use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public', function (RouteCollectorProxy $group): void {
    $group->get('/echo', 'getEcho');
    $group->post('/csp-report', 'logCSPReportAPI');
});

function getEcho(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, ['message' => 'echo']);
}

function logCSPReportAPI(Request $request, Response $response, array $args): Response
{
    try {
        $input = json_decode($request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        LoggerUtils::getCSPLogger()->warning('CSP violation reported', $input);
    } catch (\JsonException $e) {
        LoggerUtils::getCSPLogger()->warning('Invalid CSP report JSON: ' . $e->getMessage());
    }

    return $response->withStatus(204);
}
