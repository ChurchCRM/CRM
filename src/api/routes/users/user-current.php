<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/user/current', function (RouteCollectorProxy $group): void {
    $group->post('/refresh2fasecret', 'refresh2fasecret');
    $group->post('/refresh2farecoverycodes', 'refresh2farecoverycodes');
    $group->post('/remove2fasecret', 'remove2fasecret');
    $group->post('/test2FAEnrollmentCode', 'test2FAEnrollmentCode');
    $group->get('/get2faqrcode', 'get2faqrcode');
});

function refresh2fasecret(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    $secret = $user->provisionNew2FAKey();

    LoggerUtils::getAuthLogger()->info('Began 2FA enrollment for user: ' . $user->getUserName());

    $writer = new PngWriter();
    $qrCode = LocalAuthentication::getTwoFactorQRCode(
        $user->getUserName(),
        $secret
    );
    $result = $writer->write($qrCode);

    return SlimUtils::renderJSON(
        $response,
        [
            'TwoFAQRCodeDataUri' => $result->getDataUri()
        ]
    );
}

function refresh2farecoverycodes(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();

    return SlimUtils::renderJSON($response, ['TwoFARecoveryCodes' => $user->getNewTwoFARecoveryCodes()]);
}

function remove2fasecret(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    $user->remove2FAKey();

    return SlimUtils::renderJSON($response, []);
}

function get2faqrcode(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    $response = $response->withHeader('Content-Type', 'image/png');
    $response->getBody()
        ->write(
            LocalAuthentication::getTwoFactorQRCode(
                $user->getUserName(),
                $user->getDecryptedTwoFactorAuthSecret()
            )->writeString()
        );

    return $response;
}

function test2FAEnrollmentCode(Request $request, Response $response, array $args): Response
{
    $requestParsedBody = $request->getParsedBody();
    $user = AuthenticationManager::getCurrentUser();
    $result = $user->confirmProvisional2FACode($requestParsedBody['enrollmentCode']);
    if ($result) {
        LoggerUtils::getAuthLogger()->info('Completed 2FA enrollment for user: ' . $user->getUserName());
    } else {
        LoggerUtils::getAuthLogger()->notice('Unsuccessful 2FA enrollment for user: ' . $user->getUserName());
    }

    return SlimUtils::renderJSON($response, ['IsEnrollmentCodeValid' => $result]);
}
