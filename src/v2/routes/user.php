<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/user', function (RouteCollectorProxy $group): void {
    $group->get('/not-found', 'viewUserNotFound');
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
    $userId = (int) $args['id'];

    if (!$curUser->isAdmin() && $curUser->getId() !== $userId) {
        throw new HttpForbiddenException($request);
    }

    $user = UserQuery::create()->findPk($userId);

    if (empty($user)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/user/not-found?id=' . $args['id']);
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $user,
    ];

    return $renderer->render($response, 'user.php', $pageArgs);
}

