<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\FamilyAPIMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\HttpCache\Cache;
use Slim\HttpCache\CacheProvider;

$app->add(new Cache('public', MiscUtils::getPhotoCacheExpirationTimestamp()));

$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group): void {
    $group->get('/photo', function (Request $request, Response $response, array $args): Response {
        $photo = new Photo('Family', $args['familyId']);
        return SlimUtils::renderPhoto($response, $photo);
    });

    $group->post('/photo', function (Request $request, Response $response): Response {
        $input = $request->getParsedBody();

        /** @var Family $family */
        $family = $request->getAttribute('family');
        $family->setImageFromBase64($input['imgBase64']);

        return SlimUtils::renderSuccessJSON($response);
    })->add(EditRecordsRoleAuthMiddleware::class);

    $group->delete('/photo', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');

        return SlimUtils::renderJSON($response, ['status' => $family->deletePhoto()]);
    })->add(EditRecordsRoleAuthMiddleware::class);

    $group->get('/thumbnail', function (Request $request, Response $response, array $args): Response {
        $this->cache->withExpires(
            $response,
            MiscUtils::getPhotoCacheExpirationTimestamp()
        );
        $photo = new Photo('Family', $args['familyId']);

        $response
            ->withHeader('Content-type', $photo->getThumbnailContentType())
            ->getBody()
                ->write($photo->getThumbnailBytes());

        return $response;
    });

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
})->add(FamilyAPIMiddleware::class);
