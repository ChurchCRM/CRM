<?php

use ChurchCRM\Emails\users\LockedEmail;
use ChurchCRM\Emails\users\ResetPasswordTokenEmail;
use ChurchCRM\model\ChurchCRM\Token;
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
 *             @OA\Property(property="password", type="string", format="password", example="secret")
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
    $logger = LoggerUtils::getAppLogger();
    $body = json_decode($request->getBody(), true, 512, JSON_THROW_ON_ERROR);

    // Use a generic error message to prevent username enumeration
    $genericError = gettext('Invalid login or password');

    if (empty($body['userName'])) {
        throw new HttpUnauthorizedException($request, $genericError);
    }

    $user = UserQuery::create()->findOneByUserName($body['userName']);
    if ($user === null) {
        // Return same error as wrong password to prevent username enumeration
        $logger->warning('API login attempt for non-existent user: ' . $body['userName']);
        throw new HttpUnauthorizedException($request, $genericError);
    }

    // Check account lockout before attempting password validation
    if ($user->isLocked()) {
        $logger->warning('API login attempt for locked account: ' . $user->getUserName());
        throw new HttpUnauthorizedException($request, gettext('Too many failed logins: your account has been locked. Please contact an administrator.'));
    }

    $password = $body['password'] ?? '';
    if (!$user->isPasswordValid($password)) {
        // Increment failed login counter
        $user->setFailedLogins($user->getFailedLogins() + 1);
        $user->save();

        // Send locked email if account just became locked
        if ($user->isLocked() && !empty($user->getEmail())) {
            $logger->warning('API login: too many failed attempts, account locked: ' . $user->getUserName());
            $lockedEmail = new LockedEmail($user);
            $lockedEmail->send();
        }

        $logger->warning('API login: invalid password for user: ' . $user->getUserName());
        throw new HttpUnauthorizedException($request, $genericError);
    }

    // Password is valid — reset failed login counter
    $user->setFailedLogins(0);
    $user->save();

    // Check 2FA enrollment
    if ($user->is2FactorAuthEnabled()) {
        $otp = $body['otp'] ?? null;

        if (empty($otp)) {
            // No OTP provided — tell client to prompt for it
            return SlimUtils::renderJSON($response, ['requiresOTP' => true], 202);
        }

        // Validate OTP or recovery code
        if (!$user->isTwoFACodeValid($otp) && !$user->isTwoFaRecoveryCodeValid($otp)) {
            $logger->warning('API login: invalid 2FA code for user: ' . $user->getUserName());
            throw new HttpUnauthorizedException($request, gettext('Invalid verification code'));
        }
    }

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
    $logger = LoggerUtils::getAppLogger();
    
    $body = json_decode($request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    $userName = trim($body['userName'] ?? '');
    
    if (empty($userName)) {
        throw new HttpBadRequestException($request, gettext('Login Name is required'));
    }

    $user = UserQuery::create()->findOneByUserName($userName);
    if (empty($user) || empty($user->getEmail())) {
        // Don't reveal whether user exists (security best practice)
        $logger->warning('Password reset requested for non-existent user: ' . $userName);
        return SlimUtils::renderJSON($response, ['success' => true]);
    }

    $token = new Token();
    $token->build('password', $user->getId());
    $token->save();
    
    $email = new ResetPasswordTokenEmail($user, $token->getToken());
    if (!$email->send()) {
        $logger->error('Failed to send password reset email for user ' . $user->getUserName() . ': ' . $email->getError());
        // Still return success to user (don't expose email issues)
        return SlimUtils::renderJSON($response, ['success' => true]);
    }

    $logger->info('Password reset token sent for user: ' . $user->getUserName());
    return SlimUtils::renderJSON($response, ['success' => true]);
}
