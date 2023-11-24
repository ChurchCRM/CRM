<?php

use ChurchCRM\dto\Cart;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/cart', function (RouteCollectorProxy $group) {
    $group->get('/', function (Request $request, Response $response, array $args) {
        $cart = ['PeopleCart' => $_SESSION['aPeopleCart']];
        return SlimUtils::renderJSON($request, $cart);
    });

    $group->post('/', function (Request $request, Response $response, array $args) {
        $cartPayload = (object)$request->getParsedBody();
        if (isset($cartPayload->Persons) && count($cartPayload->Persons) > 0) {
            Cart::addPersonArray($cartPayload->Persons);
        } elseif (isset($cartPayload->Family)) {
            Cart::addFamily($cartPayload->Family);
        } elseif (isset($cartPayload->Group)) {
            Cart::addGroup($cartPayload->Group);
        } else {
            throw new Exception(gettext('POST to cart requires a Persons array, FamilyID, or GroupID'), 500);
        }
        return SlimUtils::renderSuccessJSON($response);
    });

    $group->post('/emptyToGroup', function (Request $request, Response $response, array $args) {
        $cartPayload = (object)$request->getParsedBody();
        Cart::emptyToGroup($cartPayload->groupID, $cartPayload->groupRoleID);

        return SlimUtils::renderJSON($response, [
            'status' => 'success',
            'message' => gettext('records(s) successfully added to selected Group.')
        ]);
    });

    $group->post('/removeGroup', function (Request $request, Response $response, array $args) {
        $cartPayload = $request->getParsedBody();
        Cart::removeGroup($cartPayload->Group);
        return SlimUtils::renderJSON($response, [
            'status' => 'success',
            'message' => gettext('records(s) successfully deleted from the selected Group.'),
        ]);
    });

    /**
     * delete. This will empty the cart.
     */
    $group->delete('/', function (Request $request, Response $response, array $args) {
        $cartPayload = (object)$request->getParsedBody();
        $sMessage = gettext('Your cart is empty');
        if (isset($cartPayload->Persons) && count($cartPayload->Persons) > 0) {
            Cart::removePersonArray($cartPayload->Persons);
        } else {
            if (count($_SESSION['aPeopleCart']) > 0) {
                $_SESSION['aPeopleCart'] = [];
                $sMessage = gettext('Your cart has been successfully emptied');
            }
        }

        return SlimUtils::renderJSON($response, [
            'status' => 'success',
            'message' => $sMessage,
        ]);
    });
});
