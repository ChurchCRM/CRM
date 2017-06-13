<?php


use ChurchCRM\PersonQuery;
use ChurchCRM\PersonPropertyQuery;
use ChurchCRM\PropertyQuery;


$app->group('/properties', function() {

    $this->post('/persons/assign', function($request, $response, $args) {
        if (!$_SESSION['user']->isAdmin()) {
            return $response->withStatus(401);
        }
 
        $data = $request->getParsedBody();
        $personId = empty($data['PersonId']) ? null : $data['PersonId'];
        $propertyId = empty($data['PropertyId']) ? null : $data['PropertyId'];
        $propertyValue = empty($data['PropertyValue']) ? '' : $data['PropertyValue'];

        $person = PersonQuery::create()->findPk($personId);
        $property = PropertyQuery::create()->findPk($propertyId);
        if (!$person || !$property) {
            return $response->withStatus(404, gettext('The record could not be found.'));
        }
        
        $personProperty = PersonPropertyQuery::create()
            ->filterByPersonId($personId)
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
                    return $response->withJson(['success' => false, 'msg' => gettext('The property could not be assigned.')]);
                }
            }

            return $response->withJson(['success' => true, 'msg' => gettext('The property is successfully assigned.')]);
        }

        $response->withJson(['success' => false, 'msg' => gettext('The property could not be assigned.')]);

    });
    
    
    $this->delete('/persons/unassign', function($request, $response, $args) {
        if (!$_SESSION['user']->isAdmin()) {
            return $response->withStatus(401);
        }
        
        $data = $request->getParsedBody();
        $personId = empty($data['PersonId']) ? null : $data['PersonId'];
        $propertyId = empty($data['PropertyId']) ? null : $data['PropertyId'];

        $personProperty = PersonPropertyQuery::create()
            ->filterByPersonId($personId)
            ->filterByPropertyId($propertyId)
            ->findOne();        
        
        if ($personProperty == null) {
            return $response->withStatus(404, gettext('The record could not be found.'));
        }
        
        $personProperty->delete();
        if ($personProperty->isDeleted()) {
            return $response->withJson(['success' => true, 'msg' => gettext('The property is successfully unassigned.')]);
        } else {
           return $response->withJson(['success' => false, 'msg' => gettext('The property could not be unassigned.')]); 
        }

    });

});



