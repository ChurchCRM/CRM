<?php

use ChurchCRM\Authentication\SignInThrottle;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\UserService;
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
 *     @OA\Response(response=401, description="Invalid username or password. Unknown, locked and throttled accounts get the same answer."),
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
    $body = json_decode($request->getBody(), true, 512);
    $password = (string) ($body['password'] ?? '');

    // One answer for every refusal, so a caller cannot tell an unknown, locked,
    // throttled or blocked account from a wrong password.
    $genericError = gettext('Invalid login or password');

    if (empty($body['userName'])) {
        throw new HttpUnauthorizedException($request, $genericError);
    }
    $logCtx = ['username' => $body['userName']];

    if (SignInThrottle::isIpThrottled()) {
        SignInThrottle::spendPasswordCheckTime($password);
        $logger->warning('API login refused: too many failed logins from this address', $logCtx);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    $user = UserQuery::create()->findOneByUserName($body['userName']);
    if ($user === null) {
        SignInThrottle::spendPasswordCheckTime($password);
        SignInThrottle::recordFailure(null);
        $logger->warning('API login attempt for non-existent user', $logCtx);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    if ($user->isLocked()) {
        SignInThrottle::spendPasswordCheckTime($password);
        SignInThrottle::recordFailure(null);
        $logger->warning('API login attempt for locked account', $logCtx);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    if (!$user->isPasswordValid($password)) {
        SignInThrottle::recordFailure($user);
        $logger->warning('API login: invalid password', $logCtx);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    $blockedReason = $user->getSignInBlockedReason();
    if ($blockedReason !== null) {
        $logger->warning('API login refused: account cannot sign in', $logCtx + ['reason' => $blockedReason]);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    // A self-service account signs in to the Member Portal in a browser. It never receives an API key.
    if ($user->isEditSelfExclusive()) {
        $logger->warning('API login refused: self-service account', ['username' => $user->getUserName()]);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    // Check 2FA enrollment BEFORE resetting failed logins (only reset on full auth)
    if ($user->is2FactorAuthEnabled()) {
        $otp = $body['otp'] ?? null;

        if (empty($otp)) {
            // No OTP provided — tell client to prompt for it (don't reset failed logins yet)
            return SlimUtils::renderJSON($response, ['requiresOTP' => true], 202);
        }

        // Validate OTP or recovery code
        $otpValid = $user->isTwoFACodeValid($otp);
        $recoveryValid = !$otpValid && $user->isTwoFaRecoveryCodeValid($otp);

        if (!$otpValid && !$recoveryValid) {
            // OTP failures count toward the account lock too (GHSA-f2fq-4rmp-9x8c).
            SignInThrottle::recordFailure($user);
            $logger->warning('API login: invalid 2FA code', $logCtx);
            throw new HttpUnauthorizedException($request, $genericError);
        }

        // isTwoFaRecoveryCodeValid() already calls $user->save() internally when
        // a recovery code is consumed — no additional save needed here.
    }

    // Full authentication complete — reset failed login counter
    $user->setFailedLogins(0);
    $user->save();

    return SlimUtils::renderJSON($response, ['apiKey' => $user->getApiKey()]);
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
    $body = json_decode($request->getBody(), true, 512);
    $userName = trim($body['userName'] ?? '');

    if (empty($userName)) {
        throw new HttpBadRequestException($request, gettext('Login Name is required'));
    }

    (new UserService())->requestPasswordReset($userName);

    return SlimUtils::renderJSON($response, ['success' => true]);
}
