<?php

use ChurchCRM\model\ChurchCRM\MenuLink;
use ChurchCRM\model\ChurchCRM\MenuLinkQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\ORMUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system/menu', function (RouteCollectorProxy $group) {
    $group->get('', 'getMenus');
    $group->get('/', 'getMenus');
    $group->put('', 'addMenu');
    $group->put('/', 'addMenu');
    $group->delete('{linkId:[0-9]+}', 'delMenu');
    $group->delete('/{linkId:[0-9]+}', 'delMenu');
})->add(AdminRoleAuthMiddleware::class);

function getMenus(Request $request, Response $response, array $args)
{
    $links = MenuLinkQuery::create()->orderByOrder()->find();

    return $response->withJson(['menus' => $links->toArray()]);
}

function addMenu(Request $request, Response $response, array $args)
{
    $link = new MenuLink();
    $link->fromJSON($request->getBody());

    if ($link->validate()) {
        $link->save();

        return $response->withJson($link->toArray());
    }

    return $response->withStatus(400)->withJson(['error' => gettext('Validation Error'),
        'failures'                                       => ORMUtils::getValidationErrors($link->getValidationFailures())]);
}

function delMenu(Request $request, Response $response, array $args)
{
    $link = MenuLinkQuery::create()->findPk($args['linkId']);
    if (empty($link)) {
        return $response->withStatus(404, gettext('Link Not found').': '.$args['linkId']);
    }
    $link->delete();

    return $response->withStatus(200);
}
