<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Exceptions\ImportantDateRefusedException;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\ImportantDateType;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Service\ImportantDateService;
use ChurchCRM\Slim\Middleware\Api\FamilyMiddleware;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * The dates list plus what the caller may add: active types, each marked when
 * it allows one date and one is already recorded.
 *
 * @param list<array<string, mixed>> $dates
 */
function importantDatesPayload(ImportantDateService $service, string $appliesTo, array $dates, bool $canEdit): array
{
    $recordedTypeIds = array_column(array_filter($dates, static fn (array $d): bool => $d['typeId'] !== null), 'typeId');
    $types = [];
    foreach ($service->activeTypesFor($appliesTo) as $type) {
        $types[] = [
            'id'            => (int) $type->getId(),
            'name'          => (string) $type->getName(),
            'allowMultiple' => (bool) $type->getAllowMultiple(),
            'recorded'      => in_array((int) $type->getId(), $recordedTypeIds, true),
        ];
    }

    return [
        'dates'          => $dates,
        'types'          => $types,
        'canEdit'        => $canEdit,
        'canManageTypes' => AuthenticationManager::getCurrentUser()->isAdmin(),
    ];
}

function importantDateRefused(Response $response, ImportantDateRefusedException $e): Response
{
    return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], $e->isConflict() ? 409 : 400);
}

$importantDateInput = new InputSanitizationMiddleware(['note' => 'text']);

/**
 * @OA\Get(
 *     path="/person/{personId}/important-dates",
 *     operationId="getPersonImportantDates",
 *     summary="A person's yearly dates, soonest first",
 *     description="Birthday, membership, friend and death dates from the person record plus church-defined important dates, each with its recorded date, days until its next observed day, and a relative label. Also lists the active date types that can be added.",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Dates, addable types and whether the caller may edit"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Person not found")
 * )
 * @OA\Post(
 *     path="/person/{personId}/important-dates",
 *     operationId="addPersonImportantDate",
 *     summary="Record an important date on a person",
 *     description="Requires permission to edit the person (EditRecords, or EditSelf for the user's own family).",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"typeId","month","day"},
 *         @OA\Property(property="typeId", type="integer"),
 *         @OA\Property(property="month", type="integer", minimum=1, maximum=12),
 *         @OA\Property(property="day", type="integer", minimum=1, maximum=31),
 *         @OA\Property(property="year", type="integer", nullable=true),
 *         @OA\Property(property="note", type="string", maxLength=255)
 *     )),
 *     @OA\Response(response=201, description="The person's dates after the change"),
 *     @OA\Response(response=400, description="Invalid date or type"),
 *     @OA\Response(response=403, description="Cannot edit this person"),
 *     @OA\Response(response=409, description="The type allows one date and one is already recorded")
 * )
 * @OA\Put(
 *     path="/person/{personId}/important-dates/{dateId}",
 *     operationId="updatePersonImportantDate",
 *     summary="Change the date or note of a person's important date",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="dateId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="The person's dates after the change"),
 *     @OA\Response(response=400, description="Invalid date"),
 *     @OA\Response(response=403, description="Cannot edit this person"),
 *     @OA\Response(response=404, description="Date not found on this person")
 * )
 * @OA\Delete(
 *     path="/person/{personId}/important-dates/{dateId}",
 *     operationId="removePersonImportantDate",
 *     summary="Remove a person's important date",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="dateId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="The person's dates after the change"),
 *     @OA\Response(response=403, description="Cannot edit this person"),
 *     @OA\Response(response=404, description="Date not found on this person")
 * )
 */
$app->group('/person/{personId:[0-9]+}/important-dates', function (RouteCollectorProxy $group) use ($importantDateInput): void {
    $canEdit = static function (Person $person): bool {
        return AuthenticationManager::getCurrentUser()->canEditPerson((int) $person->getId(), (int) $person->getFamId());
    };
    $payload = static function (Person $person) use ($canEdit): array {
        $service = new ImportantDateService();

        return importantDatesPayload($service, ImportantDateType::APPLIES_TO_PERSON, $service->datesForPerson($person), $canEdit($person));
    };

    $group->get('', function (Request $request, Response $response) use ($payload): Response {
        return SlimUtils::renderJSON($response, $payload($request->getAttribute('person')));
    });

    $group->post('', function (Request $request, Response $response) use ($canEdit, $payload): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        if (!$canEdit($person)) {
            return SlimUtils::renderErrorJSON($response, gettext('You do not have permission to edit this person'), [], 403);
        }
        try {
            (new ImportantDateService())->addPersonDate($person, (array) $request->getParsedBody(), AuthenticationManager::getCurrentUser());
        } catch (ImportantDateRefusedException $e) {
            return importantDateRefused($response, $e);
        }

        return SlimUtils::renderJSON($response, $payload($person), 201);
    })->add($importantDateInput);

    $group->put('/{dateId:[0-9]+}', function (Request $request, Response $response, array $args) use ($canEdit, $payload): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        if (!$canEdit($person)) {
            return SlimUtils::renderErrorJSON($response, gettext('You do not have permission to edit this person'), [], 403);
        }
        $service = new ImportantDateService();
        $record = $service->findPersonDate($person, (int) $args['dateId']);
        if ($record === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Date not found'), [], 404);
        }
        try {
            $service->updateDate($record, (array) $request->getParsedBody(), AuthenticationManager::getCurrentUser());
        } catch (ImportantDateRefusedException $e) {
            return importantDateRefused($response, $e);
        }

        return SlimUtils::renderJSON($response, $payload($person));
    })->add($importantDateInput);

    $group->delete('/{dateId:[0-9]+}', function (Request $request, Response $response, array $args) use ($canEdit, $payload): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        if (!$canEdit($person)) {
            return SlimUtils::renderErrorJSON($response, gettext('You do not have permission to edit this person'), [], 403);
        }
        $service = new ImportantDateService();
        $record = $service->findPersonDate($person, (int) $args['dateId']);
        if ($record === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Date not found'), [], 404);
        }
        $service->removeDate($record);

        return SlimUtils::renderJSON($response, $payload($person));
    });
})->add(new PersonMiddleware());

