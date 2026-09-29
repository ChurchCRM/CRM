<?php

use ChurchCRM\model\ChurchCRM\DonationFund;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Public (Finance-role) read-only REST API for DonationFund.
 *
 * Admin CRUD (POST / PUT / DELETE / PATCH order) has been moved to
 * /finance/api/funds (AdminRoleAuth) — see src/finance/routes/api/funds-api.php.
 *
 * These endpoints are used by forms that need a fund dropdown (e.g. pledge
 * editor, payment entry) and are accessible to any finance-enabled user.
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
     *     @OA\Parameter(name="activeOnly", in="query", required=false, @OA\Schema(type="boolean"),
     *         description="When true, return only active funds"),
     *     @OA\Response(response=200, description="Array of donation funds",
     *         @OA\JsonContent(
     *             @OA\Property(property="funds", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="active", type="boolean"),
     *                     @OA\Property(property="order", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Finance role required")
     * )
     */
    $group->get('', function (Request $request, Response $response): Response {
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
     * @OA\Get(
     *     path="/donation-funds/{id}",
     *     summary="Get a single donation fund (Finance role required)",
     *     tags={"Finance"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"),
     *         description="Fund ID"),
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
})->add(FinanceRoleAuthMiddleware::class);
