<?php

use ChurchCRM\PredefinedReportsQuery;
use ChurchCRM\QueryParameterOptionsQuery;
use ChurchCRM\QueryParametersQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\UserConfigQuery;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/locale', function () use ($app) {
    $app->get('/database/terms', 'getDBTerms');
})->add(new AdminRoleAuthMiddleware());

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
