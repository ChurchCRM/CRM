<?php

use ChurchCRM\dto\Cart;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/cart', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/cart/",
     *     summary="Get the current session people cart contents",
     *     tags={"Cart"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Array of person IDs currently in the cart",
     *         @OA\JsonContent(@OA\Property(property="PeopleCart", type="array", @OA\Items(type="integer")))
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/cart/",
     *     summary="Add persons, a family, or a group to the session cart",
     *     tags={"Cart"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="Persons", type="array", @OA\Items(type="integer"), description="Array of person IDs to add"),
     *             @OA\Property(property="Family", type="integer", description="Family ID to add all members from"),
     *             @OA\Property(property="Group", type="integer", description="Group ID to add all members from")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Persons, family, or group added to cart"),
     *     @OA\Response(response=400, description="Invalid or missing request data")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/cart/emptyToGroup",
     *     summary="Move all persons in the cart into a group with a specified role",
     *     tags={"Cart"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"groupID","groupRoleID"},
     *             @OA\Property(property="groupID", type="integer"),
     *             @OA\Property(property="groupRoleID", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cart persons added to the group"),
     *     @OA\Response(response=400, description="Invalid or missing groupID/groupRoleID")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/cart/removeGroup",
     *     summary="Remove all members of a group from the session cart",
     *     tags={"Cart"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"Group"},
     *             @OA\Property(property="Group", type="integer", description="Group ID whose members should be removed from the cart")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Group members removed from cart"),
     *     @OA\Response(response=400, description="Invalid or missing Group ID")
     * )
     */
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
     * @OA\Delete(
     *     path="/cart/",
     *     summary="Remove persons or a family from the cart, or empty the entire cart",
     *     tags={"Cart"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="Persons", type="array", @OA\Items(type="integer"), description="Person IDs to remove"),
     *             @OA\Property(property="Family", type="integer", description="Family ID — removes all family members from cart"),
     *             description="Omit body to empty the entire cart"
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cart updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request data")
     * )
     */
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
