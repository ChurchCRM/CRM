<?php


use ChurchCRM\PersonQuery;
use ChurchCRM\PersonPropertyQuery;
use ChurchCRM\PropertyQuery;


$app->group('/properties', function() {

    $this->post('/persons/assign', function($request, $response, $args) {
        $data = $request->getParsedBody();
        $personId = empty($data['PersonId']) ? null : $data['PersonId'];
        $propertyId = empty($data['PropertyId']) ? null : $data['PropertyId'];
        $propertyValue = empty($data['PropertyValue']) ? '' : $data['PropertyValue'];

        $person = PersonQuery::create()->findPk($personId);
        $property = PropertyQuery::create()->findPk($propertyId);
        if (!$person || !$property) {
            return $response->withStatus(404);
        }
        
        $personProperty = PersonPropertyQuery::create()
            ->filterByPersonId($personId)
            ->filterByPropertyId($propertyId)
            ->findOne();

        if ($personProperty) {
            if (empty($property->getProPrompt()) || $personProperty->getPropertyValue() == $propertyValue) {
                return $response->withJson(['success' => true, 'msg' => 'assigned']);
            }

            $personProperty->setPropertyValue($propertyValue);
            if ($personProperty->save()) {
                return $response->withJson(['success' => true, 'msg' => 'assigned']);
            } else {
                return $response->withJson(['success' => false, 'msg' => 'could not assign']);
            }
        }

        $person->addProperty($property);
        $saved = $person->save();
        if ($saved) {
            if (!empty($property->getProPrompt())) {
                $personProperty = PersonPropertyQuery::create()
                    ->filterByPersonId($personId)
                    ->filterByPropertyId($propertyId)
                    ->findOne();
                $personProperty->setPropertyValue($propertyValue);
                if (!$personProperty->save()) {
                    return $response->withJson(['success' => false, 'msg' => 'could not assign']);
                }
            }

            return $response->withJson(['success' => true, 'msg' => 'assigned']);
        }

        $response->withJson(['success' => false, 'msg' => 'could not assign']);

    });
    
    
    $this->post('/persons/unassign', function($request, $response, $args) {
        $data = $request->getParsedBody();
        $personId = empty($data['PersonId']) ? null : $data['PersonId'];
        $propertyId = empty($data['PropertyId']) ? null : $data['PropertyId'];

        $personProperty = PersonPropertyQuery::create()
            ->filterByPersonId($personId)
            ->filterByPropertyId($propertyId)
            ->findOne();        
        
        if (!$personProperty) {
            return $response->withStatus(404);
        }
        
        $personProperty->delete();
        if ($personProperty->isDeleted()) {
            return $response->withJson(['success' => true, 'msg' => 'deleted']);
        } else {
           return $response->withJson(['success' => false, 'msg' => 'could not delete']); 
        }

    });

});



