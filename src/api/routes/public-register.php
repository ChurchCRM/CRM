<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Person;
use ChurchCRM\Family;

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
    $person = new Person();
    $person->setFirstName($body->firstName);
    $person->setLastName($body->lastName);
    $person->setEmail($body->email);
    $person->setCellPhone($body->cellPhone);
    $person->setWorkEmail($body->workEmail);
    $person->setWorkPhone($body->workPhone);
    $person->setAddress1($body->address1);
    $person->setAddress2($body->address2);
    $person->setCity($body->city);
    $person->setZip($body->zip);
    $person->setState($body->state);
    $person->setCountry($body->countryCode);
    $person->setGender($body->gender);
    $person->save();
    return $response->withStatus(401);
}