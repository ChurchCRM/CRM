<?php

use ChurchCRM\Service\FinancialService;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Get(
 *     path="/fiscalyear",
 *     summary="Resolve fiscal year ID for a given date (Finance role required)",
 *     description="Returns the fiscal year ID and label for the supplied date, respecting the iFYMonth system setting. Falls back to the current fiscal year when no date is supplied.",
 *     tags={"Finance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="date",
 *         in="query",
 *         required=false,
 *         description="Calendar date to resolve (YYYY-MM-DD). Defaults to today.",
 *         @OA\Schema(type="string", format="date", example="2025-03-15")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Fiscal year details for the supplied date",
 *         @OA\JsonContent(
 *             @OA\Property(property="fyId",  type="integer", example=29, description="Fiscal year ID"),
 *             @OA\Property(property="label", type="string",  example="2025", description="Human-readable fiscal year label")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Finance role required")
 * )
 */
$app->get('/fiscalyear', function (Request $request, Response $response): Response {
    $params = $request->getQueryParams();
    $date   = isset($params['date']) ? (string) $params['date'] : '';

    $fyId = FiscalYearUtils::getFiscalYearIdForDate($date);

    return SlimUtils::renderJSON($response, [
        'fyId'  => $fyId,
        'label' => FinancialService::formatFiscalYear($fyId),
    ]);
})->add(FinanceRoleAuthMiddleware::class);
