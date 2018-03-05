<?php

use ChurchCRM\Slim\Middleware\AdminRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\MenuLink;
use ChurchCRM\MenuLinkQuery;


$app->group('/system/menu', function () {
    $this->get('', 'getMenus');
    $this->get('/', 'getMenus');
    $this->put('', 'addMenu');
    $this->put('/', 'addMenu');
    $this->delete('{linkId:[0-9]+}', 'delMenu');
    $this->delete('/{linkId:[0-9]+}', 'delMenu');
})->add(new AdminRoleAuthMiddleware());


function getMenus(Request $request, Response $response, array $args)
{
    $links = MenuLinkQuery::create()->find();

    return $response->withJson($links->toArray());
}


function addMenu(Request $request, Response $response, array $args)
{
    $
    return $response->withStatus(200);
}

function delMenu(Request $request, Response $response, array $args)
{
    $link = MenuLinkQuery::create()->findPk($args["linkId"]);
    if (empty($link)) {
        return $response->withStatus(404, gettext("Link Not found"). ": " . $args["linkId"]);
    }
    $link->delete();
    return $response->withStatus(200);
}
