<?php

use ChurchCRM\ListOptionQuery;
use ChurchCRM\PersonQuery;
use Slim\Http\Request;
use Slim\Http\Response;


$app->group('/persons', function () {
    $this->get('/roles', 'getAllRoles');
    $this->get('/roles/', 'getAllRoles');
    $this->post('/role', 'setPersonRole');
    $this->post('/role/', 'setPersonRole');
});


function getAllRoles(Request $request, Response $response, array $p_args)
{
    $roles = ListOptionQuery::create()->getFamilyRoles();
    return $response->withJson($roles->toArray());
}

function setPersonRole(Request $request, Response $response, array $p_args)
{
    if (!$_SESSION['user']->isEditRecordsEnabled()) {
        return $response->withStatus(401);
    }

    $data = $request->getParsedBody();
    $personId = empty($data['personId']) ? null : $data['personId'];
    $roleId = empty($data['roleId']) ? null : $data['roleId'];

    $person = PersonQuery::create()->findPk($personId);
    $role = ListOptionQuery::create()
        ->filterByOptionId($roleId)
        ->findOne();

    if (!$person || !$role) {
        return $response->withStatus(404, gettext('The record could not be found.'));
    }

    if ($person->getFmrId() == $roleId) {
        return $response->withJson(['success' => true, 'msg' => gettext('The role is already assigned.')]);
    }

    $person->setFmrId($role->getOptionId());
    if ($person->save()) {
        return $response->withJson(['success' => true, 'msg' => gettext('The role is successfully assigned.')]);
    } else {
        throw new LogicException(gettext('The role could not be assigned.'));
    }
}

