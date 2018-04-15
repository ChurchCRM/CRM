<?php

use ChurchCRM\Family;
use ChurchCRM\Person;
use ChurchCRM\Slim\Middleware\Request\Setting\PublicRegistrationAuthMiddleware;
use ChurchCRM\Utils\ORMUtils;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/public/register', function () {
    $this->post('/family', 'registerFamilyAPI');
    $this->post('/family/', 'registerFamilyAPI');
    $this->post('/person', 'registerPersonAPI');
    $this->post('/person/', 'registerPersonAPI');
})->add(new PublicRegistrationAuthMiddleware());

function registerFamilyAPI(Request $request, Response $response, array $args)
{
    $family = new Family();
    $family->fromJSON($request->getBody());
    $family->setId();  //ignore any ID set in the payload
    $family->setEnteredBy(Person::SELF_REGISTER);
    $family->setDateEntered(new \DateTime());

    if ($family->validate()) {
        $family->save();
        return $response->withJson($family->toArray());
    }

    return $response->withStatus(401)->withJson(["error" => gettext("Validation Error"),
        "failures" => ORMUtils::getValidationErrors($family->getValidationFailures())]);

}

function registerPersonAPI(Request $request, Response $response, array $args)
{

    $person = new Person();
    $person->fromJSON($request->getBody());
    $person->setId(); //ignore any ID set in the payload
    $person->setEnteredBy(Person::SELF_REGISTER);
    $person->setDateEntered(new \DateTime());
    if ($person->validate()) {
        $person->save();
        return $response->withJson($person->toArray());
    }

    return $response->withStatus(401)->withJson(["error" => gettext("Validation Error"),
        "failures" => ORMUtils::getValidationErrors($person->getValidationFailures())]);
}
