<?php

use ChurchCRM\model\ChurchCRM\PredefinedReportsQuery;
use ChurchCRM\model\ChurchCRM\QueryParameterOptionsQuery;
use ChurchCRM\model\ChurchCRM\QueryParametersQuery;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/locale', function (RouteCollectorProxy $group) {
    $group->get('/database/terms', 'getDBTerms');
})->add(AdminRoleAuthMiddleware::class);

/**
 * A method that gets locale terms from the db for po generation.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 */
function getDBTerms(Request $request, Response $response, array $p_args)
{
    $terms = [];

    $dbTerms = UserConfigQuery::create()->select(['ucfg_tooltip'])->distinct()->find();
    foreach ($dbTerms as $term) {
        array_push($terms, $term);
    }

    $dbTerms = QueryParameterOptionsQuery::create()->select(['qpo_Display'])->distinct()->find();
    foreach ($dbTerms as $term) {
        array_push($terms, $term);
    }

    $dbTerms = PredefinedReportsQuery::create()->select(['qry_Name', 'qry_Description'])->distinct()->find();
    foreach ($dbTerms as $term) {
        array_push($terms, $term['qry_Name']);
        array_push($terms, $term['qry_Description']);
    }

    $dbTerms = QueryParametersQuery::create()->select(['qrp_Name', 'qrp_Description'])->distinct()->find();
    foreach ($dbTerms as $term) {
        array_push($terms, $term['qrp_Name']);
        array_push($terms, $term['qrp_Description']);
    }

    return $response->withJson(['terms' => $terms]);
}
