<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/system/config/{configName}', function () {
    $this->get('', 'getConfigValueByNameAPI');
    $this->post('', 'setConfigValueByNameAPI');
    $this->get('/', 'getConfigValueByNameAPI');
    $this->post('/', 'setConfigValueByNameAPI');
})->add(new AdminRoleAuthMiddleware());

function getConfigValueByNameAPI(Request $request, Response $response, array $args)
{
    return $response->withJson(["value" => SystemConfig::getValue($args['configName'])]);
}

function setConfigValueByNameAPI(Request $request, Response $response, array $args)
{
    $configName = $args['configName'];
    $input = (object)$request->getParsedBody();
    SystemConfig::setValue($configName, $input->value);
    return $response->withJson(["value" => SystemConfig::getValue($configName)]);
}
