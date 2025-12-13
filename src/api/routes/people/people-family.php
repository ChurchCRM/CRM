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
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\HttpCache\Cache;

// Photo and avatar routes (no FamilyMiddleware to speed up page loads)
$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group): void {
    // Returns uploaded photo only - 404 if no uploaded photo
    $group->get('/photo', function (Request $request, Response $response, array $args): Response {
        $photo = new Photo('Family', (int)$args['familyId']);
        
        if (!$photo->hasUploadedPhoto()) {
            throw new HttpNotFoundException($request, 'No uploaded photo exists for this family');
        }
        
        return SlimUtils::renderPhoto($response, $photo);
    })->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));
    
    // Returns avatar info JSON for client-side rendering
    $group->get('/avatar', function (Request $request, Response $response, array $args): Response {
        $avatarInfo = Photo::getAvatarInfo('Family', (int)$args['familyId']);
        return SlimUtils::renderJSON($response, $avatarInfo);
    })->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));
});

// Routes that require FamilyMiddleware
$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group): void {
    $group->post('/photo', function (Request $request, Response $response): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $input = $request->getParsedBody();
        
        try {
            $family->setImageFromBase64($input['imgBase64']);
            return SlimUtils::renderSuccessJSON($response);
        } catch (\Exception $e) {
            return SlimUtils::renderJSON($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    })->add(EditRecordsRoleAuthMiddleware::class);

    $group->delete('/photo', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        return SlimUtils::renderJSON($response, ['success' => $family->deletePhoto()]);
    })->add(EditRecordsRoleAuthMiddleware::class);

    $group->get('', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        return SlimUtils::renderJSON($response, $family->toArray());
    });

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

    $group->post('/verify', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        try {
            $family->sendVerifyEmail();

            return SlimUtils::renderSuccessJSON($response);
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->error($e->getMessage());

            return SlimUtils::renderJSON($response, [
                'message' => gettext('Error sending email(s)') . ' - ' . gettext('Please check logs for more information'),
                'trace' => $e->getMessage(),
            ], 500);
        }
    });

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

    $group->post('/verify/now', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $family->verify();

        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * Update the family status to activated or deactivated with :familyId and :status true/false.
     * Pass true to activate and false to deactivate.
     */
    $group->post('/activate/{status}', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $newStatus = $args['status'];

        $currentStatus = (empty($family->getDateDeactivated()) ? 'true' : 'false');

        //update only if the value is different
        if ($currentStatus !== $newStatus) {
            if ($newStatus == 'false') {
                $family->setDateDeactivated(date('YmdHis'));
            } elseif ($newStatus == 'true') {
                $family->setDateDeactivated(null);
            }
            $family->save();

            //Create a note to record the status change
            $note = new Note();
            $note->setFamId($family->getId());
            if ($newStatus == 'false') {
                $note->setText(gettext('Deactivated the Family'));
            } else {
                $note->setText(gettext('Activated the Family'));
            }
            $note->setType('edit');
            $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
            $note->save();
        }

        return SlimUtils::renderJSON($response, ['success' => true]);
    });
})->add(FamilyMiddleware::class);
