<?php

use ChurchCRM\model\ChurchCRM\MenuLink;
use ChurchCRM\model\ChurchCRM\MenuLinkQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\ORMUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system/menu', function (RouteCollectorProxy $group): void {
    $group->get('', 'getMenus');
    $group->get('/', 'getMenus');
    $group->put('', 'addMenu');
    $group->put('/', 'addMenu');
    $group->delete('{linkId:[0-9]+}', 'delMenu');
    $group->delete('/{linkId:[0-9]+}', 'delMenu');
})->add(AdminRoleAuthMiddleware::class);

function getMenus(Request $request, Response $response, array $args): Response
{
    $links = MenuLinkQuery::create()->orderByOrder()->find();

    return SlimUtils::renderJSON($response, ['menus' => $links->toArray()]);
}

function addMenu(Request $request, Response $response, array $args): Response
{
    $link = new MenuLink();
    $link->fromJSON($request->getBody());

    if ($link->validate()) {
        $link->save();

        return SlimUtils::renderJSON($response, $link->toArray());
    }

    return SlimUtils::renderJSON(
        $response,
        [
            'error' => gettext('Validation Error'),
            'failures' => ORMUtils::getValidationErrors($link->getValidationFailures())
        ],
        400
    );
}

function delMenu(Request $request, Response $response, array $args): Response
{
    $link = MenuLinkQuery::create()->findPk($args['linkId']);
    if (empty($link)) {
        throw new HttpNotFoundException($request, gettext('Link Not found') . ': ' . $args['linkId']);
    }
    $link->delete();

    return SlimUtils::renderSuccessJSON($response);
}
