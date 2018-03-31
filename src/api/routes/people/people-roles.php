<?php

use ChurchCRM\ListOptionQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Middleware\Role\EditRecordsRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;


$app->group('/persons', function () {
    $this->get('/roles', 'getAllRoles');
    $this->get('/roles/', 'getAllRoles');
    $this->post('/role', 'setPersonRole')->add(new EditRecordsRoleAuthMiddleware());
    $this->post('/role/', 'setPersonRole')->add(new EditRecordsRoleAuthMiddleware());
});

function getAllRoles(Request $request, Response $response, array $p_args)
{
    $roles = ListOptionQuery::create()->getFamilyRoles();
    return $response->withJson($roles->toArray());
}

function setPersonRole(Request $request, Response $response, array $p_args)
{
    $data = $request->getParsedBody();
    $personId = empty($data['personId']) ? null : $data['personId'];
    $roleId = empty($data['roleId']) ? null : $data['roleId'];

    $person = PersonQuery::create()->findPk($personId);
    if (empty($person)) {
        return $response->withStatus(404, gettext('The person could not be found.'));
    }

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

