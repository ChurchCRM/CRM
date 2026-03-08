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

    SystemConfig::setValue($configName, $value);

    // Never return the saved value for password types
    return SlimUtils::renderJSON($response, ['value' => $isPassword ? '' : SystemConfig::getValue($configName)]);
}
