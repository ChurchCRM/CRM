<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Slim\SlimUtils;
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
    $group->get('/2fa-status', 'get2FAStatus');
});

/**
 * @OA\Post(
 *     path="/user/current/refresh2fasecret",
 *     summary="Begin 2FA enrollment — provision a new TOTP secret and return a QR code data URI",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="QR code data URI for TOTP enrollment",
 *         @OA\JsonContent(@OA\Property(property="TwoFAQRCodeDataUri", type="string"))
 *     )
 * )
 */
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

/**
 * @OA\Post(
 *     path="/user/current/refresh2farecoverycodes",
 *     summary="Generate new 2FA recovery codes for the current user",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Array of new recovery codes",
 *         @OA\JsonContent(@OA\Property(property="TwoFARecoveryCodes", type="array", @OA\Items(type="string")))
 *     )
 * )
 */
function refresh2farecoverycodes(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();

    return SlimUtils::renderJSON($response, ['TwoFARecoveryCodes' => $user->getNewTwoFARecoveryCodes()]);
}

/**
 * @OA\Post(
 *     path="/user/current/remove2fasecret",
 *     summary="Remove the 2FA secret from the current user (disables 2FA)",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="2FA secret removed")
 * )
 */
function remove2fasecret(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    $user->remove2FAKey();

    return SlimUtils::renderJSON($response, []);
}

/**
 * @OA\Get(
 *     path="/user/current/get2faqrcode",
 *     summary="Get the current user's 2FA QR code as a PNG image",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="PNG image of the 2FA QR code",
 *         @OA\MediaType(mediaType="image/png")
 *     )
 * )
 */
function get2faqrcode(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    $qrCode = LocalAuthentication::getTwoFactorQRCode(
        $user->getUserName(),
        $user->getDecryptedTwoFactorAuthSecret()
    );

    $writer = new PngWriter();
    $result = $writer->write($qrCode);

    $response = $response->withHeader('Content-Type', 'image/png');
    $response->getBody()->write($result->getString());

    return $response;
}

/**
 * @OA\Post(
 *     path="/user/current/test2FAEnrollmentCode",
 *     summary="Validate a TOTP enrollment code to complete 2FA setup",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(@OA\Property(property="enrollmentCode", type="string"))
 *     ),
 *     @OA\Response(response=200, description="Whether the enrollment code is valid",
 *         @OA\JsonContent(@OA\Property(property="IsEnrollmentCodeValid", type="boolean"))
 *     )
 * )
 */
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

/**
 * @OA\Get(
 *     path="/user/current/2fa-status",
 *     summary="Get the 2FA enabled status for the current user",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="2FA enabled status",
 *         @OA\JsonContent(@OA\Property(property="IsEnabled", type="boolean"))
 *     )
 * )
 */
function get2FAStatus(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    $isEnabled = $user->is2FactorAuthEnabled();

    return SlimUtils::renderJSON($response, ['IsEnabled' => $isEnabled]);
}
