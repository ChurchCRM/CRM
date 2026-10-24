<?php

use ChurchCRM\Slim\Middleware\Api\UserMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/user/{userId:[0-9]+}/setting', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/user/{userId}/setting/{settingName}",
     *     summary="Get a named setting value for a user",
     *     tags={"Users"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="settingName", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Setting value",
     *         @OA\JsonContent(@OA\Property(property="value", type="string"))
     *     )
     * )
     */
    $group->get('/{settingName}', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        $settingName = $args['settingName'];
        $setting = $user->getSetting($settingName);
        $value = '';
        if ($setting) {
            $value = $setting->getValue();
        }

        return SlimUtils::renderJSON($response, ['value' => $value]);
    });

    /**
     * @OA\Post(
     *     path="/user/{userId}/setting/{settingName}",
     *     summary="Set a named setting value for a user",
     *     tags={"Users"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="settingName", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(@OA\Property(property="value", type="string"))
     *     ),
     *     @OA\Response(response=200, description="Updated setting value",
     *         @OA\JsonContent(@OA\Property(property="value", type="string"))
     *     )
     * )
     */
    $group->post('/{settingName}', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        $settingName = $args['settingName'];

        $value = ((array) $request->getParsedBody())['value'] ?? null;
        $formats = [
            'ui.locale' => '/^([a-z]{2,3}(_[A-Za-z0-9]{2,8})*)?$/',
            'ui.table.size' => '/^-?\d{1,4}$/',
        ];
        if (
            !preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $settingName)
            || !is_scalar($value)
            || strlen((string) $value) > 50
            || (isset($formats[$settingName]) && !preg_match($formats[$settingName], (string) $value))
        ) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid setting name or value'), [], 400);
        }
        $user->setSetting($settingName, (string) $value);

        return SlimUtils::renderJSON($response, ['value' => $user->getSetting($settingName)->getValue()]);
    });
})->add(UserMiddleware::class);
