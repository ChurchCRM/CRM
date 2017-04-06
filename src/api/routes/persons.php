<?php

// Person APIs
use ChurchCRM\PersonQuery;
use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\PersonCustomQuery;
use ChurchCRM\NoteQuery;
use ChurchCRM\UserQuery;
use ChurchCRM\PropertyQuery;
use ChurchCRM\PersonPropertyQuery;
use ChurchCRM\PersonVolunteerOpportunityQuery;

$app->group('/persons', function () {

    // search person by Name
    $this->get('/search/{query}', function ($request, $response, $args) {
        $query = $args['query'];
        $data = "[" . $this->PersonService->getPersonsJSON($this->PersonService->search($query)) . "]";
        return $response->withJson($data);
    });

    $this->get('/{personId:[0-9]+}/photo', function ($request, $response, $args) {
        $person = PersonQuery::create()->findPk($args['personId']);
        if ($person->isPhotoLocal()) {
            return $response->write($person->getPhotoBytes())->withHeader('Content-type', $person->getPhotoContentType());
        } else if ($person->isPhotoRemote()) {
            return $response->withRedirect($person->getPhotoURI());
        } else {
            return $response->withStatus(404);
        }
    });

    $this->get('/{personId:[0-9]+}/thumbnail', function ($request, $response, $args) {
        $person = PersonQuery::create()->findPk($args['personId']);
        if ($person->isPhotoLocal()) {
            return $response->write($person->getThumbnailBytes())->withHeader('Content-type', $person->getPhotoContentType());
        } else if ($person->isPhotoRemote()) {
            return $response->withRedirect($person->getThumbnailURI());
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

        $obj = PersonCustomQuery::create()->findPk($person->getId());
        if (!is_null($obj)) {
            $obj->delete();
        }

        $obj = UserQuery::create()->findPk($person->getId());
        if (!is_null($obj)) {
            $obj->delete();
        }

        NoteQuery::create()->filterByPerson($person)->deleteAll();
        PersonVolunteerOpportunityQuery::create()->filterByPersonId($person->getId())->deleteAll();
        PersonPropertyQuery::create()->filterByPerson($person)->deleteAll();

        $obj = Person2group2roleP2g2rQuery::create()->filterByPerson($person)->find();
        foreach ($obj as $group2roleP2g2r) {
            $this->GroupService->removeUserFromGroup($group2roleP2g2r->getGroupId(), $group2roleP2g2r->getPersonId());
        }

        $person->deletePhoto();
        $person->delete();

        return $response->withJSON(array("status" => "success"));

    });

});
