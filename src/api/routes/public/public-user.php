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
