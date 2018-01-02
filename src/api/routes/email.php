<?php

use Propel\Runtime\Propel;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/emails', function () {
    $this->get('/duplicates', 'getEmailDupes');
});


/**
 * A method that review dup emails in the db and returns families and people where that email is used.
 *
 * @param \Slim\Http\Request $p_request The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 * @return \Slim\Http\Response The augmented response.
 */
function getEmailDupes(Request $request, Response $response, array $p_args)
{
    $connection = Propel::getConnection();
    $dupEmailsSQL = "SELECT email, total FROM email_count where total > 1";
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
            array_push($families, ["id" => $family->getId(), "nane" => $family->getName()]);
        }
        $emails[$email] = [
            "people" => $people,
            "families" => $families
        ];
    }

    return $response->withJson($emails);
}
