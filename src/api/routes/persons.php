<?php

// Person APIs
use ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

$app->group('/persons', function () {
    // search person by Name
    $this->get('/search/{query}', function ($request, $response, $args) {
        $query = $args['query'];

    	$searchLikeString = '%'.$query.'%';
    	$people = PersonQuery::create()->
			filterByFirstName($searchLikeString, Criteria::LIKE)->
			_or()->filterByLastName($searchLikeString, Criteria::LIKE)->
			_or()->filterByEmail($searchLikeString, Criteria::LIKE)->
      		limit(15)->find();
        
        $id = 1;
        
        $return = [];        
        foreach ($people as $person) {
            $values['id'] = $id++;
            $values['objid'] = $person->getId();
            $values['text'] = $person->getFullName();
            $values['uri'] = $person->getViewURI();
            
            array_push($return, $values);
        }
        
        return $response->withJson($return);    
    });

    $this->get('/{personId:[0-9]+}/photo', function ($request, $response, $args) {
        $person = PersonQuery::create()->findPk($args['personId']);
        if ($person->isPhotoLocal()) {
            return $response->write($person->getPhotoBytes())->withHeader('Content-type', $person->getPhotoContentType());
        } else if ($person->isPhotoRemote()) {
            return $response->write(file_get_contents($person->getPhotoURI()))->withHeader('Content-type', $person->getPhotoContentType());
        } else {
            return $response->withStatus(404);
        }
    });

    $this->get('/{personId:[0-9]+}/thumbnail', function ($request, $response, $args) {
        $person = PersonQuery::create()->findPk($args['personId']);
        if ($person->isPhotoLocal()) {
            return $response->write($person->getThumbnailBytes())->withHeader('Content-type', $person->getPhotoContentType());
        } else if ($person->isPhotoRemote()) {
            return $response->write(file_get_contents($person->getPhotoURI()))->withHeader('Content-type', $person->getPhotoContentType()); 
        } else {
            return $response->withStatus(404);
        }
    });

    $this->post('/{personId:[0-9]+}/photo', function ($request, $response, $args) {
        $input = (object)$request->getParsedBody();
        $person = PersonQuery::create()->findPk($args['personId']);
        $person->setImageFromBase64($input->imgBase64);
        $response->withJSON(array("status" => "success"));
    });

    $this->delete('/{personId:[0-9]+}/photo', function ($request, $response, $args) {
        $person = PersonQuery::create()->findPk($args['personId']);
        return json_encode(array("status" => $person->deletePhoto()));
    });

    $this->post('/{personId:[0-9]+}/addToCart', function ($request, $response, $args) {
        AddToPeopleCart($args['personId']);
    });

    /**
     * @var $response \Psr\Http\Message\ResponseInterface
     */
    $this->delete('/{personId:[0-9]+}', function ($request, $response, $args) {
        /**
         * @var \ChurchCRM\User $sessionUser
         */
        $sessionUser = $_SESSION['user'];
        if (!$sessionUser->isDeleteRecordsEnabled()) {
            return $response->withStatus(401);
        }
        $personId = $args['personId'];
        if ($sessionUser->getId() == $personId) {
            return $response->withStatus(403);
        }
        $person = PersonQuery::create()->findPk($personId);
        if (is_null($person)) {
            return $response->withStatus(404);
        }

        $person->delete();

        return $response->withJSON(array("status" => "success"));

    });

});
