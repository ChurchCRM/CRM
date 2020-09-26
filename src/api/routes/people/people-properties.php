<?php


use ChurchCRM\PropertyQuery;
use ChurchCRM\RecordProperty;
use ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\MenuOptionsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\PersonAPIMiddleware;
use ChurchCRM\Slim\Middleware\Request\PropertyAPIMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use Slim\Http\Request;
use Slim\Http\Response;


$app->group('/people/properties', function () {

    $this->get('/person', 'getAllPersonProperties');
    $this->post('/person/{personId}/{propertyId}', 'addPropertyToPerson')->add(new PersonAPIMiddleware())->add(new PropertyAPIMiddleware("p"));
    $this->delete('/person/{personId}/{propertyId}', 'removePropertyFromPerson')->add(new PersonAPIMiddleware())->add(new PropertyAPIMiddleware("p"));
    $this->get('/family', 'getAllFamilyProperties');
    //$this->post('/family/{familyId}', 'addPropertyToPerson');

})->add(new MenuOptionsRoleAuthMiddleware());


function getAllPersonProperties(Request $request, Response $response, array $args)
{
    $properties = PropertyQuery::create()
        ->filterByProClass("p")
        ->find();
    return $response->withJson($properties->toArray());
}

function addPropertyToPerson (Request $request, Response $response, array $args) {

    $person = $request->getAttribute("person");
    $personId = $person->getId();

    $property = $request->getAttribute("property");
    $propertyId = $property->getProId();

    $personProperty = RecordPropertyQuery::create()
        ->filterByRecordId($personId)
        ->filterByPropertyId($propertyId)
        ->findOne();

    $propertyValue = "";
    if (!empty($property->getProPrompt())) {
        $data = $request->getParsedBody();
        $propertyValue = empty($data['value']) ? 'N/A' : $data['value'];
        LoggerUtils::getAppLogger()->debug("final value is: " . $propertyValue);
    }

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

    return $response->withStatus(500)->withJson(['success' => false, 'msg' => gettext('The property could not be assigned.')]);
}

function removePropertyFromPerson ($request, $response, $args) {

    $person = $request->getAttribute("person");
    $personId = $person->getId();

    $property = $request->getAttribute("property");
    $propertyId = $property->getProId();

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
    } else {
        return $response->withStatus(500)->withJson(['success' => false, 'msg' => gettext('The property could not be unassigned.')]);
    }

}

function getAllFamilyProperties(Request $request, Response $response, array $args)
{
    $properties = PropertyQuery::create()
        ->filterByProClass("f")
        ->find();
    return $response->withJson($properties->toArray());
}
