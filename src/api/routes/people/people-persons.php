<?php

use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Person;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/persons', function () {

    $this->get('/roles', 'getAllRolesAPI');
    $this->get('/roles/', 'getAllRolesAPI');
    $this->get('/duplicate/emails', 'getEmailDupesAPI');

    // search person by Name
    $this->get('/search/{query}', function ($request, $response, $args) {
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

            array_push($return, $values);
        }

        return $response->withJson($return);
    });

    $this->get('/numbers', function ($request, $response, $args) {
        return $response->withJson(MenuEventsCount::getNumberBirthDates());
    });

    $this->get('/self-register', function ($request, $response, $args) {
        $people = PersonQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();
        return $response->withJSON(['people' => $people->toArray()]);
    });

});

function getAllRolesAPI(Request $request, Response $response, array $p_args)
{
    $roles = ListOptionQuery::create()->getFamilyRoles();
    return $response->withJson($roles->toArray());
}

/**
 * A method that review dup emails in the db and returns families and people where that email is used.
 */
function getEmailDupesAPI(Request $request, Response $response, array $args)
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
            array_push($people, ["id" => $person->getId(), "name" => $person->getFullName()]);
        }
        $families = [];
        $dbFamilies = FamilyQuery::create()->findByEmail($email);
        foreach ($dbFamilies as $family) {
            array_push($families, ["id" => $family->getId(), "name" => $family->getName()]);
        }
        array_push($emails, [
            "email" => $email,
            "people" => $people,
            "families" => $families
        ]);
    }

    return $response->withJson(["emails" => $emails]);
}
