<?php

use ChurchCRM\model\ChurchCRM\DonationFund;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * REST API for DonationFund CRUD — replaces legacy DonationFundEditor.php /
 * DonationFundRowOps.php workflow.
 *
 * Funds have a name, description, active enum ('true'|'false'), and an
 * ordering column. The API surfaces `active` as a JSON boolean to keep
 * clients from having to learn the quirky legacy enum.
 */

/**
 * Convert a DonationFund model to a plain array for JSON output.
 */
function donationFundToArray(DonationFund $fund): array
{
    return [
        'id'          => (int) $fund->getId(),
        'name'        => $fund->getName(),
        'description' => $fund->getDescription(),
        'active'      => $fund->getActive() === 'true',
        'order'       => (int) $fund->getOrder(),
    ];
}

$app->group('/donation-funds', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/donation-funds",
     *     summary="List all donation funds (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="activeOnly", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Array of donation funds"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $params = $request->getQueryParams();
        $activeOnly = filter_var($params['activeOnly'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = DonationFundQuery::create()->orderByOrder();
        if ($activeOnly) {
            $query->filterByActive('true');
        }

        $funds = [];
        foreach ($query->find() as $fund) {
            $funds[] = donationFundToArray($fund);
        }

        return SlimUtils::renderJSON($response, ['funds' => $funds]);
    });

    /**
     * @OA\Post(
     *     path="/donation-funds",
     *     summary="Create a new donation fund (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=30),
     *             @OA\Property(property="description", type="string", maxLength=100),
     *             @OA\Property(property="active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Newly created fund"),
     *     @OA\Response(response=400, description="Validation error (missing name or duplicate)"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->post('', function (Request $request, Response $response, array $args): Response {
        try {
            $input = (array) $request->getParsedBody();
            $name = InputUtils::sanitizeText($input['name'] ?? '');
            $description = InputUtils::sanitizeText($input['description'] ?? '');
            $active = array_key_exists('active', $input)
                ? filter_var($input['active'], FILTER_VALIDATE_BOOLEAN)
                : true;

            if ($name === '') {
                return SlimUtils::renderErrorJSON($response, gettext('You must enter a name'), [], 400);
            }

            if (DonationFundQuery::create()->findOneByName($name) !== null) {
                return SlimUtils::renderErrorJSON($response, gettext('That fund name already exists.'), [], 400);
            }

            // Append to the end of the current order
            $maxOrderFund = DonationFundQuery::create()
                ->orderByOrder('desc')
                ->findOne();
            $nextOrder = $maxOrderFund !== null ? ((int) $maxOrderFund->getOrder()) + 1 : 1;

            $fund = new DonationFund();
            $fund->setName($name);
            $fund->setDescription($description);
            $fund->setActive($active ? 'true' : 'false');
            $fund->setOrder($nextOrder);
            $fund->save();

            return SlimUtils::renderJSON($response, ['fund' => donationFundToArray($fund)], 201);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to create donation fund'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Get(
     *     path="/donation-funds/{id}",
     *     summary="Get a single donation fund (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Donation fund object"),
     *     @OA\Response(response=404, description="Fund not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $fund = DonationFundQuery::create()->findPk((int) $args['id']);
        if ($fund === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Donation fund not found'), [], 404);
        }

        return SlimUtils::renderJSON($response, ['fund' => donationFundToArray($fund)]);
    });

    /**
     * @OA\Put(
     *     path="/donation-funds/{id}",
     *     summary="Update a donation fund (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=30),
     *             @OA\Property(property="description", type="string", maxLength=100),
     *             @OA\Property(property="active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated fund"),
     *     @OA\Response(response=400, description="Validation error (blank or duplicate name)"),
     *     @OA\Response(response=404, description="Fund not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->put('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $id = (int) $args['id'];
            $fund = DonationFundQuery::create()->findPk($id);
            if ($fund === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Donation fund not found'), [], 404);
            }

            $input = (array) $request->getParsedBody();

            if (array_key_exists('name', $input)) {
                $name = InputUtils::sanitizeText($input['name']);
                if ($name === '') {
                    return SlimUtils::renderErrorJSON($response, gettext('You must enter a name'), [], 400);
                }
                // Reject duplicate rename — allow keeping own name
                $existing = DonationFundQuery::create()->findOneByName($name);
                if ($existing !== null && (int) $existing->getId() !== $id) {
                    return SlimUtils::renderErrorJSON($response, gettext('That fund name already exists.'), [], 400);
                }
                $fund->setName($name);
            }

            if (array_key_exists('description', $input)) {
                $fund->setDescription(InputUtils::sanitizeText($input['description']));
            }

            if (array_key_exists('active', $input)) {
                $fund->setActive(filter_var($input['active'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false');
            }

            $fund->save();

            return SlimUtils::renderJSON($response, ['fund' => donationFundToArray($fund)]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update donation fund'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Delete(
     *     path="/donation-funds/{id}",
     *     summary="Delete a donation fund (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Fund not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $fund = DonationFundQuery::create()->findPk((int) $args['id']);
            if ($fund === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Donation fund not found'), [], 404);
            }

            $fund->delete();

            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete donation fund'), [], 500, $e, $request);
        }
    });
})->add(FinanceRoleAuthMiddleware::class);
