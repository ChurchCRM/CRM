<?php

use ChurchCRM\Emails\users\LockedEmail;
use ChurchCRM\Emails\users\ResetPasswordTokenEmail;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public/user', function (RouteCollectorProxy $group): void {
    $group->post('/login', 'userLogin');
    $group->post('/login/', 'userLogin');
    $group->post('/password-reset', 'passwordResetRequest');
});

/**
 * @OA\Post(
 *     path="/public/user/login",
 *     operationId="userLogin",
 *     summary="Log in and retrieve an API key",
 *     description="Authenticates a user by username and password and returns their API key for use in subsequent authenticated requests.",
 *     tags={"Auth"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"userName","password"},
 *             @OA\Property(property="userName", type="string", example="admin"),
 *             @OA\Property(property="password", type="string", format="password", example="secret"),
 *             @OA\Property(property="otp", type="string", description="One-time password or recovery code for 2FA (required when 202 is returned)", example="123456")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Login successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="apiKey", type="string", example="abc123xyz")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Invalid username or password"),
 *     @OA\Response(
 *         response=202,
 *         description="Password valid but 2FA verification required",
 *         @OA\JsonContent(
 *             @OA\Property(property="requiresOTP", type="boolean", example=true)
 *         )
 *     )
 * )
 */
function userLogin(Request $request, Response $response, array $args): Response
{
    $logger = LoggerUtils::getAuthLogger();
    $body = json_decode($request->getBody(), true, 512, JSON_THROW_ON_ERROR);

    // Use a generic error message to prevent username enumeration
    $genericError = gettext('Invalid login or password');

    if (empty($body['userName'])) {
        throw new HttpUnauthorizedException($request, $genericError);
    }

    $user = UserQuery::create()->findOneByUserName($body['userName']);
    if ($user === null) {
        // Return same error as wrong password to prevent username enumeration
        $logger->warning('API login attempt for non-existent user', ['username' => $body['userName']]);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    $password = $body['password'] ?? '';
    if ($user->getSignInBlockedReason() !== null) {
        throw new HttpUnauthorizedException($request, $genericError);
    }
    if ($user->getSignInBlockedReason() !== null) {
        throw new HttpUnauthorizedException($request, $genericError);
    }
    $primaryAuthentication = $user->authenticatePrimaryPassword($password);
    $authenticatedRecoveryCodes = $primaryAuthentication['recoveryCodes'];
    if ($primaryAuthentication['isLocked']) {
        if ($primaryAuthentication['accountBecameLocked']) {
            $logger->warning('API login: account locked after too many failures', ['username' => $user->getUserName()]);
            if (!empty($user->getEmail())) {
                $lockedEmail = new LockedEmail($user);
                $lockedEmail->send();
            }
        }

        $logger->warning('API login attempt for locked account', ['username' => $user->getUserName()]);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    if (!$primaryAuthentication['isPasswordValid']) {

        $logger->warning('API login: invalid password', ['username' => $user->getUserName()]);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    if (!$user->isApiAuthenticationEligible($primaryAuthentication['twoFactorSecret'] !== null)) {
        $logger->warning('API login rejected because required account security steps are incomplete', ['username' => $user->getUserName()]);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    // Check 2FA enrollment BEFORE resetting failed logins (only reset on full auth)
    if ($primaryAuthentication['twoFactorSecret'] !== null) {
        if ($user->isTwoFactorAuthRateLimited()) {
            $logger->warning('API login rejected while two-factor rate limit is active', ['username' => $user->getUserName()]);
            throw new HttpUnauthorizedException($request, $genericError);
        }

        $otp = $body['otp'] ?? null;

        if (empty($otp)) {
            // No OTP provided — tell client to prompt for it (don't reset failed logins yet)
            return SlimUtils::renderJSON($response, ['requiresOTP' => true], 202);
        }

        $factorAuthenticationResult = $user->authenticateTwoFactorCode(
            $otp,
            $primaryAuthentication['passwordHash'],
            $primaryAuthentication['twoFactorSecret']
        );
        if ($factorAuthenticationResult === User::TWO_FACTOR_AUTHENTICATION_RATE_LIMITED) {
            $logger->warning('API login rejected after reaching the two-factor attempt limit', ['username' => $user->getUserName()]);
            throw new HttpUnauthorizedException($request, $genericError);
        }
        if ($factorAuthenticationResult === User::TWO_FACTOR_AUTHENTICATION_REVOKED) {
            $logger->warning('API login rejected after primary authentication was revoked', ['username' => $user->getUserName()]);
            throw new HttpUnauthorizedException($request, $genericError);
        }
        if (!in_array($factorAuthenticationResult, [
            User::TWO_FACTOR_AUTHENTICATION_TOTP,
            User::TWO_FACTOR_AUTHENTICATION_RECOVERY,
        ], true)) {
            $logger->warning('API login: invalid 2FA code', ['username' => $user->getUserName()]);
            throw new HttpUnauthorizedException($request, $genericError);
        }
        if ($factorAuthenticationResult === User::TWO_FACTOR_AUTHENTICATION_RECOVERY) {
            $authenticatedRecoveryCodes = $user->getTwoFactorAuthRecoveryCodes();
        }

        // Factor validation atomically persists TOTP replay state or recovery-code
        // consumption before this request can complete authentication.
    }

    // Re-check lock state while atomically finalizing authentication. This
    // prevents a concurrent failed password request from being overwritten.
    if (!$user->finalizeSuccessfulAuthentication(
        $primaryAuthentication['passwordHash'],
        $primaryAuthentication['twoFactorSecret'],
        $authenticatedRecoveryCodes
    )) {
        $logger->warning('API login rejected because the account locked before authentication completed', ['username' => $user->getUserName()]);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    return SlimUtils::renderJSON($response, ['apiKey' => $user->getApiKey()])
        ->withHeader('Cache-Control', 'no-store, private')
        ->withHeader('Pragma', 'no-cache');
}

/**
 * @OA\Post(
 *     path="/public/user/password-reset",
 *     operationId="passwordResetRequest",
 *     summary="Request a password reset email",
 *     description="Sends a password reset link to the email address associated with the given username. Always returns success to avoid user enumeration.",
 *     tags={"Auth"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"userName"},
 *             @OA\Property(property="userName", type="string", example="admin")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Request accepted (email sent if account exists)",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(response=400, description="userName field is required")
 * )
 */
function passwordResetRequest(Request $request, Response $response, array $args): Response
{
    $logger = LoggerUtils::getAppLogger();

    try {
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        throw new HttpBadRequestException($request, gettext('Invalid password reset request.'));
    }
    if (!is_array($body)
        || !isset($body['userName'])
        || !is_string($body['userName'])
        || strlen($body['userName']) > 255) {
        throw new HttpBadRequestException($request, gettext('Invalid password reset request.'));
    }
    $userName = trim($body['userName']);

    if (empty($userName)) {
        throw new HttpBadRequestException($request, gettext('Login Name is required'));
    }

    $user = UserQuery::create()->findOneByUserName($userName);
    if (empty($user) || empty($user->getEmail())) {
        // Don't reveal whether user exists (security best practice)
        $logger->warning('Password reset requested for non-existent user: ' . $userName);
        return SlimUtils::renderJSON($response, ['success' => true]);
    }

    if ($user->getSignInBlockedReason() !== null) {
        return SlimUtils::renderJSON($response, ['success' => true]);
    }
    if ($user->getSignInBlockedReason() !== null) {
        return SlimUtils::renderJSON($response, ['success' => true]);
    }
    $token = $user->issuePasswordResetToken();

    $email = new ResetPasswordTokenEmail($user, $token->getToken());
    if (!$email->send()) {
        $logger->error('Failed to send password reset email for user ' . $user->getUserName() . ': ' . $email->getError());
        $token->delete();
        // Still return success to user (don't expose email issues)
        return SlimUtils::renderJSON($response, ['success' => true]);
    }

    $logger->info('Password reset token sent for user: ' . $user->getUserName());
    return SlimUtils::renderJSON($response, ['success' => true]);
}
