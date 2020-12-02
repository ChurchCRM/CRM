<?php

use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/public/user', function () {
    $this->post('/login', 'userLogin');
    $this->post('/login/', 'userLogin');

});

function userLogin(Request $request, Response $response, array $args)
{
    $body = json_decode($request->getBody());
    if (!empty($body->userName)) {
        $user = UserQuery::create()->findOneByUserName($body->userName);
        if (!empty($user)) {
            $password = $body->password;
            if ($user->isPasswordValid($password)) {
                return $response->withJson(["apiKey" => $user->getApiKey()]);
            } else {
                return $response->withStatus(401, gettext('Invalid User/Password'));
            }
        }
    }
    return $response->withStatus(404);
}

