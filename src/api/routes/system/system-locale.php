<?php

use ChurchCRM\model\ChurchCRM\PredefinedReportsQuery;
use ChurchCRM\model\ChurchCRM\QueryParameterOptionsQuery;
use ChurchCRM\model\ChurchCRM\QueryParametersQuery;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/locale', function (RouteCollectorProxy $group): void {
    $group->get('/database/terms', 'getDBTerms');
})->add(AdminRoleAuthMiddleware::class);

/**
 * @OA\Get(
 *     path="/locale/database/terms",
 *     summary="Get all translatable terms stored in the database (Admin role required)",
 *     description="Returns distinct tooltip strings, query parameter options, report names/descriptions, and query parameter names/descriptions for i18n extraction.",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Array of translatable term strings",
 *         @OA\JsonContent(@OA\Property(property="terms", type="array", @OA\Items(type="string")))
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getDBTerms(Request $request, Response $response, array $args): Response
{
    $terms = [];

    $dbTerms = UserConfigQuery::create()->select(['ucfg_tooltip'])->distinct()->find();
    foreach ($dbTerms as $term) {
        $terms[] = $term;
    }

    $dbTerms = QueryParameterOptionsQuery::create()->select(['qpo_Display'])->distinct()->find();
    foreach ($dbTerms as $term) {
        $terms[] = $term;
    }

    $dbTerms = PredefinedReportsQuery::create()->select(['qry_Name', 'qry_Description'])->distinct()->find();
    foreach ($dbTerms as $term) {
        $terms[] = $term['qry_Name'];
        $terms[] = $term['qry_Description'];
    }

    $dbTerms = QueryParametersQuery::create()->select(['qrp_Name', 'qrp_Description'])->distinct()->find();
    foreach ($dbTerms as $term) {
        $terms[] = $term['qrp_Name'];
        $terms[] = $term['qrp_Description'];
    }

    return SlimUtils::renderJSON($response, ['terms' => $terms]);
}
