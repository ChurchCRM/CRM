<?php

use ChurchCRM\dto\Cart;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/cart', function (RouteCollectorProxy $group) {
    $group->get('/', function (Request $request, Response $response, array $args) {
        $cart = ['PeopleCart' => $_SESSION['aPeopleCart']];
        $response->getBody()->write(json_encode($cart));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/', function (Request $request, Response $response, array $args) {
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
        $response->getBody()->write(json_encode(['status' => 'success']));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/emptyToGroup', function (Request $request, Response $response, array $args) {
        $cartPayload = (object) $request->getParsedBody();
        Cart::emptyToGroup($cartPayload->groupID, $cartPayload->groupRoleID);

        return $response->withJson([
            'status'  => 'success',
            'message' => gettext('records(s) successfully added to selected Group.'),
        ]);
    });

    $group->post('/removeGroup', function (Request $request, Response $response, array $args) {
        $cartPayload = $request->getParsedBody();
        Cart::removeGroup($cartPayload->Group);
        $response->getBody()->write(json_encode([
            'status'  => 'success',
            'message' => gettext('records(s) successfully deleted from the selected Group.'),
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    /**
     * delete. This will empty the cart.
     */
    $group->delete('/', function (Request $request, Response $response, array $args) {
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
