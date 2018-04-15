<?php

use ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/system/custom-fields', function () {
    $this->get('/person', 'getPersonFieldsByType');
    $this->get('/person/', 'getPersonFieldsByType');
})->add(new AdminRoleAuthMiddleware());


/**
 * A method that does the work to handle getting an existing person custom fields by type.
 *
 * @param \Slim\Http\Request $p_request The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 * @return \Slim\Http\Response The augmented response.
 */
function getPersonFieldsByType(Request $request, Response $response, array $p_args)
{
    $params = $request->getQueryParams();
    $typeId = $params['typeId'];

    $fields = PersonCustomMasterQuery::create()->filterByTypeId($typeId)->find();

    $keyValue = [];

    foreach ($fields as $field) {
        array_push($keyValue, ["id" => $field->getId(), "value" => $field->getName()]);
    }

    return $response->withJson($keyValue);
}
