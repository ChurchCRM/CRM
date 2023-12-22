<?php

use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Request\SlimUtils;
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

    // search person by Name
    $group->get('/search/{query}', function (Request $request, Response $response, array $args): Response {
        $query = $args['query'];

        $searchLikeString = '%' . $query . '%';
        $people = PersonQuery::create()->
        filterByFirstName($searchLikeString, Criteria::LIKE)->
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

    $group->get(
        '/numbers',
        fn (Request $request, Response $response, array $args): Response => SlimUtils::renderJSON($response, MenuEventsCount::getNumberBirthDates())
    );

    $group->get('/self-register', function (Request $request, Response $response, array $args): Response {
        $people = PersonQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['people' => $people->toArray()]);
    });
});

function getAllRolesAPI(Request $request, Response $response, array $args): Response
{
    $roles = ListOptionQuery::create()->getFamilyRoles();

    return SlimUtils::renderJSON($response, $roles->toArray());
}

/**
 * A method that review dup emails in the db and returns families and people where that email is used.
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

function getLatestPersons(Request $request, Response $response, array $args): Response
{
    $people = PersonQuery::create()
        ->leftJoinWithFamily()
        ->where('Family.DateDeactivated is null')
        ->orderByDateEntered('DESC')
        ->limit(10)
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedPersonList($people, true, false, false));
}

function getUpdatedPersons(Request $request, Response $response, array $args): Response
{
    $people = PersonQuery::create()
        ->leftJoinWithFamily()
        ->where('Family.DateDeactivated is null')
        ->orderByDateLastEdited('DESC')
        ->limit(10)
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedPersonList($people, false, true, false));
}

function getPersonsWithBirthdays(Request $request, Response $response, array $args): Response
{
    $people = PersonQuery::create()
        ->filterByBirthMonth(date('m'))
        ->filterByBirthDay(date('d'))
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedPersonList($people, false, false, true));
}

function buildFormattedPersonList(Collection $people, bool $created, bool $edited, bool $birthday)
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
        if ($created && $person->getDateEntered()) {
            $formattedPerson['Created'] = date_format($person->getDateEntered(), SystemConfig::getValue('sDateFormatLong'));
        }

        if ($edited && $person->getDateLastEdited()) {
            $formattedPerson['LastEdited'] = date_format($person->getDateLastEdited(), SystemConfig::getValue('sDateFormatLong'));
        }

        if ($birthday && $person->getBirthDate()) {
            $formattedPerson['Birthday'] = date_format(
                $person->getBirthDate(),
                $person->hideAge() ?
                    SystemConfig::getValue('sDateFormatNoYear') :
                    SystemConfig::getValue('sDateFormatLong')
            );
        }

        $formattedList[] = $formattedPerson;
    }

    return ['people' => $formattedList];
}
