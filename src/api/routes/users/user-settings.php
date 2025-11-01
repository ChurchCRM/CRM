<?php

use ChurchCRM\Slim\Middleware\Api\UserMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/user/{userId:[0-9]+}/setting', function (RouteCollectorProxy $group): void {
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

    $group->post('/{settingName}', function (Request $request, Response $response, array $args): Response {
        $user = $request->getAttribute('user');
        $settingName = $args['settingName'];

        $input = $request->getParsedBody();
        $user->setSetting($settingName, $input['value']);

        return SlimUtils::renderJSON($response, ['value' => $user->getSetting($settingName)->getValue()]);
    });
})->add(UserMiddleware::class);
