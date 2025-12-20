<?php

use ChurchCRM\dto\Cart;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/cart', function (RouteCollectorProxy $group): void {
    $group->get('/', function (Request $request, Response $response, array $args): Response {
        // Ensure cart session exists
        if (!isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = [];
        }
        // Validate cart contents are numeric (defense in depth)
        $validCart = array_filter($_SESSION['aPeopleCart'], 'is_numeric');
        $cart = ['PeopleCart' => array_map('intval', $validCart)];
        return SlimUtils::renderJSON($response, $cart);
    });

    $group->post('/', function (Request $request, Response $response, array $args): Response {
        try {
            $cartPayload = $request->getParsedBody();
            $result = null;
            
            if (isset($cartPayload['Persons']) && is_array($cartPayload['Persons']) && count($cartPayload['Persons']) > 0) {
                // Validate all Person IDs are numeric
                foreach ($cartPayload['Persons'] as $personId) {
                    if (!is_numeric($personId)) {
                        $e = new \Exception('Invalid Person ID in array: ' . json_encode($personId));
                        return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
                    }
                }
                // Cast to integers for safety
                $validPersonIds = array_map('intval', $cartPayload['Persons']);
                $result = Cart::addPersonArray($validPersonIds);
            } elseif (isset($cartPayload['Family'])) {
                // Validate Family ID is numeric
                if (!is_numeric($cartPayload['Family'])) {
                    $e = new \Exception('Invalid Family ID: ' . json_encode($cartPayload['Family']));
                    return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
                }
                $result = Cart::addFamily((int)$cartPayload['Family']);
            } elseif (isset($cartPayload['Group'])) {
                // Validate Group ID is numeric
                if (!is_numeric($cartPayload['Group'])) {
                    $e = new \Exception('Invalid Group ID: ' . json_encode($cartPayload['Group']));
                    return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
                }
                Cart::addGroup((int)$cartPayload['Group']);
        } else {
            $e = new \Exception('Missing required field: Persons, Family, or Group');
            return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
        }
        
            // Return result with added/duplicate information if available
            if ($result !== null) {
                return SlimUtils::renderJSON($response, $result);
            }
            
            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
        }
    });

    $group->post('/emptyToGroup', function (Request $request, Response $response, array $args): Response {
        try {
            $cartPayload = $request->getParsedBody();
            
            // Validate required fields exist
            if (!isset($cartPayload['groupID']) || !isset($cartPayload['groupRoleID'])) {
                $e = new \Exception('Missing required parameters: groupID=' . (isset($cartPayload['groupID']) ? 'set' : 'missing') . ', groupRoleID=' . (isset($cartPayload['groupRoleID']) ? 'set' : 'missing'));
                return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
            }
            
            // Validate groupID is numeric
            if (!is_numeric($cartPayload['groupID'])) {
                $e = new \Exception('Invalid groupID: ' . json_encode($cartPayload['groupID']));
                return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
            }
            
            // Validate groupRoleID is numeric
            if (!is_numeric($cartPayload['groupRoleID'])) {
                $e = new \Exception('Invalid groupRoleID: ' . json_encode($cartPayload['groupRoleID']));
                return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
            }
            
            Cart::emptyToGroup((int)$cartPayload['groupID'], (int)$cartPayload['groupRoleID']);

            return SlimUtils::renderJSON($response, [
            'status' => 'success',
            'message' => gettext('records(s) successfully added to selected Group.')
        ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
        }
    });

    $group->post('/removeGroup', function (Request $request, Response $response, array $args): Response {
        try {
            $cartPayload = $request->getParsedBody();
            
            // Validate required field exists
            if (!isset($cartPayload['Group'])) {
                $e = new \Exception('Missing required parameter: Group');
                return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
            }
            
            // Validate Group is numeric
            if (!is_numeric($cartPayload['Group'])) {
                $e = new \Exception('Invalid Group ID: ' . json_encode($cartPayload['Group']));
                return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
            }
            
            Cart::removeGroup((int)$cartPayload['Group']);
            return SlimUtils::renderJSON($response, [
                'status' => 'success',
                'message' => gettext('records(s) successfully deleted from the selected Group.'),
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
        }
    });

    /**
     * delete. This will empty the cart.
     */
    $group->delete('/', function (Request $request, Response $response, array $args): Response {
        try {
            $cartPayload = $request->getParsedBody();
            $sMessage = gettext('Your cart is empty');
            if (isset($cartPayload['Persons']) && is_array($cartPayload['Persons']) && count($cartPayload['Persons']) > 0) {
                // Validate all IDs are numeric
                foreach ($cartPayload['Persons'] as $personId) {
                    if (!is_numeric($personId)) {
                        $e = new \Exception('Invalid Person ID in array: ' . json_encode($personId));
                        return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
                    }
                }
                // Cast to integers for safety
                $validPersonIds = array_map('intval', $cartPayload['Persons']);
                Cart::removePersonArray($validPersonIds);
                $sMessage = gettext('Person(s) removed from cart');
            } elseif (isset($cartPayload['Family'])) {
                // Validate Family ID is numeric
                if (!is_numeric($cartPayload['Family'])) {
                    $e = new \Exception('Invalid Family ID: ' . json_encode($cartPayload['Family']));
                    return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
                }
                Cart::removeFamily((int)$cartPayload['Family']);
                $sMessage = gettext('Family removed from cart');
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
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, $e, $request);
        }
    });
});
