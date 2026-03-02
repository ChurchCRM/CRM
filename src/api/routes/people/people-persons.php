<?php

use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/persons', function (RouteCollectorProxy $group): void {
    $group->get('/roles', 'getAllRolesAPI');
    $group->get('/roles/', 'getAllRolesAPI');
    $group->get('/duplicate/emails', 'getEmailDupesAPI');

    $group->get('/latest', 'getLatestPersons');
    $group->get('/updated', 'getUpdatedPersons');
    $group->get('/birthday', 'getPersonsWithBirthdays');

    /**
     * @OA\Get(
     *     path="/persons/search/{query}",
     *     operationId="searchPersons",
     *     summary="Search persons by name or email",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="query", in="path", required=true, description="Search term (name or email)", @OA\Schema(type="string", example="John")),
     *     @OA\Response(response=200, description="Array of matching persons (max 15)",
     *         @OA\JsonContent(type="array", @OA\Items(type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="objid", type="integer"),
     *             @OA\Property(property="text", type="string", example="John Smith"),
     *             @OA\Property(property="uri", type="string", example="/PersonView.php?PersonID=42")
     *         ))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    // search person by Name
    $group->get('/search/{query}', function (Request $request, Response $response, array $args): Response {
        $query = $args['query'];

        $searchLikeString = '%' . $query . '%';
        $people = PersonQuery::create()->
        filterByFirstName($searchLikeString, Criteria::LIKE)->
        _or()->filterByMiddleName($searchLikeString, Criteria::LIKE)->
        _or()->filterByLastName($searchLikeString, Criteria::LIKE)->
        _or()->filterByEmail($searchLikeString, Criteria::LIKE)->
        limit(15)->find();

        $id = 1;

        $return = [];
        foreach ($people as $person) {
            $values['id'] = $id++;
            $values['objid'] = $person->getId();
            $values['text'] = $person->getFullName();
            $values['uri'] = $person->getViewURI();

            $return[] = $values;
        }

        return SlimUtils::renderJSON($response, $return);
    });

    /**
     * @OA\Get(
     *     path="/persons/self-register",
     *     operationId="getSelfRegisteredPersons",
     *     summary="List recently self-registered persons",
     *     description="Returns up to 100 persons who registered via the public self-registration form, newest first.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="List of self-registered persons",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="people", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    $group->get('/self-register', function (Request $request, Response $response, array $args): Response {
        $people = PersonQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['people' => $people->toArray()]);
    });
});

/**
 * @OA\Get(
 *     path="/persons/roles",
 *     operationId="getAllRoles",
 *     summary="List all family roles",
 *     description="Returns all family role options (e.g. Head of Household, Spouse, Child) used when registering persons.",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Array of role objects",
 *         @OA\JsonContent(type="array", @OA\Items(type="object",
 *             @OA\Property(property="OptionId", type="integer", example=1),
 *             @OA\Property(property="OptionName", type="string", example="Head of Household")
 *         ))
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getAllRolesAPI(Request $request, Response $response, array $args): Response
{
    $roles = ListOptionQuery::create()->getFamilyRoles();

    return SlimUtils::renderJSON($response, $roles->toArray());
}

/**
 * A method that review dup emails in the db and returns families and people where that email is used.
 */
