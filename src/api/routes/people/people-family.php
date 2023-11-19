<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\FamilyAPIMiddleware;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Routing\RouteCollectorProxy;

$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group) {
    $group->get('/photo', function ($request, $response, $args) {
        $this->cache->withExpires(
            $response,
            MiscUtils::getPhotoCacheExpirationTimestamp()
        );
        $photo = new Photo('Family', $args['familyId']);

        return $response
            ->write($photo->getPhotoBytes())
            ->withHeader('Content-type', $photo->getPhotoContentType());
    });

    $group->post('/photo', function ($request, $response, $args) {
        $input = (object) $request->getParsedBody();
        $family = $request->getAttribute('family');
        $family->setImageFromBase64($input->imgBase64);

        return $response->withStatus(200);
    })->add(EditRecordsRoleAuthMiddleware::class);

    $group->delete('/photo', function ($request, $response, $args) {
        $family = $request->getAttribute('family');

        return $response->withJson(['status' => $family->deletePhoto()]);
    })->add(EditRecordsRoleAuthMiddleware::class);

    $group->get('/thumbnail', function ($request, $response, $args) {
        $this->cache->withExpires(
            $response,
            MiscUtils::getPhotoCacheExpirationTimestamp()
        );
        $photo = new Photo('Family', $args['familyId']);

        return $response
            ->write($photo->getThumbnailBytes())
            ->withHeader('Content-type', $photo->getThumbnailContentType());
    });

    $group->get('', function ($request, $response, $args) {
        $family = $request->getAttribute('family');

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->write($family->exportTo('JSON'));
    });

    $group->get('/geolocation', function ($request, $response, $args) {
        $family = $request->getAttribute('family');
        $familyAddress = $family->getAddress();
        $familyLatLong = GeoUtils::getLatLong($familyAddress);
        $familyDrivingInfo = GeoUtils::drivingDistanceMatrix(
            $familyAddress,
            ChurchMetaData::getChurchAddress()
        );
        $geoLocationInfo = array_merge($familyDrivingInfo, $familyLatLong);

        return $response->withJson($geoLocationInfo);
    });

    $group->get('/nav', function ($request, $response, $args) {
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

        return $response->withJson($familyNav);
    });

    $group->post('/verify', function ($request, $response, $args) {
        $family = $request->getAttribute('family');

        try {
            $family->sendVerifyEmail();

            return $response->withStatus(200);
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->error($e->getMessage());

            return $response->withStatus(500)
                ->withJson([
                    'message' => gettext('Error sending email(s)').' - '.gettext('Please check logs for more information'),
                    'trace'   => $e->getMessage(),
                ]);
        }
    });

    $group->get('/verify/url', function ($request, $response, $args) {
        $family = $request->getAttribute('family');
        TokenQuery::create()
            ->filterByType('verifyFamily')
            ->filterByReferenceId($family->getId())
            ->delete();
        $token = new Token();
        $token->build('verifyFamily', $family->getId());
        $token->save();
        $family->createTimeLineNote('verify-URL');

        return $response
            ->withJson(['url' => SystemURLs::getURL().'/external/verify/'.$token->getToken()]);
    });

    $group->post('/verify/now', function ($request, $response, $args) {
        $family = $request->getAttribute('family');
        $family->verify();

        return $response->withJson(['message' => 'Success']);
    });
})->add(FamilyAPIMiddleware::class);
