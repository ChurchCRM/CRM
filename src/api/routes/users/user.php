<?php
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpConflictException;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;

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
    $group->post('/apikey/reveal', 'revealAPIKey')->add(new CSRFMiddleware());
    $group->post('/apikey/regen', 'genAPIKey')->add(new CSRFMiddleware());
    $group->post('/config/{key}', 'updateUserConfig');
})->add(UserMiddleware::class);

function requireRecentAPIKeyManagementAuthentication(Request $request, Response $response): ?Response
{
    if (!AuthenticationManager::isCompletedLocalAuthentication()) {
        throw new HttpForbiddenException(
            $request,
            gettext('This action requires a signed-in browser session.')
        );
    }
    if (!AuthenticationManager::hasRecentSecurityActionAuthentication()) {
        return noStoreAPIKeyResponse(SlimUtils::renderJSON(
            $response->withStatus(428),
            [
                'error' => gettext('Please confirm your current password before managing API credentials.'),
                'code' => 'reauthentication_required',
            ]
        ));
    }

    return null;
}

function noStoreAPIKeyResponse(Response $response): Response
{
    return $response
        ->withHeader('Cache-Control', 'no-store, private')
        ->withHeader('Pragma', 'no-cache');
}

/**
 * @OA\Post(
 *     path="/user/{userId}/apikey/reveal",
 *     summary="Reveal the current API key after recent browser reauthentication",
 *     tags={"Users"},
 *     security={{"BrowserSessionAuth":{}}},
 *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Current API key",
 *         @OA\JsonContent(@OA\Property(property="apiKey", type="string", nullable=true))
 *     ),
 *     @OA\Response(response=401, description="Browser session is no longer valid"),
 *     @OA\Response(response=428, description="Recent password confirmation required"),
 *     @OA\Response(response=403, description="Browser session or CSRF validation failed")
 * )
 */
function revealAPIKey(Request $request, Response $response, array $args): Response
{
    $challenge = requireRecentAPIKeyManagementAuthentication($request, $response);
    if ($challenge !== null) {
        return $challenge;
    }
    /** @var User $user */
    $user = $request->getAttribute('user');

    return noStoreAPIKeyResponse(SlimUtils::renderJSON(
        $response,
        ['apiKey' => $user->getApiKey()]
    ));
}

/**
 * @OA\Post(
 *     path="/user/{userId}/apikey/regen",
 *     summary="Regenerate the API key for a user",
 *     tags={"Users"},
 *     security={{"BrowserSessionAuth":{}}},
 *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="New API key",
 *         @OA\JsonContent(@OA\Property(property="apiKey", type="string"))
 *     )
 * )
 */
function genAPIKey(Request $request, Response $response, array $args): Response
{
    $challenge = requireRecentAPIKeyManagementAuthentication($request, $response);
    if ($challenge !== null) {
        return $challenge;
    }
    /** @var User $user */
    $user = $request->getAttribute('user');
    $apiKey = $user->regenerateApiKeyIfCurrent($user->getApiKey());
    if ($apiKey === null) {
        throw new HttpConflictException($request, gettext('The API key changed. Reload the page and try again.'));
    }
    AuthenticationManager::rotateAuthenticatedSessionAfterSecurityMutation();
    try {
        $user->createTimeLineNote('api-key-regen');
    } catch (\Throwable $exception) {
        // The credential is already rotated. Audit-note availability must not
        // suppress delivery of the new key or undo session invalidation.
        try {
            LoggerUtils::getAppLogger()->warning('Unable to record API key regeneration timeline note', [
                'userId' => $user->getId(),
                'exception' => $exception,
            ]);
        } catch (\Throwable $loggingError) {
            // The security mutation and response remain authoritative.
        }
    }

    return noStoreAPIKeyResponse(SlimUtils::renderJSON($response, ['apiKey' => $apiKey]));
}

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
