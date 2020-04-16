<?php

use ChurchCRM\Slim\Middleware\Request\FamilyAPIMiddleware;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Emails\FamilyVerificationEmail;
use ChurchCRM\Token;
use ChurchCRM\TokenQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Base\FileAssociationQuery;
use ChurchCRM\File;
use ChurchCRM\FileAssociation;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/family/{familyId:[0-9]+}', function () {

    $this->get('/files/{fileId:[0-9]+}', function (Request $request, Response $response, array $args) {
        $response = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $files = FileAssociationQuery::create() ->filterByFamilyId($args['familyId'])->filterByFileId($args['fileId'])->find();
        if (count($files) != 1) {
            return $response->withStatus(404, gettext("File does not exist"));
        }
        $file = $files->getFirst()->getFile();
        return $file->serveRequest($response);
    });

    $this->post('/files', function(Request $request, Response $response, array $args) {
        $newFiles = File::fromSlimRequest($request);
        foreach($newFiles as $newFile) {
            $fa = new FileAssociation();
            $fa->setFamilyId($args['familyId']);
            $fa->setFile($newFile);
            $fa->save();
            echo "Created new file association: " . print_r($fa, true);
        }
    });

    $this->get('/photo', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Family", $args['familyId']);
        return $res->write($photo->getPhotoBytes())->withHeader('Content-type', $photo->getPhotoContentType());
    });

    $this->post('/photo', function ($request, $response, $args) {
        $input = (object)$request->getParsedBody();
        $family = $request->getAttribute("family");
        $family->setImageFromBase64($input->imgBase64);
        return $response->withStatus(200);
    })->add(new EditRecordsRoleAuthMiddleware());

    $this->delete('/photo', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        return $response->withJSON(["status" => $family->deletePhoto()]);
    })->add(new EditRecordsRoleAuthMiddleware());

    $this->get('/thumbnail', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Family", $args['familyId']);
        return $res->write($photo->getThumbnailBytes())->withHeader('Content-type', $photo->getThumbnailContentType());
    });

    $this->get('', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        return $response->withHeader('Content-Type','application/json')->write($family->exportTo('JSON'));
    });

    $this->get('/geolocation', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        $familyAddress = $family->getAddress();
        $familyLatLong = GeoUtils::getLatLong($familyAddress);
        $familyDrivingInfo = GeoUtils::DrivingDistanceMatrix($familyAddress, ChurchMetaData::getChurchAddress());
        $geoLocationInfo = array_merge($familyDrivingInfo, $familyLatLong);
        return $response->withJson($geoLocationInfo);
    });

    $this->get('/nav', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        $familyNav = [];
        $familyNav["PreFamilyId"] = 0;
        $familyNav["NextFamilyId"] = 0;

        $tempFamily = FamilyQuery::create()->filterById($family->getId(), Criteria::LESS_THAN)->orderById(Criteria::DESC)->findOne();
        if ($tempFamily) {
            $familyNav["PreFamilyId"] = $tempFamily->getId();
        }

        $tempFamily = FamilyQuery::create()->filterById($family->getId(), Criteria::GREATER_THAN)->orderById()->findOne();
        if ($tempFamily) {
            $familyNav["NextFamilyId"] = $tempFamily->getId();
        }
        return $response->withJson($familyNav);
    });


    $this->post('/verify', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        TokenQuery::create()->filterByType("verifyFamily")->filterByReferenceId($family->getId())->delete();
        $token = new Token();
        $token->build("verifyFamily", $family->getId());
        $token->save();
        $email = new FamilyVerificationEmail($family->getEmails(), $family->getName(), $token->getToken());
        if ($email->send()) {
            $family->createTimeLineNote("verify-link");
            return $response->withStatus(200);
        } else {
            LoggerUtils::getAppLogger()->error($email->getError());
            return $response->withStatus(500)->withJSON(['message' => gettext("Error sending email(s)") . " - " . gettext("Please check logs for more information"), "trace" => $email->getError()]);
        }
    });

    $this->get('/verify/url', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        TokenQuery::create()->filterByType("verifyFamily")->filterByReferenceId($family->getId())->delete();
        $token = new Token();
        $token->build("verifyFamily", $family->getId());
        $token->save();
        $family->createTimeLineNote("verify-URL");
        return $response->withJSON(["url" => SystemURLs::getURL() . "/external/verify/" . $token->getToken()]);
    });

    $this->post('/verify/now', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        $family->verify();
        return $response->withJSON(["message" => "Success"]);
    });


})->add(new FamilyAPIMiddleware());

