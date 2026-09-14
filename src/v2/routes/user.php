<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\ImpersonationService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\NoActiveMasqueradeMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\SessionOnlyMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/user', function (RouteCollectorProxy $group): void {
    $group->get('/not-found', 'viewUserNotFound');

    // Admin masquerade (#9843). Middleware executes in reverse of the order it
    // is added (Slim 4 LIFO), so the start route runs
    // SessionOnly → NoActiveMasquerade → AdminRole → CSRF → handler: an
    // `x-api-key` caller is turned away before anything else, and a nested
    // start is answered 409 rather than the 403 the admin gate would produce
    // for the (usually non-admin) impersonated session.
    $group->post('/impersonate/exit', 'exitImpersonation')
        ->add(new CSRFMiddleware('user_impersonate'))
        ->add(new SessionOnlyMiddleware());
    $group->post('/{id:[0-9]+}/impersonate', 'startImpersonation')
        ->add(new CSRFMiddleware('user_impersonate'))
        ->add(new AdminRoleAuthMiddleware())
        ->add(new NoActiveMasqueradeMiddleware())
        ->add(new SessionOnlyMiddleware());

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

/**
 * POST /v2/user/{id}/impersonate — start an admin masquerade as user {id}.
 *
 * Reachable only from an interactive administrator session with no masquerade
 * already running (SessionOnly + NoActiveMasquerade + AdminRole + CSRF
 * middleware; the last two of those answer 409 and 403 respectively). The
 * handler itself answers 400 when {id} is the caller and 404 for an unknown
 * user. On success the session becomes {id} and the browser goes to the
 * dashboard.
 */
function startImpersonation(Request $request, Response $response, array $args): Response
{
    $admin = AuthenticationManager::getCurrentUser();
    $targetId = (int) $args['id'];

    if ($targetId === $admin->getId()) {
        return SlimUtils::renderJSON(
            $response,
            ['error' => gettext('You cannot log in as yourself.')],
            400
        );
    }

    $target = UserQuery::create()->findPk($targetId);
    if (!$target instanceof User) {
        return SlimUtils::renderJSON(
            $response,
            ['error' => gettext('No such user.')],
            404
        );
    }

    ImpersonationService::start($target);

    return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/dashboard');
}

/**
 * POST /v2/user/impersonate/exit — end the masquerade and restore the admin.
 *
 * Allowed for any authenticated session that carries an impersonation record;
 * answers 400 for a genuine login. On success the browser is sent back to the
 * record of the user that was being impersonated. If the stored administrator
 * has been deleted or demoted, the whole session is ended and the browser goes
 * to the login page instead.
 */
function exitImpersonation(Request $request, Response $response, array $args): Response
{
    if (!ImpersonationService::isActive()) {
        return SlimUtils::renderJSON(
            $response,
            ['error' => gettext('No masquerade is in progress.')],
            400
        );
    }

    $targetId = ImpersonationService::end();

    if ($targetId === null) {
        return SlimUtils::renderRedirect($response, AuthenticationManager::getSessionBeginURL());
    }

    return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/user/' . $targetId);
}

