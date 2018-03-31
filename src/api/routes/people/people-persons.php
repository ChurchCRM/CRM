<?php
/* contributor Philippe Logel */

// Person APIs
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\dto\Photo;
use ChurchCRM\Person;
use ChurchCRM\PersonQuery;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Slim\Middleware\Role\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\PersonAPIMiddleware;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/persons', function () {
    // search person by Name
    $this->get('/search/{query}', function ($request, $response, $args) {
        $query = $args['query'];

        $searchLikeString = '%' . $query . '%';
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

    $this->get('/numbers', function ($request, $response, $args) {
        return $response->withJson(MenuEventsCount::getNumberBirthDates());
    });

    $this->get('/{personId:[0-9]+}/photo', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Person", $args['personId']);
        return $res->write($photo->getPhotoBytes())->withHeader('Content-type', $photo->getPhotoContentType());

    });

    $this->get('/{personId:[0-9]+}/thumbnail', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Person", $args['personId']);
        return $res->write($photo->getThumbnailBytes())->withHeader('Content-type', $photo->getThumbnailContentType());
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
        Cart::AddPerson($args['personId']);
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
            return $response->withStatus(403);
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

    $this->get('/self-register', function ($request, $response, $args) {
        $people = PersonQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();
        return $response->withJSON(['people' => $people->toArray()]);
    });

});

$app->group('/person/{personId:[0-9]+}', function () {
    $this->post('/role/{roleId:[0-9]+}', 'setPersonRoleAPI')->add(new EditRecordsRoleAuthMiddleware());
})->add(new PersonAPIMiddleware());


function setPersonRoleAPI(Request $request, Response $response, array $args)
{
    $person =  $request->getAttribute("person");

    $roleId = $args['roleId'];
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