/**
 * @OA\Get(
 *     path="/family/{familyId}/important-dates",
 *     operationId="getFamilyImportantDates",
 *     summary="A family's yearly dates, soonest first",
 *     description="The wedding anniversary from the family record plus church-defined important dates, and the active family date types that can be added.",
 *     tags={"Families"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Dates, addable types and whether the caller may edit"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="Family not found")
 * )
 * @OA\Post(
 *     path="/family/{familyId}/important-dates",
 *     operationId="addFamilyImportantDate",
 *     summary="Record an important date on a family",
 *     description="Requires EditRecords.",
 *     tags={"Families"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=201, description="The family's dates after the change"),
 *     @OA\Response(response=400, description="Invalid date or type"),
 *     @OA\Response(response=403, description="EditRecords required"),
 *     @OA\Response(response=409, description="The type allows one date and one is already recorded")
 * )
 * @OA\Put(
 *     path="/family/{familyId}/important-dates/{dateId}",
 *     operationId="updateFamilyImportantDate",
 *     summary="Change the date or note of a family's important date",
 *     tags={"Families"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="dateId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="The family's dates after the change"),
 *     @OA\Response(response=403, description="EditRecords required"),
 *     @OA\Response(response=404, description="Date not found on this family")
 * )
 * @OA\Delete(
 *     path="/family/{familyId}/important-dates/{dateId}",
 *     operationId="removeFamilyImportantDate",
 *     summary="Remove a family's important date",
 *     tags={"Families"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="dateId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="The family's dates after the change"),
 *     @OA\Response(response=403, description="EditRecords required"),
 *     @OA\Response(response=404, description="Date not found on this family")
 * )
 */
$app->group('/family/{familyId:[0-9]+}/important-dates', function (RouteCollectorProxy $group) use ($importantDateInput): void {
    $payload = static function (Family $family): array {
        $service = new ImportantDateService();

        return importantDatesPayload($service, ImportantDateType::APPLIES_TO_FAMILY, $service->datesForFamily($family), AuthenticationManager::getCurrentUser()->isEditRecordsEnabled());
    };

    $group->get('', function (Request $request, Response $response) use ($payload): Response {
        return SlimUtils::renderJSON($response, $payload($request->getAttribute('family')));
    });

    $group->post('', function (Request $request, Response $response) use ($payload): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        try {
            (new ImportantDateService())->addFamilyDate($family, (array) $request->getParsedBody(), AuthenticationManager::getCurrentUser());
        } catch (ImportantDateRefusedException $e) {
            return importantDateRefused($response, $e);
        }

        return SlimUtils::renderJSON($response, $payload($family), 201);
    })->add($importantDateInput)->add(new EditRecordsRoleAuthMiddleware());

    $group->put('/{dateId:[0-9]+}', function (Request $request, Response $response, array $args) use ($payload): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $service = new ImportantDateService();
        $record = $service->findFamilyDate($family, (int) $args['dateId']);
        if ($record === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Date not found'), [], 404);
        }
        try {
            $service->updateDate($record, (array) $request->getParsedBody(), AuthenticationManager::getCurrentUser());
        } catch (ImportantDateRefusedException $e) {
            return importantDateRefused($response, $e);
        }

        return SlimUtils::renderJSON($response, $payload($family));
    })->add($importantDateInput)->add(new EditRecordsRoleAuthMiddleware());

    $group->delete('/{dateId:[0-9]+}', function (Request $request, Response $response, array $args) use ($payload): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $service = new ImportantDateService();
        $record = $service->findFamilyDate($family, (int) $args['dateId']);
        if ($record === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Date not found'), [], 404);
        }
        $service->removeDate($record);

        return SlimUtils::renderJSON($response, $payload($family));
    })->add(new EditRecordsRoleAuthMiddleware());
})->add(new FamilyMiddleware());
