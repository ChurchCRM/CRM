<?php

use ChurchCRM\Family;
use ChurchCRM\Person;
use ChurchCRM\Slim\Middleware\Request\Setting\PublicRegistrationAuthMiddleware;
use ChurchCRM\Utils\LoggerUtils;
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

    $familyMetadata = (object)$request->getParsedBody();

    $family->setName($familyMetadata->Name);
    $family->setAddress1($familyMetadata->Address1);
    $family->setAddress2($familyMetadata->Address2);
    $family->setCity($familyMetadata->City);
    $family->setState($familyMetadata->State);
    $family->setCountry($familyMetadata->Country);
    $family->setZip($familyMetadata->Zip);
    $family->setHomePhone($familyMetadata->HomePhone);
    $family->setWorkPhone($familyMetadata->WorkPhone);
    $family->setCellPhone($familyMetadata->CellPhone);
    $family->setEmail($familyMetadata->Email);
    $family->setEnteredBy(Person::SELF_REGISTER);
    $family->setDateEntered(new \DateTime());

    $familyMembers = [];

    if ($family->validate()) {
        foreach ($familyMetadata->people as $personMetaData) {
            $person = new Person();
            $person->setEnteredBy(Person::SELF_REGISTER);
            $person->setDateEntered(new \DateTime());
            $person->setFirstName($personMetaData["firstName"]);
            $person->setLastName($personMetaData["lastName"]);
            $person->setGender($personMetaData["gender"]);
            $person->setFmrId($personMetaData["role"]);
            $person->setEmail($personMetaData["email"]);
            $person->setCellPhone($personMetaData["cellPhone"]);
            $person->setHomePhone($personMetaData["homePhone"]);
            $person->setWorkPhone($personMetaData["workPhone"]);
            $person->setFlags($personMetaData["hideAge"] ? "1" : 0);

            $birthday = $personMetaData["birthday"];
            if (!empty($birthday)) {
                $birthdayDate = \DateTime::createFromFormat('m/d/Y', $birthday);
                $person->setBirthDay($birthdayDate->format('d'));
                $person->setBirthMonth($birthdayDate->format('m'));
                $person->setBirthYear($birthdayDate->format('Y'));
            }

            if (!$person->validate()) {
                LoggerUtils::getAppLogger()->error("Public Reg Error with the following data: " . json_encode($personMetaData));
                return $response->withStatus(401)->withJson(["error" => gettext("Validation Error"),
                    "failures" => ORMUtils::getValidationErrors($person->getValidationFailures())]);
            }
            array_push($familyMembers, $person);
        }

    } else {
        return $response->withStatus(400)->withJson(["error" => gettext("Validation Error"),
            "failures" => ORMUtils::getValidationErrors($family->getValidationFailures())]);
    }

    $family->save();
    foreach ($familyMembers as $person) {
        $person->setFamily($family);
        $family->addPerson($person);
        $person->save();
    }

    $family->save();
    return $response->withHeader('Content-Type','application/json')->write($family->exportTo('JSON'));
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
        return $response->withHeader('Content-Type','application/json')->write($person->exportTo('JSON'));
    }

    return $response->withStatus(400)->withJson(["error" => gettext("Validation Error"),
        "failures" => ORMUtils::getValidationErrors($person->getValidationFailures())]);
}
