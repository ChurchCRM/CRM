<?php

/* Contributors Philippe Logel */

// Routes
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\dto\Photo;
use ChurchCRM\Emails\FamilyVerificationEmail;
use ChurchCRM\FamilyQuery;
use ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\Map\TokenTableMap;
use ChurchCRM\Note;
use ChurchCRM\NoteQuery;
use ChurchCRM\Person;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Token;
use ChurchCRM\TokenQuery;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Slim\Middleware\Role\DeleteRecordRoleAuthMiddleware;

$app->group('/families', function () {

    $this->get('/search/{query}', 'searchFamilies');
    $this->get('/numbers', 'getFamilyAnniversariesCount');
    $this->get('/self-register', 'getFamilySelfRegister');
    $this->get('/self-verify', 'getFamilySelfVerify');
    $this->get('/pending-self-verify', 'getFamilyPendingSelfVerify');
    $this->get('/byCheckNumber/{scanString}', 'getFamiliesByCheckNumber');

    $this->post('/verify/{familyId}/now', function ($request, $response, $args) {
        $familyId = $args["familyId"];
        $family = FamilyQuery::create()->findPk($familyId);
        if ($family != null) {
            $family->verify();
            $response = $response->withStatus(200);
        } else {
            $response = $response->withStatus(404, gettext("FamilyId") . ": " . $familyId . " " . gettext("not found"));
        }
        return $response;
    });


});

$app->group('/families/{familyId:[0-9]+}', function () {
    $this->get('', 'getFamilyById');
    $this->post('/verify', 'verifyFamily');
    $this->post('/activate/{status}', 'updateFamilyActiveStatus');
    $this->get('/geolocation', 'getFamilyGeoLocation');

    $this->post('/photo', 'uploadFamilyPhoto');
    $this->delete('/photo', 'deleteFamilyPhoto')->add(new DeleteRecordRoleAuthMiddleware());
    $this->get('/photo', function ($request, $response, $args) {
        $photo = new Photo("Family", $args['familyId']);
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        return $res->write($photo->getPhotoBytes())->withHeader('Content-type', $photo->getPhotoContentType());
    });

    $this->get('/thumbnail', function ($request, $response, $args) {
        $res = $this->cache->withExpires($response, MiscUtils::getPhotoCacheExpirationTimestamp());
        $photo = new Photo("Family", $args['familyId']);
        return $res->write($photo->getThumbnailBytes())->withHeader('Content-type', $photo->getThumbnailContentType());
    });

});

function getFamilyById(Request $request, Response $response, array $args)
{
    $family = FamilyQuery::create()->findPk($args['familyId']);
    if (!empty($family))
        return $response->withJson($family->toArray());
    else
        return $response->withStatus(404);
}

function uploadFamilyPhoto(Request $request, Response $response, array $args)
{
    $input = (object)$request->getParsedBody();
    $family = FamilyQuery::create()->findPk($args['familyId']);
    $family->setImageFromBase64($input->imgBase64);
    return $response->withStatus(200, array("status" => "success"));
}

function deleteFamilyPhoto(Request $request, Response $response, array $args)
{
    $family = FamilyQuery::create()->findPk($args['familyId']);
    return $response->withJson(array("status" => $family->deletePhoto()));

}

function verifyFamily(Request $request, Response $response, array $args)
{
    $familyId = $args["familyId"];
    $family = FamilyQuery::create()->findPk($familyId);
    if ($family != null) {
        TokenQuery::create()->filterByType("verifyFamily")->filterByReferenceId($family->getId())->delete();
        $token = new Token();
        $token->build("verifyFamily", $family->getId());
        $token->save();
        $email = new FamilyVerificationEmail($family->getEmails(), $family->getName(), $token->getToken());
        if ($email->send()) {
            $family->createTimeLineNote("verify-link");
            $response = $response->withStatus(200);
        } else {
            $response = $response->withStatus(404, gettext("FamilyId"). ": " . $familyId . " ". gettext("email send error"));
        }
    } else {
        $response = $response->withStatus(404, gettext("FamilyId") . " " . $familyId . " " . gettext("not found"));
    }
    return $response;
}

