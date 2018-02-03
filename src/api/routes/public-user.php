<?php

use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/public/user', function () {
    $this->post('/{userName}/login', 'userLogin');
    $this->post('/{userName}/login/', 'userLogin');
});

function userLogin(Request $request, Response $response, array $args)
{
    $userName = $args['userName'];
    $user = UserQuery::create()->findOneByUserName("$userName");
    $body = json_decode($request->getBody());
    if (!empty($user)) {
        $password = $body->password;
        if ($user->isPasswordValid($password)) {
            return $response->withJson(["apiKey" => $user->getApiKey()]);
        } else {
            return $response->withStatus(401);
        }
    } else {
        return $response->withStatus(404);
    }
}