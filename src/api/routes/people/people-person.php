<?php

// Person APIs
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\Middleware\PersonAPIMiddleware;
use ChurchCRM\Slim\Middleware\Role\EditRecordsRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;


$app->group('/person/{personId:[0-9]+}', function () {
    $this->post('/role/{roleId:[0-9]+}', 'setPersonRoleAPI')->add(new EditRecordsRoleAuthMiddleware());
})->add(new PersonAPIMiddleware());


function setPersonRoleAPI(Request $request, Response $response, array $args)
{
    $person = $request->getAttribute("person");

    $roleId = $args['roleId'];
    $role = ListOptionQuery::create()
        ->filterByOptionId($roleId)
        ->findOne();

    if (empty($role)) {
        return $response->withStatus(404, gettext('The role could not be found.'));
    }

    if ($person->getFmrId() == $roleId) {
        return $response->withJson(['success' => true, 'msg' => gettext('The role is already assigned.')]);
    }

    $person->setFmrId($role->getOptionId());
    if ($person->save()) {
        return $response->withJson(['success' => true, 'msg' => gettext('The role is successfully assigned.')]);
    } else {
        return $response->withStatus(500, gettext('The role could not be assigned.'));
    }
}