<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Api\FamilyMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\GeoUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\HttpCache\Cache;

// Photo and avatar routes (no FamilyMiddleware to speed up page loads)
$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/family/{familyId}/photo",
     *     summary="Get uploaded photo for a family (binary image)",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Binary image data"),
     *     @OA\Response(response=404, description="No uploaded photo exists for this family")
     * )
     */
    // Returns uploaded photo only - 404 if no uploaded photo
    $group->get('/photo', function (Request $request, Response $response, array $args): Response {
        $photo = new Photo('Family', (int)$args['familyId']);

        if (!$photo->hasUploadedPhoto()) {
            throw new HttpNotFoundException($request, 'No uploaded photo exists for this family');
        }

        return SlimUtils::renderPhoto($response, $photo);
    })->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));

    /**
     * @OA\Get(
     *     path="/family/{familyId}/avatar",
     *     summary="Get avatar info JSON for a family (for client-side rendering)",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Avatar info object (type, url, initials, color, etc.)")
     * )
     */
    // Returns avatar info JSON for client-side rendering
    // No cache middleware - needs to reflect immediate photo upload changes
    $group->get('/avatar', function (Request $request, Response $response, array $args): Response {
        $avatarInfo = Photo::getAvatarInfo('Family', (int)$args['familyId']);
        return SlimUtils::renderJSON($response, $avatarInfo);
    });
});

