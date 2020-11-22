<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\UserSettings;

$app->group('/user/current', function () {
    $this->get("/settings/{settingName}", "getUserSetting");
    $this->post("/settings/{settingName}", "updateUserSetting");
    $this->post("/settings/show/finance", "updateSessionFinance");
    $this->post("/refresh2fasecret", "refresh2fasecret");
    $this->post("/refresh2farecoverycodes", "refresh2farecoverycodes");
    $this->post("/remove2fasecret", "remove2fasecret");
    $this->post("/test2FAEnrollmentCode", "test2FAEnrollmentCode");
    $this->get("/get2faqrcode",'get2faqrcode');
});

function getUserSetting(Request $request, Response $response, array $args)
{

    $user = AuthenticationManager::GetCurrentUser();
    $settingName = $args['settingName'];
    $setting = $user->getSetting($settingName);
    if (!$setting) {
       return $response->withStatus(404, "not found: " . $settingName);
    }
    return $response->withJson(["value" => $setting->getValue()]);
}

function updateUserSetting(Request $request, Response $response, array $args)
{
    $user = AuthenticationManager::GetCurrentUser();
    $settingName = $args['settingName'];

    $input = (object)$request->getParsedBody();
    $user->setSetting($settingName, $input->value);
    return $response->withJson(["value" => $user->getSetting($settingName)->getValue()]);
}

function updateSessionFinance(Request $request, Response $response, array $args)
{
    $user = AuthenticationManager::GetCurrentUser();

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


function refresh2fasecret(Request $request, Response $response, array $args)
{
    $user = AuthenticationManager::GetCurrentUser();
    $secret = $user->provisionNew2FAKey();
    LoggerUtils::getAuthLogger()->info("Began 2FA enrollment for user: " . $user->getUserName());
    return $response->withJson(["TwoFAQRCodeDataUri" => LocalAuthentication::GetTwoFactorQRCode($user->getUserName(),$secret)->writeDataUri()]);
}

function refresh2farecoverycodes(Request $request, Response $response, array $args)
{
    $user = AuthenticationManager::GetCurrentUser();
    return $response->withJson(["TwoFARecoveryCodes" => $user->getNewTwoFARecoveryCodes()]);
}

function remove2fasecret(Request $request, Response $response, array $args)
{
    $user = AuthenticationManager::GetCurrentUser();
    $user->remove2FAKey();
    return $response->withJson([]);
}

function get2faqrcode(Request $request, Response $response, array $args)
{
    $user = AuthenticationManager::GetCurrentUser();
    $response = $response->withHeader("Content-Type", "image/png");
    return $response->write(LocalAuthentication::GetTwoFactorQRCode($user->getUserName(),$user->getDecryptedTwoFactorAuthSecret())->writeString());
}

function test2FAEnrollmentCode(Request $request, Response $response, array $args)
{
    $requestParsedBody = (object)$request->getParsedBody();
    $user = AuthenticationManager::GetCurrentUser();
    $result = $user->confirmProvisional2FACode($requestParsedBody->enrollmentCode);
    if ($result) {
        LoggerUtils::getAuthLogger()->info("Completed 2FA enrollment for user: " . $user->getUserName());
    }
    else {
        LoggerUtils::getAuthLogger()->notice("Unsuccessful 2FA enrollment for user: " . $user->getUserName());
    }
    return $response->withJson(["IsEnrollmentCodeValid" => $result]);
}
