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

/**
 * @OA\Get(
 *     path="/public/echo",
 *     operationId="getEcho",
 *     summary="Health check / echo",
 *     description="Returns a simple echo response. Useful for verifying the API is reachable.",
 *     tags={"Utility"},
 *     @OA\Response(
 *         response=200,
 *         description="Echo response",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="echo")
 *         )
 *     )
 * )
 */
function getEcho(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, ['message' => 'echo']);
}

/**
 * @OA\Post(
 *     path="/public/csp-report",
 *     operationId="logCSPReport",
 *     summary="Log a Content Security Policy violation report",
 *     description="Receives browser-generated CSP violation reports and logs them server-side.",
 *     tags={"Utility"},
 *     @OA\RequestBody(
 *         required=true,
 *         description="CSP violation report object (browser-generated)",
 *         @OA\JsonContent(type="object", example={"csp-report": {"document-uri": "https://example.com", "violated-directive": "script-src"}})
 *     ),
 *     @OA\Response(response=204, description="Report logged successfully (no content)")
 * )
 */
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
