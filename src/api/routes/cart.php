<?php

use ChurchCRM\dto\Cart;
use Slim\Routing\RouteCollectorProxy;
$app->group('/cart', function (RouteCollectorProxy $group) {
    $group->get(
        '/',
        fn ($request, $response, $args) => $response->withJson(['PeopleCart' => $_SESSION['aPeopleCart']])
    );

    $group->post('/', function ($request, $response, $args) {
        $cartPayload = (object) $request->getParsedBody();
        if (isset($cartPayload->Persons) && count($cartPayload->Persons) > 0) {
            Cart::addPersonArray($cartPayload->Persons);
        } elseif (isset($cartPayload->Family)) {
            Cart::addFamily($cartPayload->Family);
        } elseif (isset($cartPayload->Group)) {
            Cart::addGroup($cartPayload->Group);
        } else {
            throw new \Exception(gettext('POST to cart requires a Persons array, FamilyID, or GroupID'), 500);
        }

        return $response->withJson(['status' => 'success']);
    });

    $group->post('/emptyToGroup', function ($request, $response, $args) {
        $cartPayload = (object) $request->getParsedBody();
        Cart::emptyToGroup($cartPayload->groupID, $cartPayload->groupRoleID);

        return $response->withJson([
            'status'  => 'success',
            'message' => gettext('records(s) successfully added to selected Group.'),
        ]);
    });

    $group->post('/removeGroup', function ($request, $response, $args) {
        $cartPayload = (object) $request->getParsedBody();
        Cart::removeGroup($cartPayload->Group);

        return $response->withJson([
            'status'  => 'success',
            'message' => gettext('records(s) successfully deleted from the selected Group.'),
        ]);
    });

    /**
     * delete. This will empty the cart.
     */
    $group->delete('/', function ($request, $response, $args) {
        $cartPayload = (object) $request->getParsedBody();
        if (isset($cartPayload->Persons) && count($cartPayload->Persons) > 0) {
            Cart::removePersonArray($cartPayload->Persons);
        } else {
            $sMessage = gettext('Your cart is empty');
            if (count($_SESSION['aPeopleCart']) > 0) {
                $_SESSION['aPeopleCart'] = [];
                $sMessage = gettext('Your cart has been successfully emptied');
            }
        }

        return $response->withJson([
            'status'  => 'success',
            'message' => $sMessage,
        ]);
    });
});
