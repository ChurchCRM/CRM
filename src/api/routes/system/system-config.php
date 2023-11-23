<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system/config/{configName}', function (RouteCollectorProxy $group) {
    $group->get('', 'getConfigValueByNameAPI');
    $group->post('', 'setConfigValueByNameAPI');
    $group->get('/', 'getConfigValueByNameAPI');
    $group->post('/', 'setConfigValueByNameAPI');
})->add(AdminRoleAuthMiddleware::class);

function getConfigValueByNameAPI(Request $request, Response $response, array $args)
{
    return SlimUtils::renderJSON($response, ['value' => SystemConfig::getValue($args['configName'])]);
}

function setConfigValueByNameAPI(Request $request, Response $response, array $args)
{
    $configName = $args['configName'];
    $input = (object)$request->getParsedBody();
    SystemConfig::setValue($configName, $input->value);

    return SlimUtils::renderJSON($response, ['value' => SystemConfig::getValue($configName)]);
}
