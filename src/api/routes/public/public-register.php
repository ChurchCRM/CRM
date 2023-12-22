<?php

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Slim\Middleware\Request\Setting\PublicRegistrationAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\ORMUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public/register', function (RouteCollectorProxy $group): void {
    $group->post('/family', 'registerFamilyAPI');
    $group->post('/family/', 'registerFamilyAPI');
    $group->post('/person', 'registerPersonAPI');
    $group->post('/person/', 'registerPersonAPI');
})->add(PublicRegistrationAuthMiddleware::class);

function registerFamilyAPI(Request $request, Response $response, array $args): Response
{
    $family = new Family();

    $familyMetadata = $request->getParsedBody();

    $family->setName($familyMetadata['Name']);
    $family->setAddress1($familyMetadata['Address1']);
    $family->setAddress2($familyMetadata['Address2']);
    $family->setCity($familyMetadata['City']);
    $family->setState($familyMetadata['State']);
    $family->setCountry($familyMetadata['Country']);
    $family->setZip($familyMetadata['Zip']);
    $family->setHomePhone($familyMetadata['HomePhone']);
    $family->setWorkPhone($familyMetadata['WorkPhone']);
    $family->setCellPhone($familyMetadata['CellPhone']);
    $family->setEmail($familyMetadata['Email']);
    $family->setEnteredBy(Person::SELF_REGISTER);
    $family->setDateEntered(new DateTime());

    $familyMembers = [];
    if (!$family->validate()) {
        return SlimUtils::renderJSON(
            $response,
            [
                'error' => gettext('Validation Error'),
                'failures' => ORMUtils::getValidationErrors($family->getValidationFailures())
            ],
            400
        );
    }
    foreach ($familyMetadata['people'] as $personMetaData) {
        $person = new Person();
        $person->setEnteredBy(Person::SELF_REGISTER);
        $person->setDateEntered(new DateTime());
        $person->setFirstName($personMetaData['firstName']);
        $person->setLastName($personMetaData['lastName']);
        $person->setGender($personMetaData['gender']);
        $person->setFmrId($personMetaData['role']);
        $person->setEmail($personMetaData['email']);
        $person->setCellPhone($personMetaData['cellPhone']);
        $person->setHomePhone($personMetaData['homePhone']);
        $person->setWorkPhone($personMetaData['workPhone']);
        $person->setFlags($personMetaData['hideAge'] ? '1' : 0);

        $birthday = $personMetaData['birthday'];
        if (!empty($birthday)) {
            $birthdayDate = DateTime::createFromFormat('m/d/Y', $birthday);
            $person->setBirthDay($birthdayDate->format('d'));
            $person->setBirthMonth($birthdayDate->format('m'));
            $person->setBirthYear($birthdayDate->format('Y'));
        }

        if (!$person->validate()) {
            LoggerUtils::getAppLogger()->error('Public Reg Error with the following data: ' . json_encode($personMetaData, JSON_THROW_ON_ERROR));

            return SlimUtils::renderJSON($response, ['error' => gettext('Validation Error'),
                'failures' => ORMUtils::getValidationErrors($person->getValidationFailures())], 401);
        }
        $familyMembers[] = $person;
    }

    $family->save();
    foreach ($familyMembers as $person) {
        $person->setFamily($family);
        $family->addPerson($person);
        $person->save();
    }

    $family->save();
    return SlimUtils::renderJSON($response, $family->toArray());
}

function registerPersonAPI(Request $request, Response $response, array $args): Response
{
    $person = new Person();
    $person->fromJSON($request->getBody());
    $person->setEnteredBy(Person::SELF_REGISTER);
    $person->setDateEntered(new DateTime());
    if (!$person->validate()) {
        return SlimUtils::renderJSON(
            $response,
            [
                'error' => gettext('Validation Error'),
                'failures' => ORMUtils::getValidationErrors($person->getValidationFailures())
            ],
            400
        );
    }

    $person->save();

    return SlimUtils::renderStringJSON($response, $person->exportTo('JSON'));
}
