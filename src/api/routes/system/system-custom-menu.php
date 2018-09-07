<?php

use ChurchCRM\MenuLink;
use ChurchCRM\MenuLinkQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\ORMUtils;
use Slim\Http\Request;
use Slim\Http\Response;


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
    $links = MenuLinkQuery::create()->orderByOrder()->find();

    return $response->withJson(["menus" => $links->toArray()]);
}


function addMenu(Request $request, Response $response, array $args)
{
    $link = new MenuLink();
    $link->fromJSON($request->getBody());

    if ($link->validate()) {
        $link->save();
        return $response->withJson($link->toArray());
    }
    return $response->withStatus(400)->withJson(["error" => gettext("Validation Error"),
        "failures" => ORMUtils::getValidationErrors($link->getValidationFailures())]);
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
