<?php

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Map\TokenTableMap;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Slim\SlimUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/families', function (RouteCollectorProxy $group): void {
    $group->get('/latest', 'getLatestFamilies');
    $group->get('/updated', 'getUpdatedFamilies');
    $group->get('/anniversaries', 'getFamiliesWithAnniversaries');
    $group->get('/familiesInCart', function (Request $request, Response $response, array $args): Response {
        $familiesInCart = [];
        
        // Check if cart has items
        if (!empty($_SESSION['aPeopleCart'])) {
            $cartPersonIDs = $_SESSION['aPeopleCart'];
            
            // Optimized query: Query people by IDs in cart, get their families
            // This only loads people in cart (efficient) instead of all families
            $people = PersonQuery::create()
                ->filterById($cartPersonIDs)
                ->find();
            
            // Collect unique family IDs from the people in cart
            $uniqueFamilyIds = [];
            foreach ($people as $person) {
                $familyId = $person->getFamId();
                if (!in_array($familyId, $uniqueFamilyIds, false)) {
                    $uniqueFamilyIds[] = $familyId;
                    // Verify ALL members of this family are in cart
                    $family = FamilyQuery::create()->findPk($familyId);
                    if ($family && $family->checkAgainstCart()) {
                        $familiesInCart[] = $familyId;
                    }
                }
            }
        }
        
        return SlimUtils::renderJSON($response, ['familiesInCart' => $familiesInCart]);
    });

    $group->get('/email/without', function (Request $request, Response $response, array $args): Response {
        $families = FamilyQuery::create()->joinWithPerson()->find();

        $familiesWithoutEmails = [];
        foreach ($families as $family) {
            if (empty($family->getEmail())) {
                $hasEmail = false;
                foreach ($family->getPeopleSorted() as $person) {
                    if (!empty($person->getEmail() || !empty($person->getWorkEmail()))) {
                        $hasEmail = true;
                        break;
                    }
                }
                if (!$hasEmail) {
                    $familiesWithoutEmails[] = $family->toArray();
                }
            }
        }

        return SlimUtils::renderJSON($response, ['count' => count($familiesWithoutEmails), 'families' => $familiesWithoutEmails]);
    });

    $group->get('/search/{query}', function (Request $request, Response $response, array $args): Response {
        $query = $args['query'];
        $results = [];
        $q = FamilyQuery::create()
            ->filterByName("%$query%", Criteria::LIKE)
            ->limit(15)
            ->find();
        foreach ($q as $family) {
            $results[] = $family->toSearchArray();
        }

        return SlimUtils::renderJSON($response, ['Families' => $results]);
    });

    $group->get('/self-register', function (Request $request, Response $response, array $args): Response {
        $families = FamilyQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $families->toArray()]);
    });

    $group->get('/self-verify', function (Request $request, Response $response, array $args): Response {
        $verificationNotes = NoteQuery::create()
            ->filterByEnteredBy(Person::SELF_VERIFY)
            ->orderByDateEntered(Criteria::DESC)
            ->joinWithFamily()
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $verificationNotes->toArray()]);
    });

    $group->get('/pending-self-verify', function (Request $request, Response $response, array $args): Response {
        $pendingTokens = TokenQuery::create()
            ->filterByType(Token::TYPE_FAMILY_VERIFY)
            ->filterByRemainingUses(['min' => 1])
            ->filterByValidUntilDate(['min' => new DateTime()])
            ->addJoin(TokenTableMap::COL_REFERENCE_ID, FamilyTableMap::COL_FAM_ID)
            ->withColumn(FamilyTableMap::COL_FAM_NAME, 'FamilyName')
            ->withColumn(TokenTableMap::COL_REFERENCE_ID, 'FamilyId')
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $pendingTokens->toArray()]);
    });

    $group->get('/byCheckNumber/{scanString}', function (Request $request, Response $response, array $args): Response {
        $scanString = $args['scanString'];

        $financialService = new FinancialService();

        return SlimUtils::renderJSON($response, $financialService->getMemberByScanString($scanString));
    });
});

function getFamiliesWithAnniversaries(Request $request, Response $response, array $args): Response
{
    // Get anniversaries for 14-day range: 7 days before to 7 days after today
    $today = new \DateTime();
    $conditions = [];

    for ($i = -7; $i <= 7; $i++) {
        $date = (clone $today)->modify("{$i} days");
        $month = (int)$date->format('m');
        $day = (int)$date->format('d');
        // Values are safe: cast to int from DateTime::format()
        $conditions[] = "(MONTH(" . FamilyTableMap::COL_FAM_WEDDINGDATE . ") = {$month} AND DAY(" . FamilyTableMap::COL_FAM_WEDDINGDATE . ") = {$day})";
    }

    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->filterByWeddingdate(null, Criteria::NOT_EQUAL)
        ->where(implode(' OR ', $conditions))
        ->orderByWeddingdate('DESC')
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedFamilies($families));
}

function getLatestFamilies(Request $request, Response $response, array $args): Response
{
    $families = FamilyQuery::create()
        ->orderByDateEntered('DESC')
        ->limit(10)
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedFamilies($families));
}

function getUpdatedFamilies(Request $request, Response $response, array $args): Response
{
    $families = FamilyQuery::create()
        ->orderByDateLastEdited('DESC')
        ->limit(10)
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedFamilies($families));
}

function buildFormattedFamilies($families): array
{
    $formattedList = [];

    foreach ($families as $family) {
        $formattedFamily = [];
        $formattedFamily['FamilyId'] = $family->getId();
        $formattedFamily['Name'] = $family->getName();
        $formattedFamily['Address'] = $family->getAddress();
        $formattedFamily['HasPhoto'] = $family->getPhoto()->hasUploadedPhoto();
        $formattedFamily['IsActive'] = $family->isActive();
        $formattedFamily['StatusText'] = $family->getStatusText();
        
        $formattedFamily['Created'] = $family->getDateEntered() ? $family->getDateEntered()->format('c') : null; // ISO 8601
        $formattedFamily['LastEdited'] = $family->getDateLastEdited() ? $family->getDateLastEdited()->format('c') : null; // ISO 8601
        $formattedFamily['WeddingDate'] = $family->getWeddingdate() ? $family->getWeddingdate()->format('F j, Y') : null;

        $formattedList[] = $formattedFamily;
    }

    return ['families' => $formattedList];
}
