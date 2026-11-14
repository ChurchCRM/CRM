<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Authentication\SignInThrottle;
use ChurchCRM\model\ChurchCRM\User;
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
    $group->post('/2fa/confirm', 'confirmTwoFactorChange');
});

/**
 * Changes to a user's existing 2FA need proof that the person at the keyboard
 * owns the account, not just a live session or API key (#10200): the body's
 * `confirmation` must be their current password or a current authenticator
 * code. A successful confirmation, or finishing enrollment, is remembered in
 * the session for TWO_FACTOR_CONFIRMATION_TTL seconds so the next step of the
 * same flow doesn't ask again. A user with no 2FA yet can enroll without it.
 * Wrong answers count as failed sign-ins, so they lock the account like
 * wrong passwords do.
 */
const TWO_FACTOR_CONFIRMATION_TTL = 300;

function twoFactorChangeConfirmed(Request $request, User $user): bool
{
    if (!$user->is2FactorAuthEnabled()) {
        return true;
    }
    if (session_status() === PHP_SESSION_ACTIVE && ($_SESSION['twoFactorChangeConfirmedUntil'] ?? 0) >= time()) {
        return true;
    }

    $body = $request->getParsedBody();
    $confirmation = is_array($body) && is_string($body['confirmation'] ?? null) ? trim($body['confirmation']) : '';
    if ($confirmation === '' || SignInThrottle::isAccountLocked($user)) {
        return false;
    }
    $valid = (preg_match('/^\d{6}$/', $confirmation) === 1 && $user->isTwoFACodeValid($confirmation))
        || $user->isPasswordValid($confirmation);
    if (!$valid) {
        SignInThrottle::recordFailure($user);
        LoggerUtils::getAuthLogger()->warning('2FA change refused: wrong confirmation for user: ' . $user->getUserName());

        return false;
    }
    rememberTwoFactorChangeConfirmed();

    return true;
}

function rememberTwoFactorChangeConfirmed(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['twoFactorChangeConfirmedUntil'] = time() + TWO_FACTOR_CONFIRMATION_TTL;
    }
}

function twoFactorConfirmationRequired(Response $response): Response
{
    return SlimUtils::renderJSON(
        $response->withStatus(403),
        ['success' => false, 'confirmationRequired' => true, 'message' => gettext('Enter your password or a code from your authenticator app to change two-factor authentication.')]
    );
}

/**
 * @OA\Post(
 *     path="/user/current/2fa/confirm",
 *     summary="Confirm the current password or an authenticator code before changing existing 2FA",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"confirmation"}, @OA\Property(property="confirmation", type="string", description="Current password or 6-digit authenticator code"))),
 *     @OA\Response(response=200, description="Confirmed for the next few minutes of this session"),
 *     @OA\Response(response=403, description="Wrong or missing confirmation")
 * )
 */
function confirmTwoFactorChange(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    if (!twoFactorChangeConfirmed($request, $user)) {
        return twoFactorConfirmationRequired($response);
    }

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/user/current/refresh2fasecret",
 *     summary="Begin 2FA enrollment — provision a TOTP secret and return its QR code and manual setup key",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="QR code and Base32 setup key for TOTP enrollment",
 *         @OA\JsonContent(
 *             @OA\Property(property="TwoFAQRCodeDataUri", type="string"),
 *             @OA\Property(property="TwoFASecret", type="string")
 *         )
 *     ),
 *     @OA\Response(response=403, description="2FA is set up and the confirmation (password or authenticator code) is missing or wrong")
 * )
 */
function refresh2fasecret(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    if (!twoFactorChangeConfirmed($request, $user)) {
        return twoFactorConfirmationRequired($response);
    }
    $secret = $user->provisionNew2FAKey();

    LoggerUtils::getAuthLogger()->info('Began 2FA enrollment for user: ' . $user->getUserName());

    $writer = new PngWriter();
    $qrCode = LocalAuthentication::getTwoFactorQRCode(
        $user->getUserName(),
        $secret
    );
    $result = $writer->write($qrCode);

    // Enrollment credentials must never be stored by a browser or intermediary cache.
    $response = $response->withHeader('Cache-Control', 'no-store, private');

    return SlimUtils::renderJSON(
        $response,
        [
            'TwoFAQRCodeDataUri' => $result->getDataUri(),
            'TwoFASecret' => $secret
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
 *     ),
 *     @OA\Response(response=403, description="2FA is set up and the confirmation (password or authenticator code) is missing or wrong")
 * )
 */
function refresh2farecoverycodes(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    if (!twoFactorChangeConfirmed($request, $user)) {
        return twoFactorConfirmationRequired($response);
    }

    return SlimUtils::renderJSON($response, ['TwoFARecoveryCodes' => $user->getNewTwoFARecoveryCodes()]);
}

/**
 * @OA\Post(
 *     path="/user/current/remove2fasecret",
 *     summary="Remove the 2FA secret from the current user (disables 2FA)",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="2FA secret removed"),
 *     @OA\Response(response=403, description="2FA is set up and the confirmation (password or authenticator code) is missing or wrong")
 * )
 */
function remove2fasecret(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    if (!twoFactorChangeConfirmed($request, $user)) {
        return twoFactorConfirmationRequired($response);
    }
    $user->remove2FAKey();

    return SlimUtils::renderJSON($response, []);
}

/**
 * @OA\Get(
 *     path="/user/current/get2faqrcode",
 *     summary="Retired: the 2FA QR code is returned only by refresh2fasecret during enrollment",
 *     tags={"2FA"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=404, description="Not enrolled: start enrollment with refresh2fasecret"),
 *     @OA\Response(response=409, description="Already enrolled: the live secret is never shown again")
 * )
 */
function get2faqrcode(Request $request, Response $response, array $args): Response
{
    $user = AuthenticationManager::getCurrentUser();
    // The live secret is shown only while enrolling (refresh2fasecret), never
    // again for a user who has already set up 2FA (#10200).
    if ($user->is2FactorAuthEnabled()) {
        return SlimUtils::renderErrorJSON($response, gettext('Two-factor authentication is already set up. Its secret is shown only during enrollment.'), [], 409);
    }

    return SlimUtils::renderErrorJSON($response, gettext('Start enrollment to get a QR code.'), [], 404);
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
 *     ),
 *     @OA\Response(response=403, description="2FA is set up and the confirmation (password or authenticator code) is missing or wrong")
 * )
 */
function test2FAEnrollmentCode(Request $request, Response $response, array $args): Response
{
    $requestParsedBody = $request->getParsedBody();
    $user = AuthenticationManager::getCurrentUser();
    // Replacing a live secret is a change to existing 2FA.
    if (!twoFactorChangeConfirmed($request, $user)) {
        return twoFactorConfirmationRequired($response);
    }
    $result = $user->confirmProvisional2FACode((string) ($requestParsedBody['enrollmentCode'] ?? ''));
    if ($result) {
        // The wizard asks for recovery codes right after this.
        rememberTwoFactorChangeConfirmed();
        LoggerUtils::getAuthLogger()->info('Completed 2FA enrollment for user: ' . $user->getUserName());
    } else {
        LoggerUtils::getAuthLogger()->warning('Unsuccessful 2FA enrollment for user: ' . $user->getUserName());
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
