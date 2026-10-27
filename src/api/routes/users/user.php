<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserConfig;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
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
 *     @OA\Response(response=200, description="Updated config key/value pair"),
 *     @OA\Response(response=400, description="A string value is required"),
 *     @OA\Response(response=403, description="This setting is administrator-only"),
 *     @OA\Response(response=404, description="Unknown user configuration key")
 * )
 */
function updateUserConfig(Request $request, Response $response, array $args): Response
{
    /** @var User $user */
    $user = $request->getAttribute('user');
    $name = (string) $args['key'];
    $input = $request->getParsedBody();

    // Reject invalid payloads instead of passing arrays or missing values
    // into a string setting and surfacing PHP/ORM failures as HTTP 500.
    if (!is_array($input) || !array_key_exists('value', $input) || !is_string($input['value'])) {
        return SlimUtils::renderErrorJSON($response, gettext('A string value is required'), [], 400);
    }

    $config = UserConfigQuery::create()
        ->filterByPeronId($user->getId())
        ->filterByName($name)
        ->findOne();

    if ($config === null) {
        // SettingsIndividual.php clones a default when the per-user row does
        // not exist. Follow that same rule, but authorize BEFORE cloning so
        // a denied request cannot create a new security-sensitive setting.
        $default = UserConfigQuery::create()
            ->filterByPeronId(0)
            ->filterByName($name)
            ->findOne();
        if ($default === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Unknown user configuration key'), [], 404);
        }
        $permission = $default->getPermission();
    } else {
        $permission = $config->getPermission();
    }

    if (!AuthenticationManager::getCurrentUser()->isAdmin() && $permission !== 'TRUE') {
        return SlimUtils::renderErrorJSON($response, gettext('Administrator permission required for this setting'), [], 403);
    }

    if ($config === null) {
        $config = new UserConfig();
        $config->setPeronId($user->getId())
            ->setId($default->getId())
            ->setName($default->getName())
            ->setValue($default->getValue())
            ->setType($default->getType())
            ->setCat($default->getCat())
            ->setTooltip($default->getTooltip())
            ->setPermission($default->getPermission());
    }

    $config->setValue($input['value']);
    $config->save();

    return SlimUtils::renderJSON($response, [$name => $input['value']]);
}
