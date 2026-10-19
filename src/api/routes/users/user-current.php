<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\LoggerUtils;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpConflictException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/user/current', function (RouteCollectorProxy $group): void {
    $group->post('/reauthenticate', 'reauthenticateCurrentUserSecurityAction')
        ->add(new CSRFMiddleware('account_security_action'));
    $group->post('/refresh2fasecret', 'refresh2fasecret')
        ->add(new CSRFMiddleware('account_security_action'));
    $group->post('/cancel2faenrollment', 'cancel2faenrollment')
        ->add(new CSRFMiddleware('account_security_action'));
    $group->post('/refresh2farecoverycodes', 'refresh2farecoverycodes')
        ->add(new CSRFMiddleware('account_security_action'));
    $group->post('/remove2fasecret', 'remove2fasecret')
        ->add(new CSRFMiddleware('account_security_action'));
    $group->post('/test2FAEnrollmentCode', 'test2FAEnrollmentCode')
        ->add(new CSRFMiddleware('account_security_action'));
    $group->get('/2fa-status', 'get2FAStatus');
});

function requireRecentAccountSecurityAuthentication(Request $request, Response $response): User|Response
{
    if (!AuthenticationManager::isCompletedLocalAuthentication()) {
        throw new HttpForbiddenException(
            $request,
            gettext('This action requires a signed-in browser session.')
        );
    }
    if (!AuthenticationManager::hasRecentSecurityActionAuthentication()) {
        return noStoreAccountSecurityResponse(SlimUtils::renderJSON(
            $response->withStatus(428),
            [
                'error' => gettext('Please confirm your current password before changing account security settings.'),
                'code' => 'reauthentication_required',
            ]
        ));
    }

    return AuthenticationManager::getCurrentUser();
}

function noStoreAccountSecurityResponse(Response $response): Response
{
    return $response
        ->withHeader('Cache-Control', 'no-store, private')
        ->withHeader('Pragma', 'no-cache');
}

function reauthenticateCurrentUserSecurityAction(Request $request, Response $response, array $args): Response
{
    if (!AuthenticationManager::isCompletedLocalAuthentication()) {
        throw new HttpForbiddenException($request, gettext('This action requires a signed-in browser session.'));
    }

    $body = $request->getParsedBody();
    if (!is_array($body)
        || !isset($body['currentPassword'])
        || !is_string($body['currentPassword'])
        || $body['currentPassword'] === ''
        || strlen($body['currentPassword']) > 1024) {
        throw new HttpBadRequestException($request, gettext('Current password is required.'));
    }

    if (!AuthenticationManager::reauthenticateForSecurityAction($body['currentPassword'])) {
        if (!AuthenticationManager::isCompletedLocalAuthentication()) {
            throw new HttpUnauthorizedException($request, gettext('The signed-in session is no longer valid.'));
        }

        return noStoreAccountSecurityResponse(SlimUtils::renderJSON(
            $response->withStatus(422),
            [
                'error' => gettext('Unable to confirm the current password.'),
                'code' => 'invalid_current_password',
            ]
        ));
    }

    // Keep the post-login session-wide token stable across tabs. The token was
    // regenerated at full login, and rotating it here would strand other open
    // security-settings tabs with an unrecoverably stale token.
    $newCSRFToken = CSRFUtils::generateToken('account_security_action');

    return noStoreAccountSecurityResponse(SlimUtils::renderJSON(
        $response,
        ['CSRFToken' => $newCSRFToken]
    ));
}

/**
 * @OA\Post(
 *     path="/user/current/refresh2fasecret",
 *     summary="Begin 2FA enrollment — provision a new TOTP secret and return a QR code data URI",
 *     tags={"2FA"},
 *     security={{"BrowserSessionAuth":{}}},
 *     @OA\Response(response=200, description="TOTP secret and QR code data URI for enrollment",
 *         @OA\JsonContent(
 *             @OA\Property(property="TwoFASecret", type="string", description="Base32 secret for manual authenticator setup"),
 *             @OA\Property(property="TwoFAQRCodeDataUri", type="string")
 *         )
 *     )
 * )
 */
function refresh2fasecret(Request $request, Response $response, array $args): Response
{
    $authentication = requireRecentAccountSecurityAuthentication($request, $response);
    if ($authentication instanceof Response) {
        return $authentication;
    }
    $user = $authentication;
    $secret = $user->provisionNew2FAKey();

    LoggerUtils::getAuthLogger()->info('Began 2FA enrollment for user: ' . $user->getUserName());

    $writer = new PngWriter();
    $qrCode = LocalAuthentication::getTwoFactorQRCode(
        $user->getUserName(),
        $secret
    );
    $result = $writer->write($qrCode);

    return noStoreAccountSecurityResponse(SlimUtils::renderJSON(
        $response,
        [
            'TwoFASecret' => $secret,
            'TwoFAQRCodeDataUri' => $result->getDataUri(),
        ]
    ));
}

