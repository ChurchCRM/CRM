<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\SessionUser;
use Slim\Views\PhpRenderer;
use ChurchCRM\UserQuery;

$app->group('/user/current', function () {
    $this->get('/enroll2fa', 'enroll2fa');
});



function enroll2fa(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/user/');
    $curUser = SessionUser::getUser();

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $curUser,
    ];

    return $renderer->render($response, 'manage-2fa.php', $pageArgs);

}
