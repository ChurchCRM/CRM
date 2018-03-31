<?php
/* contributor Philippe Logel */

// Person APIs
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\Middleware\PersonAPIMiddleware;
use ChurchCRM\Slim\Middleware\Role\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Role\DeleteRecordRoleAuthMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\SessionUser;


$app->group('/person/{personId:[0-9]+}', function () {
    $this->post('/role/{roleId:[0-9]+}', 'setPersonRoleAPI')->add(new EditRecordsRoleAuthMiddleware());

    $this->get('/photo', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Person", $args['personId']);
        return $res->write($photo->getPhotoBytes())->withHeader('Content-type', $photo->getPhotoContentType());

    });

    $this->post('/photo', function ($request, $response, $args) {
        $input = (object)$request->getParsedBody();
        $person = PersonQuery::create()->findPk($args['personId']);
        $person->setImageFromBase64($input->imgBase64);
        $response->withJSON(array("status" => "success"));
    });

    $this->delete('/photo', function ($request, $response, $args) {
        $person = PersonQuery::create()->findPk($args['personId']);
        return json_encode(array("status" => $person->deletePhoto()));
    });

    $this->get('/thumbnail', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Person", $args['personId']);
        return $res->write($photo->getThumbnailBytes())->withHeader('Content-type', $photo->getThumbnailContentType());
    });

    $this->post('/addToCart', function ($request, $response, $args) {
        Cart::AddPerson($args['personId']);
    });

    $this->delete('', function ($request, $response, $args) {

        $person = $request->getAttribute("person");
        if (SessionUser::getId() == $person->getId()) {
            return $response->withStatus(403, gettext("Can't delete yourself"));
        }

        $person->delete();

        return $response->withJSON(["status" => gettext("success")]);

    })->add(new DeleteRecordRoleAuthMiddleware());


})->add(new PersonAPIMiddleware());


function setPersonRoleAPI(Request $request, Response $response, array $args)
{
    $person = $request->getAttribute("person");

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