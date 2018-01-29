<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\UserQuery;

$app->group('/public/user', function () {
    $this->get('{userName}/login', 'userLogin');
    $this->get('{userName}/login/', 'userLogin');
});

function userLogin(Request $request, Response $response, array $args)
{
    $userName = $args['userName'];
    $user = UserQuery::create()->findOneBuyUserName();
    if (!empty($user)) {
        $password = $request->getBody()->password;
        if ($user->isPassord($password)) {
            return $response->withJson(["apiKey" => $user->getApiKey()]);
        } else {
            return $response->withStatus(401);
        }
    } else {
        return $response->withStatus(404);
    }
}