<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordProperty;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\MenuOptionsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\FamilyAPIMiddleware;
use ChurchCRM\Slim\Middleware\Request\PersonAPIMiddleware;
use ChurchCRM\Slim\Middleware\Request\PropertyAPIMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/people/properties', function (RouteCollectorProxy $group): void {
    $personPropertyAPIMiddleware = new PropertyAPIMiddleware('p');
    $personAPIMiddleware = new PersonAPIMiddleware();
    $familyPropertyAPIMiddleware = new PropertyAPIMiddleware('f');
    $familyAPIMiddleware = new FamilyAPIMiddleware();

    $group->get('/person', 'getAllPersonProperties');
    $group->get('/person/{personId}', 'getPersonProperties')->add($personAPIMiddleware);
    $group->post('/person/{personId}/{propertyId}', 'addPropertyToPerson')->add($personAPIMiddleware)->add($personPropertyAPIMiddleware);
    $group->delete('/person/{personId}/{propertyId}', 'removePropertyFromPerson')->add($personAPIMiddleware)->add($personPropertyAPIMiddleware);
    $group->get('/family', 'getAllFamilyProperties');
    $group->get('/family/{familyId}', 'getFamilyProperties')->add($familyAPIMiddleware);
    $group->post('/family/{familyId}/{propertyId}', 'addPropertyToFamily')->add($familyAPIMiddleware)->add($familyPropertyAPIMiddleware);
    $group->delete('/family/{familyId}/{propertyId}', 'removePropertyFromFamily')->add($familyAPIMiddleware)->add($familyPropertyAPIMiddleware);
})->add(MenuOptionsRoleAuthMiddleware::class);

function getAllPersonProperties(Request $request, Response $response, array $args): Response
{
    $properties = PropertyQuery::create()
        ->filterByProClass('p')
        ->find();

    return SlimUtils::renderJSON($response, $properties->toArray());
}

function addPropertyToPerson(Request $request, Response $response, array $args): Response
{
    $person = $request->getAttribute('person');

    return addProperty($request, $response, $person->getId(), $request->getAttribute('property'));
}

function removePropertyFromPerson(Request $request, Response $response, array $args): Response
{
    $person = $request->getAttribute('person');

    return removeProperty($request, $response, $person->getId(), $request->getAttribute('property'));
}

function getAllFamilyProperties(Request $request, Response $response, array $args): Response
{
    $properties = PropertyQuery::create()
        ->filterByProClass('f')
        ->find();

    return SlimUtils::renderJSON($response, $properties->toArray());
}

function getPersonProperties(Request $request, Response $response, array $args): Response
{
    $person = $request->getAttribute('person');

    return getProperties($response, 'p', $person->getId());
}

function getFamilyProperties(Request $request, Response $response, array $args): Response
{
    $family = $request->getAttribute('family');

    return getProperties($response, 'f', $family->getId());
}

function getProperties(Response $response, $type, $id): Response
{
    $properties = RecordPropertyQuery::create()
        ->filterByRecordId($id)
        ->find();

    $finalProperties = [];

    foreach ($properties as $property) {
        $rawProp = $property->getProperty();
        if ($rawProp->getProClass() == $type) {
            $tempProp = [];
            $tempProp['id'] = $property->getPropertyId();
            $tempProp['name'] = $rawProp->getProName();
            $tempProp['value'] = $property->getPropertyValue();
            if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
                $tempProp['allowEdit'] = !empty(trim($rawProp->getProPrompt()));
                $tempProp['allowDelete'] = true;
            } else {
                $tempProp['allowEdit'] = false;
                $tempProp['allowDelete'] = false;
            }
            $finalProperties[] = $tempProp;
        }
    }

    return SlimUtils::renderJSON($response, $finalProperties);
}

function addPropertyToFamily(Request $request, Response $response, array $args): Response
{
    $family = $request->getAttribute('family');

    return addProperty($request, $response, $family->getId(), $request->getAttribute('property'));
}

function removePropertyFromFamily(Request $request, Response $response, array $args): Response
{
    $family = $request->getAttribute('family');

    return removeProperty($request, $response, $family->getId(), $request->getAttribute('property'));
}

function addProperty(Request $request, Response $response, $id, $property): Response
{
    $personProperty = RecordPropertyQuery::create()
        ->filterByRecordId($id)
        ->filterByPropertyId($property->getProId())
        ->findOne();

    $propertyValue = '';
    if (!empty($property->getProPrompt())) {
        $data = $request->getParsedBody();
        $propertyValue = empty($data['value']) ? 'N/A' : $data['value'];
        LoggerUtils::getAppLogger()->debug('final value is: ' . $propertyValue);
    }

    if ($personProperty) {
        if (empty($property->getProPrompt()) || $personProperty->getPropertyValue() == $propertyValue) {
            return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The property is already assigned.')]);
        }

        $personProperty->setPropertyValue($propertyValue);
        if (!$personProperty->save()) {
            throw new \Exception(gettext('The property could not be assigned.'));
        }

        return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The property is successfully assigned.')]);
    } else {
        $personProperty = new RecordProperty();
        $personProperty->setPropertyId($property->getProId());
        $personProperty->setRecordId($id);
        $personProperty->setPropertyValue($propertyValue);
        $personProperty->save();

        return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The property is successfully assigned.')]);
    }
}

function removeProperty($request, $response, $id, $property): Response
{
    $personProperty = RecordPropertyQuery::create()
        ->filterByRecordId($id)
        ->filterByPropertyId($property->getProId())
        ->findOne();

    if ($personProperty === null) {
        throw new HttpNotFoundException($request, gettext('The record could not be found.'));
    }

    $personProperty->delete();
    if (!$personProperty->isDeleted()) {
        throw new \Exception(gettext('The property could not be unassigned.'));
    }

    return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The property is successfully unassigned.')]);
}
