<?php

use ChurchCRM\Slim\Middleware\Request\UserAPIMiddleware;
use ChurchCRM\User;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/user/{userId:[0-9]+}', function () {
    $this->post("/apikey/regen", "genAPIKey");
    $this->post("/config/{key}", "updateUserConfig");
})->add(new UserAPIMiddleware());

function genAPIKey(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute("user");
    $user->setApiKey(User::randomApiKey());
    $user->save();
    $user->createTimeLineNote("api-key-regen");
    return $response->withJson(["apiKey" => $user->getApiKey()]);
}


function updateUserConfig(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute("user");
    $userConfigName = $args['key'];
    $parsedBody = (object) $request->getParsedBody();
    $newValue = $parsedBody->value;
    $user->setUserConfigString($userConfigName, $newValue);
    $user->save();
    if ($user->getUserConfigString($userConfigName) == $newValue) {
        return $response->withJson([$userConfigName => $newValue]);
    }
};
