<?php

use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public/user', function (RouteCollectorProxy $group) {
    $group->post('/login', 'userLogin');
    $group->post('/login/', 'userLogin');
});

function userLogin(Request $request, Response $response, array $args): Response
{
    $body = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    if (!empty($body->userName)) {
        $user = UserQuery::create()->findOneByUserName($body->userName);
        if (!empty($user)) {
            $password = $body->password;
            if (!$user->isPasswordValid($password)) {
                throw new HttpUnauthorizedException($request, gettext('Invalid User/Password'));
            }

            return SlimUtils::renderJSON($response, ['apiKey' => $user->getApiKey()]);
        }
    }

    throw new HttpNotFoundException($request);
}