function cancel2faenrollment(Request $request, Response $response, array $args): Response
{
    if (!AuthenticationManager::isCompletedLocalAuthentication()) {
        throw new HttpForbiddenException($request, gettext('This action requires a signed-in browser session.'));
    }

    AuthenticationManager::getCurrentUser()->clearProvisional2FAKey();

    return noStoreAccountSecurityResponse(SlimUtils::renderJSON($response, []));
}

/**
 * @OA\Post(
 *     path="/user/current/refresh2farecoverycodes",
 *     summary="Generate new 2FA recovery codes for the current user",
 *     tags={"2FA"},
 *     security={{"BrowserSessionAuth":{}}},
 *     @OA\Response(response=200, description="Array of new recovery codes",
 *         @OA\JsonContent(@OA\Property(property="TwoFARecoveryCodes", type="array", @OA\Items(type="string")))
 *     )
 * )
 */
function refresh2farecoverycodes(Request $request, Response $response, array $args): Response
{
    $authentication = requireRecentAccountSecurityAuthentication($request, $response);
    if ($authentication instanceof Response) {
        return $authentication;
    }
    $user = $authentication;
    $securityMarkers = AuthenticationManager::getAuthenticatedSecurityMarkers();
    $recoveryCodes = $user->getNewTwoFARecoveryCodes(
        $securityMarkers['passwordHash'],
        $securityMarkers['twoFactorSecret']
    );
    if ($recoveryCodes === null) {
        throw new HttpConflictException($request, gettext('Account security state changed. Please sign in again.'));
    }
    AuthenticationManager::synchronizeAuthenticatedRecoveryCodes($user->getTwoFactorAuthRecoveryCodes());

    return noStoreAccountSecurityResponse(SlimUtils::renderJSON(
        $response,
        ['TwoFARecoveryCodes' => $recoveryCodes]
    ));
}

/**
 * @OA\Post(
 *     path="/user/current/remove2fasecret",
 *     summary="Remove the 2FA secret from the current user (disables 2FA)",
 *     tags={"2FA"},
 *     security={{"BrowserSessionAuth":{}}},
 *     @OA\Response(response=200, description="2FA secret removed")
 * )
 */
function remove2fasecret(Request $request, Response $response, array $args): Response
{
    $authentication = requireRecentAccountSecurityAuthentication($request, $response);
    if ($authentication instanceof Response) {
        return $authentication;
    }
    $user = $authentication;
    $securityMarkers = AuthenticationManager::getAuthenticatedSecurityMarkers();
    if (!$user->remove2FAKey(
        $securityMarkers['passwordHash'],
        $securityMarkers['twoFactorSecret']
    )) {
        throw new HttpConflictException($request, gettext('Account security state changed. Please sign in again.'));
    }
    AuthenticationManager::synchronizeAuthenticatedTwoFactorSecret(null);

    return noStoreAccountSecurityResponse(SlimUtils::renderJSON($response, []));
}

/**
 * @OA\Post(
 *     path="/user/current/test2FAEnrollmentCode",
 *     summary="Validate a TOTP enrollment code to complete 2FA setup",
 *     tags={"2FA"},
 *     security={{"BrowserSessionAuth":{}}},
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
    if (!is_array($requestParsedBody)
        || !isset($requestParsedBody['enrollmentCode'])
        || !is_string($requestParsedBody['enrollmentCode'])
        || !preg_match('/^[0-9]{6}$/', $requestParsedBody['enrollmentCode'])) {
        throw new HttpBadRequestException($request, gettext('A six-digit enrollment code is required.'));
    }

    $authentication = requireRecentAccountSecurityAuthentication($request, $response);
    if ($authentication instanceof Response) {
        return $authentication;
    }
    $user = $authentication;
    $securityMarkers = AuthenticationManager::getAuthenticatedSecurityMarkers();
    $confirmedSecret = $user->confirmProvisional2FACode(
        $requestParsedBody['enrollmentCode'],
        $securityMarkers['passwordHash'],
        $securityMarkers['twoFactorSecret']
    );
    $result = $confirmedSecret !== null;
    if ($result) {
        AuthenticationManager::synchronizeAuthenticatedTwoFactorSecret($confirmedSecret);
        LoggerUtils::getAuthLogger()->info('Completed 2FA enrollment for user: ' . $user->getUserName());
    } else {
        LoggerUtils::getAuthLogger()->warning('Unsuccessful 2FA enrollment for user: ' . $user->getUserName());
    }

    return noStoreAccountSecurityResponse(SlimUtils::renderJSON($response, ['IsEnrollmentCodeValid' => $result]));
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

    return noStoreAccountSecurityResponse(SlimUtils::renderJSON($response, [
        'IsEnabled' => $isEnabled,
        'RequiresReauthentication' => !AuthenticationManager::hasRecentSecurityActionAuthentication(),
    ]));
}
