<?php

use ChurchCRM\model\ChurchCRM\MenuLink;
use ChurchCRM\model\ChurchCRM\MenuLinkQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
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
    $data = json_decode($request->getBody(), true);
    
    // Custom validation before ORM validation
    $errors = [];
    
    // Validate Name
    if (empty($data['Name']) || trim($data['Name']) === '') {
        $errors[] = 'Menu name is required';
    } elseif (strlen(trim($data['Name'])) < 2) {
        $errors[] = 'Menu name must be at least 2 characters';
    } elseif (strlen(trim($data['Name'])) > 50) {
        $errors[] = 'Menu name must be 50 characters or less';
    } elseif (preg_match('/<[^>]*>/', $data['Name'])) {
        $errors[] = 'Menu name cannot contain HTML tags';
    }
    
    // Validate Uri with permissive URL check
    if (empty($data['Uri']) || trim($data['Uri']) === '') {
        $errors[] = 'Link address is required';
    } elseif (!preg_match('/^https?:\/\//i', $data['Uri'])) {
        $errors[] = 'Link must start with http:// or https://';
    } elseif (!preg_match('/^https?:\/\/[^\s\/$.?#].[^\s]*$/i', $data['Uri'])) {
        $errors[] = 'Link must be a valid URL';
    } elseif (preg_match('/<[^>]*>/', $data['Uri'])) {
        $errors[] = 'Link address cannot contain HTML tags';
    }
    
    if (!empty($errors)) {
        return SlimUtils::renderJSON(
            $response,
            [
                'error' => gettext('Validation Error'),
                'failures' => $errors
            ],
            400
        );
    }
    
    $link = new MenuLink();
    $link->fromJSON($request->getBody());

    // Skip ORM validation since we already validated above
    // The ORM's strict URL validator would reject valid URLs with special chars
    $link->save();

    return SlimUtils::renderJSON($response, $link->toArray());
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
