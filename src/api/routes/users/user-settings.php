<?php

use ChurchCRM\Slim\Middleware\Request\UserAPIMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/user/{userId:[0-9]+}/setting', function (RouteCollectorProxy $group): void {
    $group->get('/{settingName}', 'getUserSetting');
    $group->post('/{settingName}', 'updateUserSetting');
})->add(UserAPIMiddleware::class);

function getUserSetting(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    $settingName = $args['settingName'];
    $setting = $user->getSetting($settingName);
    $value = '';
    if ($setting) {
        $value = $setting->getValue();
    }

    return SlimUtils::renderJSON($response, ['value' => $value]);
}

function updateUserSetting(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    $settingName = $args['settingName'];

    $input = $request->getParsedBody();
    $user->setSetting($settingName, $input['value']);

    return SlimUtils::renderJSON($response, ['value' => $user->getSetting($settingName)->getValue()]);
}
