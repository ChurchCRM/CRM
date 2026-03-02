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
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/families', function (RouteCollectorProxy $group): void {
    $group->get('/latest', 'getLatestFamilies');
    $group->get('/updated', 'getUpdatedFamilies');
    $group->get('/anniversaries', 'getFamiliesWithAnniversaries');

    /**
     * @OA\Get(
     *     path="/families/familiesInCart",
     *     summary="Get families whose all members are in the session cart",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="List of family IDs where every member is in the cart",
     *         @OA\JsonContent(@OA\Property(property="familiesInCart", type="array", @OA\Items(type="integer")))
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/families/email/without",
     *     summary="Get families with no email address on record",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Families without any email address",
     *         @OA\JsonContent(
     *             @OA\Property(property="count", type="integer"),
     *             @OA\Property(property="families", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/families/search/{query}",
     *     summary="Search families by name (max 15 results)",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="query", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Matching families",
     *         @OA\JsonContent(@OA\Property(property="Families", type="array", @OA\Items(type="object")))
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/families/self-register",
     *     summary="Get the last 100 self-registered families",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Self-registered families ordered by date entered descending",
     *         @OA\JsonContent(@OA\Property(property="families", type="array", @OA\Items(type="object")))
     *     )
     * )
     */
    $group->get('/self-register', function (Request $request, Response $response, array $args): Response {
        $families = FamilyQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $families->toArray()]);
    });

    /**
     * @OA\Get(
     *     path="/families/self-verify",
     *     summary="Get the last 100 families with self-verification notes",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Families that submitted self-verification notes",
     *         @OA\JsonContent(@OA\Property(property="families", type="array", @OA\Items(type="object")))
     *     )
     * )
     */
    $group->get('/self-verify', function (Request $request, Response $response, array $args): Response {
        $verificationNotes = NoteQuery::create()
            ->filterByEnteredBy(Person::SELF_VERIFY)
            ->orderByDateEntered(Criteria::DESC)
            ->joinWithFamily()
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $verificationNotes->toArray()]);
    });

    /**
     * @OA\Get(
     *     path="/families/pending-self-verify",
     *     summary="Get families with pending (unused, non-expired) self-verify tokens",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Pending verification tokens with family names",
     *         @OA\JsonContent(@OA\Property(property="families", type="array", @OA\Items(type="object")))
     *     )
     * )
     */
    $group->get('/pending-self-verify', function (Request $request, Response $response, array $args): Response {
        $pendingTokens = TokenQuery::create()
            ->filterByType(Token::TYPE_FAMILY_VERIFY)
            ->filterByRemainingUses(['min' => 1])
            ->filterByValidUntilDate(['min' => DateTimeUtils::getToday()])
            ->addJoin(TokenTableMap::COL_REFERENCE_ID, FamilyTableMap::COL_FAM_ID)
            ->withColumn(FamilyTableMap::COL_FAM_NAME, 'FamilyName')
            ->withColumn(TokenTableMap::COL_REFERENCE_ID, 'FamilyId')
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $pendingTokens->toArray()]);
    });

    /**
     * @OA\Get(
     *     path="/families/byCheckNumber/{scanString}",
     *     summary="Find a family by check scan string",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="scanString", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Family member matched by check scan string")
     * )
     */
    $group->get('/byCheckNumber/{scanString}', function (Request $request, Response $response, array $args): Response {
        $scanString = $args['scanString'];

        $financialService = new FinancialService();

        return SlimUtils::renderJSON($response, $financialService->getMemberByScanString($scanString));
    });
});

/**
 * @OA\Get(
 *     path="/families/anniversaries",
 *     summary="Get families with wedding anniversaries within 7 days of today",
 *     tags={"Families"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Families with upcoming/recent anniversaries (±7 days)",
 *         @OA\JsonContent(
 *             @OA\Property(property="families", type="array", @OA\Items(
 *                 @OA\Property(property="FamilyId", type="integer"),
 *                 @OA\Property(property="Name", type="string"),
 *                 @OA\Property(property="WeddingDate", type="string")
 *             ))
 *         )
 *     )
 * )
 */
function getFamiliesWithAnniversaries(Request $request, Response $response, array $args): Response
{
    // Get anniversaries for 14-day range: 7 days before to 7 days after today
    // Use configured timezone to ensure correct "today" calculation
    $today = DateTimeUtils::getToday();
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

/**
 * @OA\Get(
 *     path="/families/latest",
 *     summary="Get the 10 most recently added families",
 *     tags={"Families"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="10 latest families by date entered",
 *         @OA\JsonContent(
 *             @OA\Property(property="families", type="array", @OA\Items(
 *                 @OA\Property(property="FamilyId", type="integer"),
 *                 @OA\Property(property="Name", type="string"),
 *                 @OA\Property(property="Created", type="string", format="date-time")
 *             ))
 *         )
 *     )
 * )
 */
function getLatestFamilies(Request $request, Response $response, array $args): Response
{
    $families = FamilyQuery::create()
        ->orderByDateEntered('DESC')
        ->limit(10)
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedFamilies($families));
}

/**
 * @OA\Get(
 *     path="/families/updated",
 *     summary="Get the 10 most recently updated families",
 *     tags={"Families"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="10 families ordered by last edit date descending",
 *         @OA\JsonContent(
 *             @OA\Property(property="families", type="array", @OA\Items(
 *                 @OA\Property(property="FamilyId", type="integer"),
 *                 @OA\Property(property="Name", type="string"),
 *                 @OA\Property(property="LastEdited", type="string", format="date-time")
 *             ))
 *         )
 *     )
 * )
 */
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
