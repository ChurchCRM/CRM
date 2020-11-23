<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\UserSettings;
use ChurchCRM\Slim\Middleware\Request\UserAPIMiddleware;


$app->group('/user/{userId:[0-9]+}/setting', function () {
    $this->get("/{settingName}", "getUserSetting");
    $this->post("/{settingName}", "updateUserSetting");
    $this->post("/finance", "updateSessionFinance");
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

function updateSessionFinance(Request $request, Response $response, array $args)
{
    $user = $request->getAttribute("user");

   if ($request->getContentLength() > 0) {
        $setting = (object)$request->getParsedBody();

        $user->setSetting( "finance.show.pledges", $setting->pledges);
        $user->setSetting( "finance.show.payments", $setting->payments);
        $user->setSetting( "finance.show.since", $setting->since);
    }

   $tempSetting = $user->getSetting("finance.show.pledges");
   $pledges = $tempSetting? $tempSetting->getValue() : "";

   $tempSetting = $user->getSetting("finance.show.payments");
    $payments = $tempSetting? $tempSetting->getValue() : "";

    $tempSetting = $user->getSetting("finance.show.since");
    $since = $tempSetting? $tempSetting->getValue() : "";

    return $response->withJson([
        "user" => $user->getName(),
        "userId" => $user->getId(),
        "showPledges" => $pledges,
        "showPayments" => $payments,
        "showSince" => $since
    ]);

}
