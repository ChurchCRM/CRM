<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/user', function (RouteCollectorProxy $group): void {
    $group->get('/not-found', 'viewUserNotFound');
    $group->get('/{id}/changePassword', 'adminChangeUserPassword');
    $group->post('/{id}/changePassword', 'adminChangeUserPassword');
    $group->get('/{id}/', 'viewUser');
    $group->get('/{id}', 'viewUser');
});

function viewUserNotFound(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/common/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'memberType' => 'User',
        'id' => SlimUtils::getURIParamInt($request, 'id'),
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}

function viewUser(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/user/');
    $curUser = AuthenticationManager::getCurrentUser();
    $userId = $args['id'];

    if (!$curUser->isAdmin() && $curUser->getId() != $userId) {
        throw new HttpForbiddenException($request);
    }

    $user = UserQuery::create()->findPk($userId);

    if (empty($user)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/admin/user/not-found?id=' . $args['id']);
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $user,
    ];

    return $renderer->render($response, 'user.php', $pageArgs);
}

function adminChangeUserPassword(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/');
    $userId = $args['id'];
    $curUser = AuthenticationManager::getCurrentUser();

    // make sure that the currently logged in user has
    // admin permissions to change other users' passwords
    if (!$curUser->isAdmin()) {
        throw new HttpForbiddenException($request);
    }

    $user = UserQuery::create()->findPk($userId);

    if (empty($user)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/admin/user/not-found?id=' . $args['id']);
    }

    if ($user->equals($curUser)) {
        // Don't allow the current user (if admin) to set their new password
        // make the user go through the "self-service" password change procedure
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/user/current/changepassword');
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $user,
    ];

    if ($request->getMethod() === 'POST') {
        $loginRequestBody = $request->getParsedBody();

        try {
            $user->adminSetUserPassword($loginRequestBody['NewPassword1']);

            return $renderer->render($response, 'common/success-changepassword.php', $pageArgs);
        } catch (PasswordChangeException $pwChangeExc) {
            $pageArgs['s' . $pwChangeExc->AffectedPassword . 'PasswordError'] = $pwChangeExc->getMessage();
        }
    }

    return $renderer->render($response, 'admin/adminchangepassword.php', $pageArgs);
}
