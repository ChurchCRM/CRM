<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\UserQuery;
use ChurchCRM\Utils\LoggerUtils;

$app->group('/user/current', function () {
    $this->post("/settings/show/finance", "updateSessionFinance");
    $this->post("/refresh2fasecret", "refresh2fasecret");
    $this->post("/refresh2farecoverycodes", "refresh2farecoverycodes");
    $this->post("/remove2fasecret", "remove2fasecret");
    $this->post("/test2FAEnrollmentCode", "test2FAEnrollmentCode");
    $this->get("/get2faqrcode",'get2faqrcode');
});

function updateSessionFinance(Request $request, Response $response, array $args)
{
    $user = AuthenticationManager::GetCurrentUser();

    if ($request->getContentLength() > 0) {
        $setting = (object)$request->getParsedBody();
        $user->setShowPledges(ConvertToBoolean($setting->pledges));
        $user->setShowPayments(ConvertToBoolean($setting->payments));
        $user->setShowSince($setting->since);
        $user->save();
    }

    return $response->withJson([
        "user" => $user->getName(),
        "userId" => $user->getId(),
        "showPledges" => $user->isShowPledges(),
        "showPayments" => $user->isShowPayments(),
        "showSince" => $user->getFormattedShowSince()
    ]);

}


function refresh2fasecret(Request $request, Response $response, array $args)
{
    $user = AuthenticationManager::GetCurrentUser();
    $secret = $user->provisionNew2FAKey();
    LoggerUtils::getAuthLogger()->addInfo("Began 2FA enrollment for user: " . $user->getUserName());
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
        LoggerUtils::getAuthLogger()->addInfo("Completed 2FA enrollment for user: " . $user->getUserName());
    }
    else {
        LoggerUtils::getAuthLogger()->addNotice("Unsuccessful 2FA enrollment for user: " . $user->getUserName());
    }
    return $response->withJson(["IsEnrollmentCodeValid" => $result]);
}
