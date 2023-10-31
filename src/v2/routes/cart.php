<?php

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->group('/cart', function () use ($app) {
    $app->get('/', 'getCartView');
    $app->get('', 'getCartView');
});

function getCartView(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/cart/');

    $pageArgs = [
      'sRootPath' => SystemURLs::getRootPath(),
      'sPageTitle' => gettext('View Your Cart'),
      'PageJSVars' => []
    ];

    if (!Cart::hasPeople()) {
        return $renderer->render($response, 'cartempty.php', $pageArgs);
    } else {
        $pageArgs = array_merge($pageArgs, [
        'sEmailLink' => Cart::getEmailLink(),
        'sPhoneLink' => Cart::getSMSLink(),
        'iNumFamilies' => Cart::countFamilies(),
        'cartPeople' => Cart::getCartPeople()
        ]);
        return $renderer->render($response, 'cartview.php', $pageArgs);
    }
}
