<?php

use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system/custom-fields', function (RouteCollectorProxy $group): void {
    $group->get('/person', 'getPersonFieldsByType');
    $group->get('/person/', 'getPersonFieldsByType');
})->add(AdminRoleAuthMiddleware::class);

function getPersonFieldsByType(Request $request, Response $response, array $args): Response
{
    $params = $request->getQueryParams();
    $typeId = $params['typeId'];

    $fields = PersonCustomMasterQuery::create()->filterByTypeId($typeId)->find();

    $keyValue = [];

    foreach ($fields as $field) {
        $keyValue[] = ['id' => $field->getId(), 'value' => $field->getName()];
    }

    return SlimUtils::renderJSON($response, $keyValue);
}
