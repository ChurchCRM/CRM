<?php

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Slim\Middleware\Request\Setting\PublicRegistrationAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
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

/**
 * @OA\Post(
 *     path="/public/register/family",
 *     operationId="registerFamilyAPI",
 *     summary="Register a new family",
 *     description="Creates a new family and its members via public self-registration. Requires public registration to be enabled in system settings.",
 *     tags={"Registration"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"Name","Address1","City","State","Country","Zip","people"},
 *             @OA\Property(property="Name", type="string", example="Smith"),
 *             @OA\Property(property="Address1", type="string", example="123 Main St"),
 *             @OA\Property(property="Address2", type="string", nullable=true, example="Apt 4B"),
 *             @OA\Property(property="City", type="string", example="Seattle"),
 *             @OA\Property(property="State", type="string", example="WA"),
 *             @OA\Property(property="Country", type="string", example="US"),
 *             @OA\Property(property="Zip", type="string", example="98101"),
 *             @OA\Property(property="HomePhone", type="string", nullable=true, example="206-555-0100"),
 *             @OA\Property(property="Email", type="string", format="email", nullable=true, example="smith@example.com"),
 *             @OA\Property(
 *                 property="people",
 *                 type="array",
 *                 minItems=1,
 *                 @OA\Items(
 *                     required={"role","gender","firstName","lastName"},
 *                     @OA\Property(property="role", type="string", example="1", description="Family role ID (1=Head of Household). See GET /public/data for lookups."),
 *                     @OA\Property(property="gender", type="string", enum={"1","2"}, description="1=Male, 2=Female"),
 *                     @OA\Property(property="firstName", type="string", example="John"),
 *                     @OA\Property(property="lastName", type="string", example="Smith"),
 *                     @OA\Property(property="email", type="string", format="email", nullable=true),
 *                     @OA\Property(property="birthday", type="string", nullable=true, example="10/02/1985", description="Format: MM/DD/YYYY"),
 *                     @OA\Property(property="hideAge", type="boolean", default=false),
 *                     @OA\Property(property="cellPhone", type="string", nullable=true),
 *                     @OA\Property(property="homePhone", type="string", nullable=true),
 *                     @OA\Property(property="workPhone", type="string", nullable=true)
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Family registered successfully",
 *         @OA\JsonContent(description="The newly created family object", type="object",
 *             @OA\Property(property="Id", type="integer", example=42),
 *             @OA\Property(property="Name", type="string", example="Smith"),
 *             @OA\Property(property="Address1", type="string"),
 *             @OA\Property(property="City", type="string"),
 *             @OA\Property(property="State", type="string"),
 *             @OA\Property(property="Zip", type="string")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation error",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="Validation Error"),
 *             @OA\Property(property="failures", type="array", @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Person validation error or registration disabled")
 * )
 */
function registerFamilyAPI(Request $request, Response $response, array $args): Response
{
    $familyMetadata = [];

    foreach ($request->getParsedBody() as $key => $value) {
        if (is_string($value)) {
            $familyMetadata[$key] = InputUtils::sanitizeAndEscapeText($value);
        } elseif (is_array($value) && $key === 'people') {
            // Sanitize nested people array
            $familyMetadata[$key] = array_map(function ($person) {
                $sanitized = [];
                foreach ($person as $pKey => $pValue) {
                    if (is_string($pValue)) {
                        $sanitized[$pKey] = InputUtils::sanitizeAndEscapeText($pValue);
                    } else {
                        $sanitized[$pKey] = $pValue;
                    }
                }
                return $sanitized;
            }, $value);
        } else {
            $familyMetadata[$key] = $value;
        }
    };

    $family = new Family();
    $family->setName($familyMetadata['Name']);
    $family->setAddress1($familyMetadata['Address1']);
    $family->setAddress2($familyMetadata['Address2']);
    $family->setCity($familyMetadata['City']);
    $family->setState($familyMetadata['State']);
    $family->setCountry($familyMetadata['Country']);
    $family->setZip($familyMetadata['Zip']);
    $family->setHomePhone($familyMetadata['HomePhone']);
    $family->setEmail($familyMetadata['Email']);
    $family->setEnteredBy(Person::SELF_REGISTER);
    $family->setDateEntered(new DateTime());

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

    $familyMembers = [];

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

/**
 * @OA\Post(
 *     path="/public/register/person",
 *     operationId="registerPersonAPI",
 *     summary="Register a new individual person",
 *     description="Creates a standalone person record via public self-registration. Requires public registration to be enabled in system settings.",
 *     tags={"Registration"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"firstName","lastName","gender"},
 *             @OA\Property(property="firstName", type="string", example="Jane"),
 *             @OA\Property(property="lastName", type="string", example="Doe"),
 *             @OA\Property(property="gender", type="string", enum={"1","2"}, description="1=Male, 2=Female"),
 *             @OA\Property(property="email", type="string", format="email", nullable=true),
 *             @OA\Property(property="cellPhone", type="string", nullable=true),
 *             @OA\Property(property="homePhone", type="string", nullable=true),
 *             @OA\Property(property="workPhone", type="string", nullable=true),
 *             @OA\Property(property="address1", type="string", nullable=true),
 *             @OA\Property(property="address2", type="string", nullable=true),
 *             @OA\Property(property="city", type="string", nullable=true),
 *             @OA\Property(property="state", type="string", nullable=true),
 *             @OA\Property(property="zip", type="string", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Person registered successfully",
 *         @OA\JsonContent(description="The newly created person object (Propel JSON export)", type="object",
 *             @OA\Property(property="Id", type="integer", example=99),
 *             @OA\Property(property="FirstName", type="string", example="Jane"),
 *             @OA\Property(property="LastName", type="string", example="Doe")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation error",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="Validation Error"),
 *             @OA\Property(property="failures", type="array", @OA\Items(type="string"))
 *         )
 *     )
 * )
 */
function registerPersonAPI(Request $request, Response $response, array $args): Response
{
    // Sanitize input data before creating person
    $personData = [];
    foreach ($request->getParsedBody() as $key => $value) {
        if (is_string($value)) {
            $personData[$key] = InputUtils::sanitizeAndEscapeText($value);
        } else {
            $personData[$key] = $value;
        }
    }

    $person = new Person();
    
    // Set sanitized fields manually instead of using fromJSON
    if (isset($personData['firstName'])) {
        $person->setFirstName($personData['firstName']);
    }
    if (isset($personData['lastName'])) {
        $person->setLastName($personData['lastName']);
    }
    if (isset($personData['email'])) {
        $person->setEmail($personData['email']);
    }
    if (isset($personData['cellPhone'])) {
        $person->setCellPhone($personData['cellPhone']);
    }
    if (isset($personData['homePhone'])) {
        $person->setHomePhone($personData['homePhone']);
    }
    if (isset($personData['workPhone'])) {
        $person->setWorkPhone($personData['workPhone']);
    }
    if (isset($personData['gender'])) {
        $person->setGender($personData['gender']);
    }
    if (isset($personData['address1'])) {
        $person->setAddress1($personData['address1']);
    }
    if (isset($personData['address2'])) {
        $person->setAddress2($personData['address2']);
    }
    if (isset($personData['city'])) {
        $person->setCity($personData['city']);
    }
    if (isset($personData['state'])) {
        $person->setState($personData['state']);
    }
    if (isset($personData['zip'])) {
        $person->setZip($personData['zip']);
    }
    
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
