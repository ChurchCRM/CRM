<?php

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Slim\Middleware\Request\UserAPIMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/user/{userId:[0-9]+}', function (RouteCollectorProxy $group): void {
    $group->post('/apikey/regen', 'genAPIKey');
    $group->post('/config/{key}', 'updateUserConfig');
})->add(UserAPIMiddleware::class);

function genAPIKey(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    $user->setApiKey(User::randomApiKey());
    $user->save();
    $user->createTimeLineNote('api-key-regen');

    return SlimUtils::renderJSON($response, ['apiKey' => $user->getApiKey()]);
}

function updateUserConfig(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    $userConfigName = $args['key'];
    $parsedBody = $request->getParsedBody();
    $newValue = $parsedBody['value'];
    $user->setUserConfigString($userConfigName, $newValue);
    $user->save();

    if ($user->getUserConfigString($userConfigName) !== $newValue) {
        throw new \Exception('user config string does not match provided value');
    }

    return SlimUtils::renderJSON($response, [$userConfigName => $newValue]);
}