function getFamilyGeoLocation(Request $request, Response $response, array $args)
{
    $familyId = $args["familyId"];
    $family = FamilyQuery::create()->findPk($familyId);
    if (!empty($family)) {
        $familyAddress = $family->getAddress();
        $familyLatLong = GeoUtils::getLatLong($familyAddress);

        $familyDrivingInfo = GeoUtils::DrivingDistanceMatrix($familyAddress, ChurchMetaData::getChurchAddress());
        $geoLocationInfo = array_merge($familyDrivingInfo, $familyLatLong);

        return $response->withJson($geoLocationInfo);
    }
    return $response->withStatus(404, gettext("FamilyId" . ": " . $familyId . " " . gettext("not found")));
}

function updateFamilyActiveStatus(Request $request, Response $response, array $args)
{
    $familyId = $args["familyId"];
    $newStatus = $args["status"];

    $family = FamilyQuery::create()->findPk($familyId);
    $currentStatus = (empty($family->getDateDeactivated()) ? 'true' : 'false');

    //update only if the value is different
    if ($currentStatus != $newStatus) {
        if ($newStatus == "false") {
            $family->setDateDeactivated(date('YmdHis'));
        } elseif ($newStatus == "true") {
            $family->setDateDeactivated(Null);
        }
        $family->save();

        //Create a note to record the status change
        $note = new Note();
        $note->setFamId($familyId);
        if ($newStatus == 'false') {
            $note->setText(gettext('Deactivated the Family'));
        } else {
            $note->setText(gettext('Activated the Family'));
        }
        $note->setType('edit');
        $note->setEntered($_SESSION['user']->getId());
        $note->save();
    }
    return $response->withJson(['success' => true]);

}

function getFamiliesByCheckNumber(Request $request, Response $response, array $args)
{
    $scanString = $args['scanString'];
    $financialService = new FinancialService();
    $response->write($financialService->getMemberByScanString($scanString));
}

function getFamilyPendingSelfVerify(Request $request, Response $response, array $args)
{
    $pendingTokens = TokenQuery::create()
        ->filterByType(Token::typeFamilyVerify)
        ->filterByRemainingUses(array('min' => 1))
        ->filterByValidUntilDate(array('min' => new DateTime()))
        ->addJoin(TokenTableMap::COL_REFERENCE_ID, FamilyTableMap::COL_FAM_ID)
        ->withColumn(FamilyTableMap::COL_FAM_NAME, "FamilyName")
        ->withColumn(TokenTableMap::COL_REFERENCE_ID, "FamilyId")
        ->limit(100)
        ->find();
    return $response->withJSON(['families' => $pendingTokens->toArray()]);

}

function getFamilySelfVerify(Request $request, Response $response, array $args)
{
    $verifcationNotes = NoteQuery::create()
        ->filterByEnteredBy(Person::SELF_VERIFY)
        ->orderByDateEntered(Criteria::DESC)
        ->joinWithFamily()
        ->limit(100)
        ->find();
    return $response->withJSON(['families' => $verifcationNotes->toArray()]);
}

function getFamilySelfRegister(Request $request, Response $response, array $args)
{
    $families = FamilyQuery::create()
        ->filterByEnteredBy(Person::SELF_REGISTER)
        ->orderByDateEntered(Criteria::DESC)
        ->limit(100)
        ->find();
    return $response->withJSON(['families' => $families->toArray()]);
}

function getFamilyAnniversariesCount(Request $request, Response $response, array $args)
{
    return $response->withJson(MenuEventsCount::getNumberAnniversaries());
}

function searchFamilies(Request $request, Response $response, array $args)
{
    $query = $args['query'];
    $results = [];
    $q = FamilyQuery::create()
        ->filterByName("%$query%", Criteria::LIKE)
        ->limit(15)
        ->find();
    foreach ($q as $family) {
        array_push($results, $family->toSearchArray());
    }

    return $response->withJSON(["Families" => $results]);
}
