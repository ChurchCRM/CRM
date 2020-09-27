<?php


use ChurchCRM\PropertyQuery;
use ChurchCRM\RecordProperty;
use ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\MenuOptionsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\PersonAPIMiddleware;
use ChurchCRM\Slim\Middleware\Request\FamilyAPIMiddleware;
use ChurchCRM\Slim\Middleware\Request\PropertyAPIMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use Slim\Http\Request;
use Slim\Http\Response;


$app->group('/people/properties', function () {

    $personPropertyAPIMiddleware = new PropertyAPIMiddleware("p");
    $personAPIMiddleware = new PersonAPIMiddleware();
    $familyPropertyAPIMiddleware = new PropertyAPIMiddleware("f");
    $familyAPIMiddleware = new FamilyAPIMiddleware();


    $this->get('/person', 'getAllPersonProperties');
    $this->post('/person/{personId}/{propertyId}', 'addPropertyToPerson')->add($personAPIMiddleware)->add($personPropertyAPIMiddleware);
    $this->delete('/person/{personId}/{propertyId}', 'removePropertyFromPerson')->add($personAPIMiddleware)->add($personPropertyAPIMiddleware);
    $this->get('/family', 'getAllFamilyProperties');
    $this->post('/family/{familyId}/{propertyId}', 'addPropertyToFamily')->add($familyAPIMiddleware)->add($familyPropertyAPIMiddleware);
    $this->delete('/family/{familyId}/{propertyId}', 'removePropertyFromFamily')->add($familyAPIMiddleware)->add($familyPropertyAPIMiddleware);


})->add(new MenuOptionsRoleAuthMiddleware());


function getAllPersonProperties(Request $request, Response $response, array $args)
{
    $properties = PropertyQuery::create()
        ->filterByProClass("p")
        ->find();
    return $response->withJson($properties->toArray());
}

function addPropertyToPerson (Request $request, Response $response, array $args)
{
    $person = $request->getAttribute("person");
    return addProperty($response, $request, $person->getId(), $request->getAttribute("property"));
}



function removePropertyFromPerson ($request, $response, $args)
{
    $person = $request->getAttribute("person");
    return removeProperty($response, $person->getId(), $request->getAttribute("property"));
}


function getAllFamilyProperties(Request $request, Response $response, array $args)
{
    $properties = PropertyQuery::create()
        ->filterByProClass("f")
        ->find();
    return $response->withJson($properties->toArray());
}

function addPropertyToFamily (Request $request, Response $response, array $args) {
    $family = $request->getAttribute("family");
    return addProperty($request, $response, $family->getId(), $request->getAttribute("property"));
}

function removePropertyFromFamily ($request, $response, $args)
{
    $family = $request->getAttribute("family");
    return removeProperty($response, $family->getId(), $request->getAttribute("property"));
}

function addProperty(Request $request, Response $response, $id, $property) {

    $personProperty = RecordPropertyQuery::create()
        ->filterByRecordId($id)
        ->filterByPropertyId($property->getProId())
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
            return $response->withStatus(500, gettext('The property could not be assigned.'));
        }
    } else {
        $personProperty = new RecordProperty();
        $personProperty->setPropertyId($property->getProId());
        $personProperty->setRecordId($id);
        $personProperty->setPropertyValue($propertyValue);
        $personProperty->save();
        return $response->withJson(['success' => true, 'msg' => gettext('The property is successfully assigned.')]);
    }

    return $response->withStatus(500, gettext('The property could not be assigned.'));
}

function removeProperty($response, $id, $property) {

    $personProperty = RecordPropertyQuery::create()
        ->filterByRecordId($id)
        ->filterByPropertyId($property->getProId())
        ->findOne();

    if ($personProperty == null) {
        return $response->withStatus(404, gettext('The record could not be found.'));
    }

    $personProperty->delete();
    if ($personProperty->isDeleted()) {
        return $response->withJson(['success' => true, 'msg' => gettext('The property is successfully unassigned.')]);
    } else {
        return $response->withStatus(500, gettext('The property could not be unassigned.'));
    }
}
