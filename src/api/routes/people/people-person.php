<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\Photo;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\DeleteRecordRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\HttpCache\Cache;

// This group does not load the person via middleware (to speed up the page loads)
// Photo endpoint - returns uploaded photo only (404 if no photo exists)
// Avatar info endpoint - returns JSON with initials, gravatar info for client-side rendering
$app->group('/person/{personId:[0-9]+}', function (RouteCollectorProxy $group): void {
    // Returns uploaded photo only - 404 if no uploaded photo
    $group->get('/photo', function (Request $request, Response $response, array $args): Response {
        $photo = new Photo('Person', (int)$args['personId']);
        
        if (!$photo->hasUploadedPhoto()) {
            throw new HttpNotFoundException($request, 'No uploaded photo exists for this person');
        }
        
        return SlimUtils::renderPhoto($response, $photo);
    })->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));
    
    // Returns avatar info JSON for client-side rendering
    // No cache middleware - needs to reflect immediate photo upload changes
    $group->get('/avatar', function (Request $request, Response $response, array $args): Response {
        $avatarInfo = Photo::getAvatarInfo('Person', (int)$args['personId']);
        return SlimUtils::renderJSON($response, $avatarInfo);
    });
});

$app->group('/person/{personId:[0-9]+}', function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $person = $request->getAttribute('person');

        return SlimUtils::renderStringJSON($response, $person->exportTo('JSON'));
    });

    $group->delete('', function (Request $request, Response $response, array $args): Response {
        $person = $request->getAttribute('person');
        if (AuthenticationManager::getCurrentUser()->getId() === (int) $person->getId()) {
            throw new HttpForbiddenException($request, gettext("Can't delete yourself"));
        }
        $person->delete();

        return SlimUtils::renderSuccessJSON($response);
    })->add(DeleteRecordRoleAuthMiddleware::class);

    $group->post('/role/{roleId:[0-9]+}', 'setPersonRoleAPI')->add(new EditRecordsRoleAuthMiddleware());

    $group->post('/addToCart', function (Request $request, Response $response, array $args): Response {
        Cart::addPerson($args['personId']);
        return SlimUtils::renderSuccessJSON($response);
    });

    $group->post('/photo', function (Request $request, Response $response, array $args): Response {
        $person = $request->getAttribute('person');
        $input = $request->getParsedBody();
        
        try {
            $person->setImageFromBase64($input['imgBase64']);
            // Refresh photo status and return updated info
            $person->getPhoto()->refresh();
            return SlimUtils::renderJSON($response, [
                'success' => true,
                'hasPhoto' => $person->getPhoto()->hasUploadedPhoto()
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to upload person photo'), [], 400, $e, $request);
        }
    })->add(EditRecordsRoleAuthMiddleware::class);

    $group->delete('/photo', function (Request $request, Response $response, array $args): Response {
        $person = $request->getAttribute('person');

        return SlimUtils::renderJSON($response, ['success' => $person->deletePhoto()]);
    })->add(DeleteRecordRoleAuthMiddleware::class);
})->add(PersonMiddleware::class);

function setPersonRoleAPI(Request $request, Response $response, array $args): Response
{
    $person = $request->getAttribute('person');

    $roleId = (int) $args['roleId'];
    $role = ListOptionQuery::create()->filterByOptionId($roleId)->findOne();

    if (empty($role)) {
        throw new HttpNotFoundException($request, gettext('The role could not be found.'));
    }

    if ((int) $person->getFmrId() === $roleId) {
        return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The role is already assigned.')]);
    }

    $person->setFmrId($role->getOptionId());
    if ($person->save()) {
        return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The role is successfully assigned.')]);
    } else {
        return SlimUtils::renderErrorJSON($response, gettext('The role could not be assigned.'), [], 500);
    }
}
