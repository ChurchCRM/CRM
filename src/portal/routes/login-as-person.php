<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\LoginAsPerson;
use Laminas\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

// Stop (member sign-in design §6.5): back to the administrator's staff session, on
// the person's page. It works after Allow Login as Person is turned off, and it is
// neither refused nor recorded as a change.
$group->post('/login-as-person/stop', function (Request $request, Response $response): Response {
    if (!LoginAsPerson::isActive()) {
        throw new HttpNotFoundException($request);
    }

    $personId = LoginAsPerson::stop();
    $location = $personId === null
        ? AuthenticationManager::getStaffSignInURL()
        : SystemURLs::getRootPath() . '/people/view/' . $personId;

    return (new Psr7Response())->withStatus(303)->withHeader('Location', $location);
});
