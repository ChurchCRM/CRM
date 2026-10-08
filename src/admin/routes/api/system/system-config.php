<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Volunteer\Service\VolunteerEventService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/system/config/{configName}', function (RouteCollectorProxy $group): void {
    $group->get('', 'getConfigValueByNameAPI');
    $group->post('', 'setConfigValueByNameAPI');
    $group->get('/', 'getConfigValueByNameAPI');
    $group->post('/', 'setConfigValueByNameAPI');
})->add(AdminRoleAuthMiddleware::class);

/**
 * @OA\Get(
 *     path="/admin/api/system/config/{configName}",
 *     operationId="getSystemConfigValue",
 *     summary="Get the current value of a system setting",
 *     description="Returns the stored value, or the shipped default when none is stored. Password settings always return an empty value.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="configName", in="path", required=true, @OA\Schema(type="string"), example="iSMTPTimeout"),
 *     @OA\Response(response=200, description="Setting value",
 *         @OA\JsonContent(@OA\Property(property="value", type="string", example="10"))
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=404, description="Configuration item not found")
 * )
 */
function getConfigValueByNameAPI(Request $request, Response $response, array $args): Response
{
    $configName = $args['configName'];
    $configItem = SystemConfig::getConfigItem($configName);
    if ($configItem === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Configuration item not found'), [], 404, null, $request);
    }

    // Never return password values to the browser
    if ($configItem->getType() === 'password') {
        return SlimUtils::renderJSON($response, ['value' => '']);
    }

    return SlimUtils::renderJSON($response, ['value' => SystemConfig::getValue($configName)]);
}

/**
 * @OA\Post(
 *     path="/admin/api/system/config/{configName}",
 *     operationId="setSystemConfigValue",
 *     summary="Set the value of a system setting",
 *     description="An empty value for a password setting keeps the stored password. A number setting takes a number; it takes an empty value only when its shipped default is empty.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="configName", in="path", required=true, @OA\Schema(type="string"), example="iSMTPTimeout"),
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(@OA\Property(property="value", type="string", example="10"))
 *     ),
 *     @OA\Response(response=200, description="Setting saved (password settings return an empty value)",
 *         @OA\JsonContent(@OA\Property(property="value", type="string", example="10"))
 *     ),
 *     @OA\Response(response=400, description="A number setting was given a value that is not a number"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=404, description="Configuration item not found")
 * )
 */
function setConfigValueByNameAPI(Request $request, Response $response, array $args): Response
{
    $configName = $args['configName'];
    $configItem = SystemConfig::getConfigItem($configName);
    if ($configItem === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Configuration item not found'), [], 404, null, $request);
    }

    $input = $request->getParsedBody();
    $value = $input['value'] ?? '';
    $isPassword = $configItem->getType() === 'password';

    // Never overwrite a password with an empty value
    if ($isPassword && empty($value)) {
        return SlimUtils::renderJSON($response, ['value' => '']);
    }

    $blankAllowed = $value === '' && $configItem->getDefault() === '';
    if ($configItem->getType() === 'number' && !is_numeric($value) && !$blankAllowed) {
        return SlimUtils::renderErrorJSON($response, gettext('This setting must be a number'), [], 400, null, $request);
    }

    $volunteerV2WasOn = User::isVolunteerV2Enabled();

    // Sanitization is applied centrally in SystemConfig::setValue() — no duplicate call here.
    SystemConfig::setValue($configName, $value);

    // #10357: V2's default type for ministry events arrives when V2 is turned on, not on upgrade.
    if ($configName === 'sVolunteerVersion' && !$volunteerV2WasOn && User::isVolunteerV2Enabled()) {
        VolunteerEventService::addOtherEventType();
    }

    // Never return the saved value for password types
    return SlimUtils::renderJSON($response, ['value' => $isPassword ? '' : SystemConfig::getValue($configName)]);
}
