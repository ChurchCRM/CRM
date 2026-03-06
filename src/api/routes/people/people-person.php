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
/**
 * @OA\Get(
 *     path="/person/{personId}/photo",
 *     operationId="getPersonPhoto",
 *     summary="Get a person's uploaded photo",
 *     description="Returns the binary photo image. Returns 404 if no photo has been uploaded (use avatar endpoint for fallback).",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\Response(response=200, description="Photo image", @OA\MediaType(mediaType="image/jpeg", @OA\Schema(type="string", format="binary"))),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="No uploaded photo for this person")
 * )
 * @OA\Get(
 *     path="/person/{personId}/avatar",
 *     operationId="getPersonAvatar",
 *     summary="Get a person's avatar info (initials, gravatar)",
 *     description="Returns JSON with avatar metadata for client-side rendering. Always returns a result even if no photo is uploaded.",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\Response(response=200, description="Avatar info",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="hasPhoto", type="boolean"),
 *             @OA\Property(property="initials", type="string", example="JS"),
 *             @OA\Property(property="gravatarUrl", type="string", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 * @OA\Post(
 *     path="/person/{personId}/photo",
 *     operationId="uploadPersonPhoto",
 *     summary="Upload a person's photo (base64 encoded)",
 *     description="Upload a base64-encoded image file for a person. Supported formats: PNG, JPEG, JPG, GIF, WEBP. Maximum size: 10MB.",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\RequestBody(required=true, description="Base64 encoded image data",
 *         @OA\JsonContent(
 *             required={"imgBase64"},
 *             @OA\Property(property="imgBase64", type="string", description="Base64-encoded image data with data URI prefix", example="data:image/jpeg;base64,/9j/4AAQSkZJRg...")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Photo uploaded successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="hasPhoto", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid image data or upload failed"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="EditRecords role required"),
 *     @OA\Response(response=404, description="Person not found")
 * )
 * @OA\Delete(
 *     path="/person/{personId}/photo",
 *     operationId="deletePersonPhoto",
 *     summary="Delete a person's uploaded photo",
 *     description="Remove the uploaded photo for a person. This operation is idempotent and will return success even if no photo exists.",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\Response(response=200, description="Photo deleted successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="DeleteRecords role required")
 * )
 */
// Photo GET and Avatar GET endpoints - no PersonMiddleware needed
$app->group('/person/{personId:[0-9]+}', function (RouteCollectorProxy $group): void {
    // Photo endpoints - returns uploaded photo only (404 if no photo exists)
    $group->get('/photo', function (Request $request, Response $response, array $args): Response {
        $personId = (int)$args['personId'];
        $photo = new Photo('Person', $personId);
        
        if (!$photo->hasUploadedPhoto()) {
            return SlimUtils::renderErrorJSON($response, 'No uploaded photo exists for this person', [], 404);
        }
        
        return SlimUtils::renderPhoto($response, $photo);
    })->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));
    
    // Avatar info endpoint - returns JSON with initials, gravatar info for client-side rendering
    // Returns fallback data even for invalid person IDs (no PersonMiddleware needed)
    $group->get('/avatar', function (Request $request, Response $response, array $args): Response {
        $avatarInfo = Photo::getAvatarInfo('Person', (int)$args['personId']);
        return SlimUtils::renderJSON($response, $avatarInfo);
    });
});

// Main person operations - POST/DELETE/role operations with PersonMiddleware
$app->group('/person/{personId:[0-9]+}', function (RouteCollectorProxy $group): void {
    // Upload photo endpoint
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
    
    // Delete photo endpoint
    $group->delete('/photo', function (Request $request, Response $response, array $args): Response {
        $person = $request->getAttribute('person');
        $deleted = $person->deletePhoto();
        return SlimUtils::renderJSON($response, ['success' => $deleted]);
    })->add(DeleteRecordRoleAuthMiddleware::class);
    
    /**
     * @OA\Get(
     *     path="/person/{personId}",
     *     operationId="getPerson",
     *     summary="Get a person's full record by ID",
     *     description="Returns the complete person object including family info, addresses, and other related details.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
     *     @OA\Response(response=200, description="Person object",
     *         @OA\JsonContent(type="object", example={"id":42,"firstName":"John","lastName":"Doe"})
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     * @OA\Delete(
     *     path="/person/{personId}",
     *     operationId="deletePerson",
     *     summary="Delete a person record",
     *     description="Permanently delete a person and all their associated records. Current user cannot delete their own account.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
     *     @OA\Response(response=200, description="Person deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Cannot delete yourself or DeleteRecords role required"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     * @OA\Post(
     *     path="/person/{personId}/addToCart",
     *     operationId="addPersonToCart",
     *     summary="Add a person to the cart",
     *     description="Add a person to the current user's cart for batch operations.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
     *     @OA\Response(response=200, description="Person added to cart",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    // Get person by ID
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $person = $request->getAttribute('person');
        return SlimUtils::renderStringJSON($response, $person->exportTo('JSON'));
    });

    // Delete person
    $group->delete('', function (Request $request, Response $response, array $args): Response {
        $person = $request->getAttribute('person');
        if (AuthenticationManager::getCurrentUser()->getId() === (int) $person->getId()) {
            throw new HttpForbiddenException($request, gettext("Can't delete yourself"));
        }
        $person->delete();

        return SlimUtils::renderSuccessJSON($response);
    })->add(DeleteRecordRoleAuthMiddleware::class);

    // Set person role
    $group->post('/role/{roleId:[0-9]+}', 'setPersonRoleAPI')->add(new EditRecordsRoleAuthMiddleware());

    // Add person to cart
    $group->post('/addToCart', function (Request $request, Response $response, array $args): Response {
        Cart::addPerson($args['personId']);
        return SlimUtils::renderSuccessJSON($response);
    });
})->add(new PersonMiddleware());

/**
 * @OA\Post(
 *     path="/person/{personId}/role/{roleId}",
 *     operationId="setPersonRole",
 *     summary="Set a person's family role",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\Parameter(name="roleId", in="path", required=true, description="Role ID from GET /persons/roles", @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Role updated",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="msg", type="string", example="The role is successfully assigned.")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="EditRecords role required"),
 *     @OA\Response(response=404, description="Person or role not found"),
 *     @OA\Response(response=500, description="Failed to save role")
 * )
 */
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
