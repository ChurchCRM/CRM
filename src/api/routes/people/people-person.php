<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\Photo;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\DeleteRecordRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\PersonAPIMiddleware;
use ChurchCRM\Utils\MiscUtils;
use Slim\Http\Request;
use Slim\Http\Response;

// This group does not load the person via middleware (to speed up the page loads)
$app->group('/person/{personId:[0-9]+}', function () {

    $this->get('/thumbnail', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Person", $args['personId']);
        return $res->write($photo->getThumbnailBytes())->withHeader('Content-type', $photo->getThumbnailContentType());
    });

    $this->get('/photo', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Person", $args['personId']);
        return $res->write($photo->getPhotoBytes())->withHeader('Content-type', $photo->getPhotoContentType());
    });
});

$app->group('/person/{personId:[0-9]+}', function () {

    $this->get('', function ($request, $response, $args) {
        $person = $request->getAttribute("person");
        return $response->withHeader('Content-Type', 'application/json')->write($person->exportTo('JSON'));
    });

    $this->delete('', function ($request, $response, $args) {
        $person = $request->getAttribute("person");
        if (AuthenticationManager::GetCurrentUser()->getId() == $person->getId()) {
            return $response->withStatus(403, gettext("Can't delete yourself"));
        }
        $person->delete();
        return $response->withJson(["status" => gettext("success")]);
    })->add(new DeleteRecordRoleAuthMiddleware());

    $this->post('/role/{roleId:[0-9]+}', 'setPersonRoleAPI')->add(new EditRecordsRoleAuthMiddleware());

    $this->post('/addToCart', function ($request, $response, $args) {
        Cart::AddPerson($args['personId']);
    });

    $this->post('/photo', function ($request, $response, $args) {
        $person = $request->getAttribute("person");
        $input = (object)$request->getParsedBody();
        $person->setImageFromBase64($input->imgBase64);
        $response->withJson(array("status" => "success"));
    })->add(new EditRecordsRoleAuthMiddleware());

    $this->delete('/photo', function ($request, $response, $args) {
        $person = $request->getAttribute("person");
        return $response->withJson(['success' => $person->deletePhoto()]);
    })->add(new DeleteRecordRoleAuthMiddleware());

})->add(new PersonAPIMiddleware());


function setPersonRoleAPI(Request $request, Response $response, array $args)
{
    $person = $request->getAttribute("person");

    $roleId = $args['roleId'];
    $role = ListOptionQuery::create()->filterByOptionId($roleId)->findOne();

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
