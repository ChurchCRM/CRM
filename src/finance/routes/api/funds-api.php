<?php

use ChurchCRM\Exceptions\DonationFundNotFoundException;
use ChurchCRM\model\ChurchCRM\DonationFund;
use ChurchCRM\Service\DonationFundService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Finance-role REST API for DonationFund CRUD.
 *
 * All routes require FinanceRoleAuth (Finance role or Admin), enforced by
 * FinanceRoleAuthMiddleware at the module level in src/finance/index.php.
 *
 * Mounted at /finance/api/funds via src/finance/index.php.
 */

/**
 * Convert a DonationFund model to a plain array for JSON output.
 *
 * Declared as a closure (not a global function) to avoid "Cannot redeclare"
 * fatal errors if this file is ever included more than once in the same process.
 */
$fundToArray = static function (DonationFund $fund): array {
    return [
        'id'          => (int) $fund->getId(),
        'name'        => $fund->getName(),
        'description' => $fund->getDescription(),
        'active'      => $fund->getActive() === 'true',
        'order'       => (int) $fund->getOrder(),
    ];
};

$app->group('/api/funds', function (RouteCollectorProxy $group) use ($fundToArray): void {

    /**
     * @OA\Post(
     *     path="/finance/api/funds",
     *     operationId="createDonationFundAdmin",
     *     summary="Create a new donation fund (Admin only)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=30, example="Building Fund"),
     *             @OA\Property(property="description", type="string", maxLength=100, example="For building projects"),
     *             @OA\Property(property="active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Newly created fund",
     *         @OA\JsonContent(
     *             @OA\Property(property="fund", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="active", type="boolean"),
     *                 @OA\Property(property="order", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error (missing or duplicate name)"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->post('', function (Request $request, Response $response) use ($fundToArray): Response {
        try {
            $input = (array) $request->getParsedBody();
            $name = (string) ($input['name'] ?? '');
            $description = (string) ($input['description'] ?? '');
            $active = array_key_exists('active', $input)
                ? filter_var($input['active'], FILTER_VALIDATE_BOOLEAN)
                : true;

            if ($name === '') {
                return SlimUtils::renderErrorJSON($response, gettext('You must enter a name'), [], 400);
            }

            $service = new DonationFundService();
            $fund = $service->createFund($name, $description, $active);

            return SlimUtils::renderJSON($response, ['fund' => $fundToArray($fund)], 201);
        } catch (\InvalidArgumentException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to create donation fund'), [], 500, $e, $request);
        }
    })->add(new InputSanitizationMiddleware(['name' => 'text', 'description' => 'text']));

    /**
     * @OA\Put(
     *     path="/finance/api/funds/{id}",
     *     operationId="updateDonationFundAdmin",
     *     summary="Update a donation fund (Admin only)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="Fund ID"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=30),
     *             @OA\Property(property="description", type="string", maxLength=100),
     *             @OA\Property(property="active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated fund"),
     *     @OA\Response(response=400, description="Validation error (blank or duplicate name)"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required"),
     *     @OA\Response(response=404, description="Fund not found")
     * )
     */
    $group->put('/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($fundToArray): Response {
        try {
            $id = (int) $args['id'];
            $input = (array) $request->getParsedBody();

            $data = [];
            if (array_key_exists('name', $input)) {
                $data['name'] = (string) $input['name'];
            }
            if (array_key_exists('description', $input)) {
                $data['description'] = (string) $input['description'];
            }
            if (array_key_exists('active', $input)) {
                $data['active'] = filter_var($input['active'], FILTER_VALIDATE_BOOLEAN);
            }

            $service = new DonationFundService();
            $fund = $service->updateFund($id, $data);

            return SlimUtils::renderJSON($response, ['fund' => $fundToArray($fund)]);
        } catch (DonationFundNotFoundException $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Donation fund not found'), [], 404);
        } catch (\InvalidArgumentException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update donation fund'), [], 500, $e, $request);
        }
    })->add(new InputSanitizationMiddleware(['name' => 'text', 'description' => 'text']));

    /**
     * @OA\Delete(
     *     path="/finance/api/funds/{id}",
     *     operationId="deleteDonationFundAdmin",
     *     summary="Delete a donation fund (Admin only)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="Fund ID"),
     *     @OA\Response(response=200, description="Deleted successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required"),
     *     @OA\Response(response=404, description="Fund not found"),
     *     @OA\Response(response=409, description="Fund is still referenced by one or more pledges")
     * )
     */
    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            (new DonationFundService())->deleteFund((int) $args['id']);
            return SlimUtils::renderSuccessJSON($response);
        } catch (DonationFundNotFoundException $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Donation fund not found'), [], 404);
        } catch (\RuntimeException $e) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Cannot delete donation fund: it is still referenced by one or more pledges.'),
                [],
                409,
                $e,
                $request
            );
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete donation fund'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Patch(
     *     path="/finance/api/funds/{id}/order",
     *     operationId="reorderDonationFundAdmin",
     *     summary="Move a donation fund up or down in display order (Admin only)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="Fund ID"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"direction"},
     *             @OA\Property(property="direction", type="string", enum={"up","down"}, example="up")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reordered successfully"),
     *     @OA\Response(response=400, description="Invalid direction"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required"),
     *     @OA\Response(response=404, description="Fund not found")
     * )
     */
    $group->patch('/{id:[0-9]+}/order', function (Request $request, Response $response, array $args): Response {
        try {
            $id = (int) $args['id'];
            $input = (array) $request->getParsedBody();
            $direction = (string) ($input['direction'] ?? '');

            if (!in_array($direction, ['up', 'down'], true)) {
                return SlimUtils::renderErrorJSON($response, gettext("Direction must be 'up' or 'down'."), [], 400);
            }

            (new DonationFundService())->reorderFund($id, $direction);
            return SlimUtils::renderSuccessJSON($response);
        } catch (DonationFundNotFoundException $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Donation fund not found'), [], 404);
        } catch (\InvalidArgumentException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to reorder donation fund'), [], 500, $e, $request);
        }
    });

});
