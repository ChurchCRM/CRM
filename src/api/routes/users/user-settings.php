<?php

use ChurchCRM\Slim\Middleware\Request\UserAPIMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/user/{userId:[0-9]+}/setting', function (RouteCollectorProxy $group) {
    $group->get('/{settingName}', 'getUserSetting');
    $group->post('/{settingName}', 'updateUserSetting');
})->add(UserAPIMiddleware::class);

function getUserSetting(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute('user');
    $settingName = $args['settingName'];
    $setting = $user->getSetting($settingName);
    $value = '';
    if ($setting) {
        $value = $setting->getValue();
    }

    return $response->withJson(['value' => $value]);
}

function updateUserSetting(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute('user');
    $settingName = $args['settingName'];

    $input = (object) $request->getParsedBody();
    $user->setSetting($settingName, $input->value);

    return $response->withJson(['value' => $user->getSetting($settingName)->getValue()]);
}