/**
 * @OA\Get(
 *     path="/persons/duplicate/emails",
 *     operationId="getEmailDuplicates",
 *     summary="Find duplicate email addresses across persons and families",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Emails used by more than one record",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="emails", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="email", type="string", format="email"),
 *                 @OA\Property(property="people", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string")
 *                 )),
 *                 @OA\Property(property="families", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string")
 *                 ))
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getEmailDupesAPI(Request $request, Response $response, array $args): Response
{
    $connection = Propel::getConnection();
    $dupEmailsSQL = "select email, total from ( SELECT email, COUNT(*) AS total FROM ( SELECT fam_Email AS email, 'family' AS type, fam_id AS id FROM family_fam WHERE fam_email IS NOT NULL AND fam_email != '' UNION SELECT per_email AS email, 'person_home' AS type, per_id AS id FROM person_per WHERE per_email IS NOT NULL AND per_email != '' UNION SELECT per_WorkEmail AS email, 'person_work' AS type, per_id AS id FROM person_per WHERE per_WorkEmail IS NOT NULL AND per_WorkEmail != '') as allEmails group by email) as dupEmails where total > 1";
    $statement = $connection->prepare($dupEmailsSQL);
    $statement->execute();
    $dupEmails = $statement->fetchAll();

    $emails = [];
    foreach ($dupEmails as $dbEmail) {
        $email = $dbEmail['email'];
        $dbPeople = PersonQuery::create()->filterByEmail($email)->_or()->filterByWorkEmail($email)->find();
        $people = [];
        foreach ($dbPeople as $person) {
            $people[] = ['id' => $person->getId(), 'name' => $person->getFullName()];
        }
        $families = [];
        $dbFamilies = FamilyQuery::create()->findByEmail($email);
        foreach ($dbFamilies as $family) {
            $families[] = ['id' => $family->getId(), 'name' => $family->getName()];
        }
        $emails[] = [
            'email' => $email,
            'people' => $people,
            'families' => $families,
        ];
    }

    return SlimUtils::renderJSON($response, ['emails' => $emails]);
}

/**
 * @OA\Get(
 *     path="/persons/latest",
 *     operationId="getLatestPersons",
 *     summary="List the 10 most recently added persons",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Recent persons with family info",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="people", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="PersonId", type="integer"),
 *                 @OA\Property(property="FirstName", type="string"),
 *                 @OA\Property(property="LastName", type="string"),
 *                 @OA\Property(property="Email", type="string"),
 *                 @OA\Property(property="FamilyId", type="integer", nullable=true),
 *                 @OA\Property(property="Created", type="string", format="date-time")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getLatestPersons(Request $request, Response $response, array $args): Response
{
    $people = PersonQuery::create()
        ->leftJoinWithFamily()
        ->orderByDateEntered('DESC')
        ->limit(10)
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedPersonList($people));
}

/**
 * @OA\Get(
 *     path="/persons/updated",
 *     operationId="getUpdatedPersons",
 *     summary="List the 10 most recently edited persons",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Recently updated persons",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="people", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="PersonId", type="integer"),
 *                 @OA\Property(property="FirstName", type="string"),
 *                 @OA\Property(property="LastName", type="string"),
 *                 @OA\Property(property="LastEdited", type="string", format="date-time")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getUpdatedPersons(Request $request, Response $response, array $args): Response
{
    $people = PersonQuery::create()
        ->leftJoinWithFamily()
        ->orderByDateLastEdited('DESC')
        ->limit(10)
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedPersonList($people));
}

/**
 * @OA\Get(
 *     path="/persons/birthday",
 *     operationId="getPersonsWithBirthdays",
 *     summary="List persons with birthdays in a ±7-day window around today",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Persons with upcoming or recent birthdays, sorted by days until birthday",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="people", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="PersonId", type="integer"),
 *                 @OA\Property(property="FirstName", type="string"),
 *                 @OA\Property(property="LastName", type="string"),
 *                 @OA\Property(property="Age", type="integer", nullable=true),
 *                 @OA\Property(property="DaysUntil", type="integer", description="Negative = past, 0 = today, positive = upcoming"),
 *                 @OA\Property(property="Birthday", type="string", example="March 15")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getPersonsWithBirthdays(Request $request, Response $response, array $args): Response
{
    // Get birthdays for 14-day range: 7 days before to 7 days after today
    // Use configured timezone to ensure correct "today" calculation
    $today = DateTimeUtils::getToday();
    $dates = [];
    
    for ($i = -7; $i <= 7; $i++) {
        $date = (clone $today)->modify("{$i} days");
        $dates[] = [
            'month' => (int)$date->format('m'),
            'day' => (int)$date->format('d'),
            'offset' => $i,
        ];
    }
    
    // Build query for all dates in range
    $people = PersonQuery::create()
        ->leftJoinWithFamily()
        ->where('Family.DateDeactivated is null')
        ->filterByBirthDay(null, Criteria::NOT_EQUAL)
        ->filterByBirthMonth(null, Criteria::NOT_EQUAL);
    
    // Add conditions for each day in range
    $conditions = [];
    foreach ($dates as $idx => $dateInfo) {
        $condName = 'date' . $idx;
        $people->condition(
            $condName . '_month',
            'Person.BirthMonth = ?',
            $dateInfo['month']
        );
        $people->condition(
            $condName . '_day', 
            'Person.BirthDay = ?',
            $dateInfo['day']
        );
        $people->combine(
            [$condName . '_month', $condName . '_day'],
            'and',
            $condName
        );
        $conditions[] = $condName;
    }
    $people->where($conditions, 'or');
    
    $results = $people->find();
    
    // Build list with age and days until birthday for sorting
    $formattedList = [];
    $thisYear = (int) $today->format('Y');
    
    foreach ($results as $person) {
        $formattedPerson = [];
        $formattedPerson['PersonId'] = $person->getId();
        $formattedPerson['FirstName'] = $person->getFirstName();
        $formattedPerson['LastName'] = $person->getLastName();
        $formattedPerson['FormattedName'] = $person->getFullName();
        $formattedPerson['HasPhoto'] = $person->getPhoto()->hasUploadedPhoto();
        
        // Calculate days until birthday this year (for sorting)
        $birthMonth = $person->getBirthMonth();
        $birthDay = $person->getBirthDay();
        $birthdayThisYear = DateTimeUtils::createDateTime("{$thisYear}-{$birthMonth}-{$birthDay}");
        $diff = (int) $today->diff($birthdayThisYear)->format('%r%a');
        
        // If birthday already passed this year (more than 7 days ago), calculate next year's birthday
        if ($diff < -7) {
            $birthdayNextYear = DateTimeUtils::createDateTime(($thisYear + 1) . "-{$birthMonth}-{$birthDay}");
            $diff = (int) $today->diff($birthdayNextYear)->format('%r%a');
        }
        
        $formattedPerson['DaysUntil'] = $diff;
        
        // Get age (respects hideAge setting)
        $formattedPerson['Age'] = $person->getAge();
        
        // Birthday date for display
        if ($person->getBirthDate()) {
            $formattedPerson['Birthday'] = date_format(
                $person->getBirthDate(),
                $person->hideAge() ?
                    SystemConfig::getValue('sDateFormatNoYear') :
                    SystemConfig::getValue('sDateFormatLong')
            );
            $formattedPerson['BirthMonth'] = $birthMonth;
            $formattedPerson['BirthDay'] = $birthDay;
        }
        
        $formattedList[] = $formattedPerson;
    }
    
    // Sort by days until birthday (ascending: today first, then upcoming, then recent past at end)
    usort($formattedList, function ($a, $b) {
        return $a['DaysUntil'] <=> $b['DaysUntil'];
    });
    
    return SlimUtils::renderJSON($response, ['people' => $formattedList]);
}

function buildFormattedPersonList(Collection $people): array
{
    $formattedList = [];

    /** @var Person $person */
    foreach ($people as $person) {
        $formattedPerson = [];
        $formattedPerson['PersonId'] = $person->getId();
        $formattedPerson['FirstName'] = $person->getFirstName();
        $formattedPerson['LastName'] = $person->getLastName();
        $formattedPerson['FormattedName'] = $person->getFullName();
        $formattedPerson['Email'] = $person->getEmail();
        $formattedPerson['HasPhoto'] = $person->getPhoto()->hasUploadedPhoto();
        
        // Add family information
        $family = $person->getFamily();
        if ($family !== null) {
            $formattedPerson['FamilyId'] = $family->getId();
            $formattedPerson['FamilyName'] = $family->getName();
            // Include family status so dashboard can render inactive badges next to family links
            $formattedPerson['FamilyIsActive'] = $family->isActive();
            $formattedPerson['FamilyStatusText'] = $family->getStatusText();
        } else {
            $formattedPerson['FamilyId'] = null;
            $formattedPerson['FamilyName'] = null;
        }
        
        $formattedPerson['Created'] = $person->getDateEntered() ? $person->getDateEntered()->format('c') : null; // ISO 8601
        $formattedPerson['LastEdited'] = $person->getDateLastEdited() ? $person->getDateLastEdited()->format('c') : null; // ISO 8601
        $formattedPerson['Birthday'] = $person->getBirthDate() ? $person->getBirthDate()->format('F j, Y') : null;

        $formattedList[] = $formattedPerson;
    }

    return ['people' => $formattedList];
}
