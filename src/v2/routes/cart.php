<?php

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;


$app->group('/cart', function () {
  $this->get('/', 'getCartView');
  $this->get('', 'getCartView');
});

function getCartView(Request $request, Response $response, array $args) {
  $renderer = new PhpRenderer('templates/cart/');

  $pageArgs = [
      'sRootPath' => SystemURLs::getRootPath(),
      'sPageTitle' => gettext('View Your Cart'),
      'PageJSVars' => []
  ];

  if (!Cart::HasPeople()) {
    return $renderer->render($response, 'cartempty.php', $pageArgs);
  } else {
    $pageArgs = array_merge($pageArgs, array(
        'sEmailLink' => Cart::getEmailLink(),
        'sPhoneLink' => Cart::getSMSLink(),
        'iNumFamilies' => Cart::CountFamilies(),
        'cartPeople' => Cart::getCartPeople()
    ));
    return $renderer->render($response, 'cartview.php', $pageArgs);
  }
}
