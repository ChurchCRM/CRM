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
    $body = json_decode($request->getBody());
    $family = new Family();
    return $response->withStatus(401);
}

function registerPerson(Request $request, Response $response, array $args)
{
    $body = json_decode($request->getBody());
    if (empty($body->firstName) || empty($body->lastName) || empty($body->email)) {
        return $response->withStatus(401)->withJson(["error" => "FirstName, LastName and email are required"]);
    }

    $person = new Person();
    $person->setFirstName($body->firstName);
    $person->setMiddleName($body->middleName);
    $person->setLastName($body->lastName);
    $person->setTitle($body->title);
    $person->setEmail($body->email);
    $person->setCellPhone($body->cellPhone);
    $person->setWorkEmail($body->workEmail);
    $person->setWorkPhone($body->workPhone);
    $person->setHomePhone($body->homePhone);
    $person->setAddress1($body->address1);
    $person->setAddress2($body->address2);
    $person->setCity($body->city);
    $person->setZip($body->zip);
    $person->setState($body->state);
    $person->setCountry($body->countryCode);
    $person->setGender($body->genderCode);
    $person->setEnteredBy(Person::SELF_REGISTER);
    $person->setDateEntered(new \DateTime());
    $person->save();
    return $response->write($person->toJSON());
}