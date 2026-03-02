<?php

use ChurchCRM\Emails\users\ResetPasswordTokenEmail;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
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
 *     @OA\Response(response=404, description="User not found")
 * )
 */
function userLogin(Request $request, Response $response, array $args): Response
{
    $body = json_decode($request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    if (empty($body['userName'])) {
        throw new HttpNotFoundException($request);
    }

    $user = UserQuery::create()->findOneByUserName($body['userName']);
    if (empty($user)) {
        throw new HttpNotFoundException($request);
    }

    $password = $body['password'];
    if (!$user->isPasswordValid($password)) {
        throw new HttpUnauthorizedException($request, gettext('Invalid User/Password'));
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
        $logger->info('Password reset requested for non-existent user: ' . $userName);
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
