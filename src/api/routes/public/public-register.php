<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Person;
use ChurchCRM\Family;
use ChurchCRM\Utils\LoggerUtils;

$app->group('/public/register', function () {
    $this->post('/family', 'registerFamily');
    $this->post('/family/', 'registerFamily');
    $this->post('/person', 'registerPerson');
    $this->post('/person/', 'registerPerson');
});

function registerFamily(Request $request, Response $response, array $args)
{
    $family = new Family();
    $family->fromJSON($request->getBody());
    $family->setEnteredBy(Person::SELF_REGISTER);
    $family->setDateEntered(new \DateTime());
    if (empty($family->getName()) || empty($family->getEmail())) {
        return $response->withStatus(401)->withJson(["error" => "Name and email are required"]);
    }
    $family->save();
    return $response->write($family->toJSON());
}

function registerPerson(Request $request, Response $response, array $args)
{

    $person = new Person();
    $person->fromJSON($request->getBody());
    $person->setEnteredBy(Person::SELF_REGISTER);
    $person->setDateEntered(new \DateTime());
    if (empty($person->getFirstName()) || empty($person->getLastName()) || empty($person->getEmail())) {
        return $response->withStatus(401)->withJson(["error" => "FirstName, LastName and email are required"]);
    }
    $person->save();
    return $response->write($person->toJSON());
}