// Routes that require FamilyMiddleware
$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Post(
     *     path="/family/{familyId}/photo",
     *     summary="Upload a family photo from base64 data (EditRecords role required)",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(@OA\Property(property="imgBase64", type="string", description="Base64-encoded image data"))
     *     ),
     *     @OA\Response(response=200, description="Photo uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="hasPhoto", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Failed to upload photo"),
     *     @OA\Response(response=403, description="EditRecords role required")
     * )
     */
    $group->post('/photo', function (Request $request, Response $response): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $input = $request->getParsedBody();

        try {
            $family->setImageFromBase64($input['imgBase64']);
            // Refresh photo status and return updated info
            $family->getPhoto()->refresh();
            return SlimUtils::renderJSON($response, [
                'success' => true,
                'hasPhoto' => $family->getPhoto()->hasUploadedPhoto()
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to upload family photo'), [], 400, $e, $request);
        }
    })->add(EditRecordsRoleAuthMiddleware::class);

    /**
     * @OA\Delete(
     *     path="/family/{familyId}/photo",
     *     summary="Delete a family's uploaded photo (EditRecords role required)",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Photo deletion result",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean"))
     *     ),
     *     @OA\Response(response=403, description="EditRecords role required")
     * )
     */
    $group->delete('/photo', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        return SlimUtils::renderJSON($response, ['success' => $family->deletePhoto()]);
    })->add(EditRecordsRoleAuthMiddleware::class);

    /**
     * @OA\Get(
     *     path="/family/{familyId}",
     *     summary="Get a family object by ID",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Family object"),
     *     @OA\Response(response=404, description="Family not found")
     * )
     */
    $group->get('', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        return SlimUtils::renderJSON($response, $family->toArray());
    });

    /**
     * @OA\Get(
     *     path="/family/{familyId}/geolocation",
     *     summary="Get geolocation and driving distance from church for a family",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Latitude, longitude, and driving distance info from church address")
     * )
     */
    $group->get('/geolocation', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        $familyAddress = $family->getAddress();
        $familyLatLong = GeoUtils::getLatLong($familyAddress);
        $familyDrivingInfo = GeoUtils::drivingDistanceMatrix(
            $familyAddress,
            ChurchMetaData::getChurchAddress()
        );
        $geoLocationInfo = array_merge($familyDrivingInfo, $familyLatLong);

        return SlimUtils::renderJSON($response, $geoLocationInfo);
    });

    /**
     * @OA\Get(
     *     path="/family/{familyId}/nav",
     *     summary="Get previous and next family IDs for navigation",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Navigation IDs",
     *         @OA\JsonContent(
     *             @OA\Property(property="PreFamilyId", type="integer"),
     *             @OA\Property(property="NextFamilyId", type="integer")
     *         )
     *     )
     * )
     */
    $group->get('/nav', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        $familyNav = [];
        $familyNav['PreFamilyId'] = 0;
        $familyNav['NextFamilyId'] = 0;

        $tempFamily = FamilyQuery::create()
            ->filterById($family->getId(), Criteria::LESS_THAN)
            ->orderById(Criteria::DESC)->findOne();
        if ($tempFamily) {
            $familyNav['PreFamilyId'] = $tempFamily->getId();
        }

        $tempFamily = FamilyQuery::create()
            ->filterById($family->getId(), Criteria::GREATER_THAN)
            ->orderById()
            ->findOne();
        if ($tempFamily) {
            $familyNav['NextFamilyId'] = $tempFamily->getId();
        }

        return SlimUtils::renderJSON($response, $familyNav);
    });

    /**
     * @OA\Post(
     *     path="/family/{familyId}/verify",
     *     summary="Send a verification email to the family",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Verification email sent successfully"),
     *     @OA\Response(response=500, description="Error sending email")
     * )
     */
    $group->post('/verify', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        try {
            $family->sendVerifyEmail();

            return SlimUtils::renderSuccessJSON($response);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Error sending email(s)') , [], 500, $e, $request);
        }
    });

    /**
     * @OA\Get(
     *     path="/family/{familyId}/verify/url",
     *     summary="Generate a new family self-verify URL token",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Verification URL",
     *         @OA\JsonContent(@OA\Property(property="url", type="string", format="uri"))
     *     )
     * )
     */
    $group->get('/verify/url', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        TokenQuery::create()
            ->filterByType('verifyFamily')
            ->filterByReferenceId($family->getId())
            ->delete();
        $token = new Token();
        $token->build('verifyFamily', $family->getId());
        $token->save();
        $family->createTimeLineNote('verify-URL');

        return SlimUtils::renderJSON($response, ['url' => SystemURLs::getURL() . '/external/verify/' . $token->getToken()]);
    });

    /**
     * @OA\Post(
     *     path="/family/{familyId}/verify/now",
     *     summary="Mark a family as verified immediately",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Family marked as verified")
     * )
     */
    $group->post('/verify/now', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $family->verify();

        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Post(
     *     path="/family/{familyId}/activate/{status}",
     *     summary="Activate or deactivate a family",
     *     description="Pass status=true to activate or status=false to deactivate the family.",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="path", required=true, @OA\Schema(type="string", enum={"true","false"})),
     *     @OA\Response(response=200, description="Family activation status updated",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean"))
     *     ),
     *     @OA\Response(response=400, description="Invalid status value")
     * )
     */
    /**
     * Update the family status to activated or deactivated with :familyId and :status true/false.
     * Pass true to activate and false to deactivate.
     */
    $group->post('/activate/{status}', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        // Normalize incoming status to boolean for clarity (true = activate, false = deactivate)
        $newStatus = filter_var($args['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($newStatus === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid status'), [], 400);
        }

        $currentStatus = $family->isActive();

        // update only if the value is different
        if ($currentStatus !== $newStatus) {
            $currentUserId = AuthenticationManager::getCurrentUser()->getId();
            $currentDate = new \DateTime();
            if ($newStatus === false) {
                // Deactivating: set DateDeactivated to now
                $family->setDateDeactivated($currentDate);
            } else {
                // Activating: clear DateDeactivated
                $family->setDateDeactivated(null);
            }


            // Create a note to record the status change
            $note = new Note();
            $note->setFamId($family->getId());
            $note->setText($newStatus === false ? gettext('Marked the Family as Inactive') : gettext('Marked the Family as Active'));
            $note->setType('edit');
            $note->setEntered($currentUserId);
            $note->save();

            // Update last edited metadata
            $family->setDateLastEdited($currentDate);
            $family->setEditedBy($currentUserId);
            $family->save();
        }

        return SlimUtils::renderJSON($response, ['success' => true]);
    });
})->add(FamilyMiddleware::class);
