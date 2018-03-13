<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\UserQuery;

$app->group('/user', function () {
    $this->get('/not-found', 'viewUserNotFound');
    $this->get('/{id}', 'viewUser');
    $this->get('/{id}/', 'viewUser');
});

function viewUserNotFound(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/common/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'memberType' => "User",
        'id' => $request->getParam("id")
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}


function viewUser(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/admin/');
    $curUser = $_SESSION['user'];
    $userId = $args["id"];

    if (!$curUser->isAdmin() && $curUser->getId() != $userId) {
        return $response->withStatus(403);
    }

    $user = UserQuery::create()->findPk($userId);

    if (empty($user)) {
        return $response->withRedirect(SystemURLs::getRootPath() . "/v2/admin/user/not-found?id=".$args["id"]);
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $user,
    ];

    return $renderer->render($response, 'user.php', $pageArgs);

}

