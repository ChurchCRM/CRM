<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\Photo;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\DeleteRecordRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\PersonAPIMiddleware;
use ChurchCRM\Utils\MiscUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// This group does not load the person via middleware (to speed up the page loads)
$app->group('/person/{personId:[0-9]+}', function (RouteCollectorProxy $group) {
    $group->get('/thumbnail', function (Request $request, Response $response, array $args) {
        $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo('Person', $args['personId']);

        return $response->write($photo->getThumbnailBytes())->withHeader('Content-type', $photo->getThumbnailContentType());
    });

    $group->get('/photo', function (Request $request, Response $response, array $args) {
        $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo('Person', $args['personId']);

        return $response->write($photo->getPhotoBytes())->withHeader('Content-type', $photo->getPhotoContentType());
    });
});

$app->group('/person/{personId:[0-9]+}', function (RouteCollectorProxy $group) {
    $group->get('', function (Request $request, Response $response, array $args) {
        $person = $request->getAttribute('person');

        return $response->withHeader('Content-Type', 'application/json')->write($person->exportTo('JSON'));
    });

    $group->delete('', function (Request $request, Response $response, array $args) {
        $person = $request->getAttribute('person');
        if (AuthenticationManager::getCurrentUser()->getId() == $person->getId()) {
            return $response->withStatus(403, gettext("Can't delete yourself"));
        }
        $person->delete();

        return $response->withJson(['status' => gettext('success')]);
    })->add(DeleteRecordRoleAuthMiddleware::class);

    $group->post('/role/{roleId:[0-9]+}', 'setPersonRoleAPI')->add(new EditRecordsRoleAuthMiddleware());

    $group->post('/addToCart', function (Request $request, Response $response, array $args) {
        Cart::addPerson($args['personId']);
    });

    $group->post('/photo', function (Request $request, Response $response, array $args) {
        $person = $request->getAttribute('person');
        $input = (object) $request->getParsedBody();
        $person->setImageFromBase64($input->imgBase64);
        $response->withJson(['status' => 'success']);
    })->add(EditRecordsRoleAuthMiddleware::class);

    $group->delete('/photo', function (Request $request, Response $response, array $args) {
        $person = $request->getAttribute('person');

        return $response->withJson(['success' => $person->deletePhoto()]);
    })->add(DeleteRecordRoleAuthMiddleware::class);
})->add(PersonAPIMiddleware::class);

function setPersonRoleAPI(Request $request, Response $response, array $args)
{
    $person = $request->getAttribute('person');

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
