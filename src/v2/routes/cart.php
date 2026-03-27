<?php

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/cart', function (RouteCollectorProxy $group): void {
    $group->get('/', 'getCartView');
    $group->get('', 'getCartView');
});

function getCartView(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/cart/');

    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Cart'),
        'sPageSubtitle' => gettext('Manage people in your cart'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Cart')],
        ]),
    ];

    if (!Cart::hasPeople()) {
        return $renderer->render($response, 'cartempty.php', $pageArgs);
    } else {
        $pageArgs = array_merge($pageArgs, [
            'sEmailLink'   => Cart::getEmailLink(),
            'iNumFamilies' => Cart::countFamilies(),
            'cartPeople'   => Cart::getCartPeople(),
        ]);

        return $renderer->render($response, 'cartview.php', $pageArgs);
    }
}
