<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/system/config/{configName}', function (RouteCollectorProxy $group): void {
    $group->get('', 'getConfigValueByNameAPI');
    $group->post('', 'setConfigValueByNameAPI');
    $group->get('/', 'getConfigValueByNameAPI');
    $group->post('/', 'setConfigValueByNameAPI');
})->add(AdminRoleAuthMiddleware::class);

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

    // A settings panel saved before it had loaded its values sends blanks; for a number that
    // ships with a value, a blank is never meant (a reminder lead time of blank reads as 0).
    if ($configItem->getType() === 'number') {
        $trimmed = trim((string) $value);
        $blankAllowed = $trimmed === '' && (string) $configItem->getDefault() === '';
        if (!$blankAllowed && !is_numeric($trimmed)) {
            return SlimUtils::renderErrorJSON(
                $response,
                sprintf(gettext('%s must be a number'), $configName),
                [],
                400,
                null,
                $request
            );
        }
        $value = $trimmed;
    }

    // Sanitization is applied centrally in SystemConfig::setValue() — no duplicate call here.
    SystemConfig::setValue($configName, $value);

    // Never return the saved value for password types
    return SlimUtils::renderJSON($response, ['value' => $isPassword ? '' : SystemConfig::getValue($configName)]);
}
