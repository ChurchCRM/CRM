<?php

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Slim\Middleware\Request\UserAPIMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
$app->group('/user/{userId:[0-9]+}', function (RouteCollectorProxy $group) {
    $group->post('/apikey/regen', 'genAPIKey');
    $group->post('/config/{key}', 'updateUserConfig');
})->add(UserAPIMiddleware::class);

function genAPIKey(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute('user');
    $user->setApiKey(User::randomApiKey());
    $user->save();
    $user->createTimeLineNote('api-key-regen');

    return $response->withJson(['apiKey' => $user->getApiKey()]);
}

function updateUserConfig(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute('user');
    $userConfigName = $args['key'];
    $parsedBody = (object) $request->getParsedBody();
    $newValue = $parsedBody->value;
    $user->setUserConfigString($userConfigName, $newValue);
    $user->save();
    if ($user->getUserConfigString($userConfigName) == $newValue) {
        return $response->withJson([$userConfigName => $newValue]);
    }
}
