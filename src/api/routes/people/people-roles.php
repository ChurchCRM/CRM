<?php

use ChurchCRM\ListOptionQuery;
use Slim\Http\Request;
use Slim\Http\Response;


$app->group('/persons', function () {
    $this->get('/roles', 'getAllRoles');
    $this->get('/roles/', 'getAllRoles');
});

function getAllRoles(Request $request, Response $response, array $p_args)
{
    $roles = ListOptionQuery::create()->getFamilyRoles();
    return $response->withJson($roles->toArray());
}


