<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Utils\LoggerUtils;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/user/current', function () {
    $this->post("/refresh2fasecret", "refresh2fasecret");
    $this->post("/refresh2farecoverycodes", "refresh2farecoverycodes");
    $this->post("/remove2fasecret", "remove2fasecret");
    $this->post("/test2FAEnrollmentCode", "test2FAEnrollmentCode");
    $this->get("/get2faqrcode",'get2faqrcode');
});

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
