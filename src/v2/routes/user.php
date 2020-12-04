<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\UserQuery;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->group('/user', function () {
    $this->get('/not-found', 'viewUserNotFound');
    $this->get('/{id}', 'viewUser');
    $this->get('/{id}/', 'viewUser');
    $this->get('/{id}/changePassword', 'adminChangeUserPassword');
    $this->post('/{id}/changePassword', 'adminChangeUserPassword');
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
    $renderer = new PhpRenderer('templates/user/');
    $curUser = AuthenticationManager::GetCurrentUser();
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

function adminChangeUserPassword(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');
    $userId = $args["id"];
    $curUser = AuthenticationManager::GetCurrentUser();

    // make sure that the currently logged in user has
    // admin permissions to change other users' passwords
    if (!$curUser->isAdmin()) {
        return $response->withStatus(403);
    }

    $user = UserQuery::create()->findPk($userId);

    if (empty($user)) {
        return $response->withRedirect(SystemURLs::getRootPath() . "/v2/admin/user/not-found?id=".$args["id"]);
    }

    if ($user->equals($curUser)) {
        // Don't allow the current user (if admin) to set their new password
        // make the user go through the "self-service" password change procedure
        return $response->withRedirect(SystemURLs::getRootPath() . "/v2/user/current/changepassword");
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $user,
    ];

    if ($request->getMethod() == "POST") {
        $loginRequestBody = (object)$request->getParsedBody();
        try {
            $user->adminSetUserPassword($loginRequestBody->NewPassword1);
            return $renderer->render($response,"common/success-changepassword.php",$pageArgs);
        }
        catch (PasswordChangeException $pwChangeExc) {
            $pageArgs['s'.$pwChangeExc->AffectedPassword.'PasswordError'] =  $pwChangeExc->getMessage();
        }
    }

    return $renderer->render($response, 'admin/adminchangepassword.php', $pageArgs);

}
