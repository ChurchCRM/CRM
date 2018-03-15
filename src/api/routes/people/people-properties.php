<?php


use ChurchCRM\PersonQuery;
use ChurchCRM\PropertyQuery;
use ChurchCRM\RecordProperty;
use ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\Middleware\Role\MenuOptionsRoleAuthMiddleware;

use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/persons/properties', function () {
    $this->post('/', 'assignProperties');
    $this->post('', 'assignProperties');
    $this->delete('/', 'unAssignProperties');
    $this->delete('', 'unAssignProperties');
})->add(new MenuOptionsRoleAuthMiddleware());

function assignProperties(Request $request, Response $response, array $p_args)
{
    $data = $request->getParsedBody();
    $personId = empty($data['PersonId']) ? null : $data['PersonId'];
    $propertyId = empty($data['PropertyId']) ? null : $data['PropertyId'];
    $propertyValue = empty($data['PropertyValue']) ? 'N/A' : $data['PropertyValue'];

    $person = PersonQuery::create()->findPk($personId);
    $property = PropertyQuery::create()->findPk($propertyId);
    if (!$person || !$property) {
        return $response->withStatus(404, gettext('The record could not be found.'));
    }

    $personProperty = RecordPropertyQuery::create()
        ->filterByRecordId($personId)
        ->filterByPropertyId($propertyId)
        ->findOne();

    if ($personProperty) {
        if (empty($property->getProPrompt()) || $personProperty->getPropertyValue() == $propertyValue) {
            return $response->withJson(['success' => true, 'msg' => gettext('The property is already assigned.')]);
        }

        $personProperty->setPropertyValue($propertyValue);
        if ($personProperty->save()) {
            return $response->withJson(['success' => true, 'msg' => gettext('The property is successfully assigned.')]);
        } else {
            return $response->withJson(['success' => false, 'msg' => gettext('The property could not be assigned.')]);
        }
    } else {
        $personProperty = new RecordProperty();
        $personProperty->setPropertyId($property->getProId());
        $personProperty->setRecordId($person->getId());
        $personProperty->setPropertyValue($propertyValue);
        $personProperty->save();
        return $response->withJson(['success' => true, 'msg' => gettext('The property is successfully assigned.')]);
    }

    return $response->withJson(['success' => false, 'msg' => gettext('The property could not be assigned.')]);
};


function unAssignProperties(Request $request, Response $response, array $p_args)
{
    $data = $request->getParsedBody();
    $personId = empty($data['PersonId']) ? null : $data['PersonId'];
    $propertyId = empty($data['PropertyId']) ? null : $data['PropertyId'];

    $personProperty = RecordPropertyQuery::create()
        ->filterByRecordId($personId)
        ->filterByPropertyId($propertyId)
        ->findOne();

    if ($personProperty == null) {
        return $response->withStatus(404, gettext('The record could not be found.'));
    }

    $personProperty->delete();
    if ($personProperty->isDeleted()) {
        return $response->withJson(['success' => true, 'msg' => gettext('The property is successfully unassigned.')]);
    }

    return $response->withJson(['success' => false, 'msg' => gettext('The property could not be unassigned.')]);

};
