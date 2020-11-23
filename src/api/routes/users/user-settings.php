<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\UserSettings;
use ChurchCRM\Slim\Middleware\Request\UserAPIMiddleware;


$app->group('/user/{userId:[0-9]+}/setting', function () {
    $this->get("/{settingName}", "getUserSetting");
    $this->post("/{settingName}", "updateUserSetting");
})->add(new UserAPIMiddleware());

function getUserSetting(Request $request, Response $response, array $args)
{

    $user = $request->getAttribute("user");
    $settingName = $args['settingName'];
    $setting = $user->getSetting($settingName);
    if (!$setting) {
       return $response->withStatus(404, "not found: " . $settingName);
    }
    return $response->withJson(["value" => $setting->getValue()]);
}

function updateUserSetting(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute("user");
    $settingName = $args['settingName'];

    $input = (object)$request->getParsedBody();
    $user->setSetting($settingName, $input->value);
    return $response->withJson(["value" => $user->getSetting($settingName)->getValue()]);
}
