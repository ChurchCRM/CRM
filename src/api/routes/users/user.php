<?php

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Slim\Middleware\Api\UserMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/user/{userId:[0-9]+}', function (RouteCollectorProxy $group): void {
    $group->post('/apikey/regen', 'genAPIKey');
    $group->post('/config/{key}', 'updateUserConfig');
})->add(UserMiddleware::class);

/**
 * @OA\Post(
 *     path="/user/{userId}/apikey/regen",
 *     summary="Regenerate the API key for a user",
 *     tags={"Users"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="New API key",
 *         @OA\JsonContent(@OA\Property(property="apiKey", type="string"))
 *     )
 * )
 */
function genAPIKey(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    $user->setApiKey(User::randomApiKey());
    $user->save();
    $user->createTimeLineNote('api-key-regen');

    return SlimUtils::renderJSON($response, ['apiKey' => $user->getApiKey()]);
}

/**
 * @OA\Post(
 *     path="/user/{userId}/config/{key}",
 *     summary="Update a named config string for a user",
 *     tags={"Users"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="key", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(@OA\Property(property="value", type="string"))
 *     ),
 *     @OA\Response(response=200, description="Updated config key/value pair")
 * )
 */
function updateUserConfig(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    $userConfigName = $args['key'];
    $parsedBody = $request->getParsedBody();
    $newValue = $parsedBody['value'];
    $user->setUserConfigString($userConfigName, $newValue);
    $user->save();

    if ($user->getUserConfigString($userConfigName) !== $newValue) {
        throw new \Exception('user config string does not match provided value');
    }

    return SlimUtils::renderJSON($response, [$userConfigName => $newValue]);
}
