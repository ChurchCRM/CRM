<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Slim\Middleware\Api\FamilyMiddleware;
use ChurchCRM\Slim\Middleware\Api\NoteMiddleware;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\DeleteRecordRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\NotesRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Convert a Note ORM object to a plain array for API responses.
 */
function noteToArray(Note $note): array
{
    return [
        'id'            => $note->getId(),
        'perId'         => $note->getPerId(),
        'famId'         => $note->getFamId(),
        'text'          => $note->getText(),
        'private'       => $note->isPrivate(),
        'type'          => $note->getType(),
        'dateEntered'   => $note->getDateEntered('Y-m-d H:i:s'),
        'dateLastEdited'=> $note->getDateLastEdited('Y-m-d H:i:s') ?: null,
        'enteredBy'     => $note->getEnteredBy(),
        'editedBy'      => $note->getEditedBy(),
    ];
}

// ---------------------------------------------------------------------------
// Person notes  GET /person/{personId}/notes  POST /person/{personId}/note
// ---------------------------------------------------------------------------
$app->group('/person/{personId:[0-9]+}', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/person/{personId}/notes",
     *     operationId="getPersonNotes",
     *     summary="List user-type notes for a person",
     *     description="Returns all notes of type 'note' for the person, filtered by visibility for the current user. Private notes are only visible to admins or the user who entered them.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Note list",
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="perId", type="integer"),
     *                     @OA\Property(property="famId", type="integer"),
     *                     @OA\Property(property="text", type="string"),
     *                     @OA\Property(property="private", type="boolean"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="dateEntered", type="string", format="date-time"),
     *                     @OA\Property(property="dateLastEdited", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="enteredBy", type="integer"),
     *                     @OA\Property(property="editedBy", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Notes permission required"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    $group->get('/notes', function (Request $request, Response $response, array $args): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        $currentUser = AuthenticationManager::getCurrentUser();

        $notes = NoteQuery::create()
            ->filterByPerId($person->getId())
            ->filterByType('note')
            ->orderByDateEntered('DESC')
            ->find();

        $result = [];
        foreach ($notes as $note) {
            if ($currentUser->isAdmin() || $note->isVisible($currentUser->getPersonId())) {
                $result[] = noteToArray($note);
            }
        }

        return SlimUtils::renderJSON($response, ['notes' => $result]);
    });

    /**
     * @OA\Post(
     *     path="/person/{personId}/note",
     *     operationId="createPersonNote",
     *     summary="Create a note for a person",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"text"},
     *             @OA\Property(property="text", type="string", description="HTML note content (Quill output)"),
     *             @OA\Property(property="private", type="boolean", description="Mark note as private (visible to admins only)", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Note created",
     *         @OA\JsonContent(@OA\Property(property="note", type="object"))
     *     ),
     *     @OA\Response(response=400, description="Note text is required"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Notes permission required"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    $group->post('/note', function (Request $request, Response $response, array $args): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        $currentUser = AuthenticationManager::getCurrentUser();
        $input = (array) $request->getParsedBody();

        $text = InputUtils::sanitizeHTML($input['text'] ?? '');
        if ($text === '') {
            return SlimUtils::renderErrorJSON($response, gettext('Note text is required'), [], 400);
        }

        $private = !empty($input['private']) ? $currentUser->getPersonId() : 0;

        $note = new Note();
        $note->setPerId($person->getId());
        $note->setFamId(0);
        $note->setText($text);
        $note->setPrivate($private);
        $note->setType('note');
        $note->setEntered($currentUser->getId());
        $note->save();

        return SlimUtils::renderJSON($response, ['note' => noteToArray($note)], 201);
    });
})->add(new PersonMiddleware())->add(NotesRoleAuthMiddleware::class);

// ---------------------------------------------------------------------------
// Family notes  GET /family/{familyId}/notes  POST /family/{familyId}/note
// ---------------------------------------------------------------------------
$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/family/{familyId}/notes",
     *     operationId="getFamilyNotes",
     *     summary="List user-type notes for a family",
     *     description="Returns all notes of type 'note' for the family, filtered by visibility for the current user.",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Note list",
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="famId", type="integer"),
     *                     @OA\Property(property="text", type="string"),
     *                     @OA\Property(property="private", type="boolean"),
     *                     @OA\Property(property="dateEntered", type="string", format="date-time"),
     *                     @OA\Property(property="dateLastEdited", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="enteredBy", type="integer"),
     *                     @OA\Property(property="editedBy", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Notes permission required"),
     *     @OA\Response(response=404, description="Family not found")
     * )
     */
    $group->get('/notes', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $currentUser = AuthenticationManager::getCurrentUser();

        $notes = NoteQuery::create()
            ->filterByFamId($family->getId())
            ->filterByType('note')
            ->orderByDateEntered('DESC')
            ->find();

        $result = [];
        foreach ($notes as $note) {
            if ($currentUser->isAdmin() || $note->isVisible($currentUser->getPersonId())) {
                $result[] = noteToArray($note);
            }
        }

        return SlimUtils::renderJSON($response, ['notes' => $result]);
    });

    /**
     * @OA\Post(
     *     path="/family/{familyId}/note",
     *     operationId="createFamilyNote",
     *     summary="Create a note for a family",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"text"},
     *             @OA\Property(property="text", type="string", description="HTML note content (Quill output)"),
     *             @OA\Property(property="private", type="boolean", description="Mark note as private (visible to admins only)", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Note created",
     *         @OA\JsonContent(@OA\Property(property="note", type="object"))
     *     ),
     *     @OA\Response(response=400, description="Note text is required"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Notes permission required"),
     *     @OA\Response(response=404, description="Family not found")
     * )
     */
    $group->post('/note', function (Request $request, Response $response, array $args): Response {
        /** @var Family $family */
        $family = $request->getAttribute('family');
        $currentUser = AuthenticationManager::getCurrentUser();
        $input = (array) $request->getParsedBody();

        $text = InputUtils::sanitizeHTML($input['text'] ?? '');
        if ($text === '') {
            return SlimUtils::renderErrorJSON($response, gettext('Note text is required'), [], 400);
        }

        $private = !empty($input['private']) ? $currentUser->getPersonId() : 0;

        $note = new Note();
        $note->setPerId(0);
        $note->setFamId($family->getId());
        $note->setText($text);
        $note->setPrivate($private);
        $note->setType('note');
        $note->setEntered($currentUser->getId());
        $note->save();

        return SlimUtils::renderJSON($response, ['note' => noteToArray($note)], 201);
    });
})->add(FamilyMiddleware::class)->add(NotesRoleAuthMiddleware::class);

// ---------------------------------------------------------------------------
// Single note  GET /note/{noteId}  PUT /note/{noteId}  DELETE /note/{noteId}
// ---------------------------------------------------------------------------
$app->group('/note/{noteId:[0-9]+}', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/note/{noteId}",
     *     operationId="getNote",
     *     summary="Get a single note by ID",
     *     description="Returns the note if it is visible to the current user. Private notes are only visible to admins or the user who entered them.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="noteId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Note object",
     *         @OA\JsonContent(@OA\Property(property="note", type="object"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Notes permission required"),
     *     @OA\Response(response=404, description="Note not found or not visible to current user")
     * )
     */
    $group->get('', function (Request $request, Response $response, array $args): Response {
        /** @var Note $note */
        $note = $request->getAttribute('note');
        $currentUser = AuthenticationManager::getCurrentUser();

        if (!$currentUser->isAdmin() && !$note->isVisible($currentUser->getPersonId())) {
            return SlimUtils::renderErrorJSON($response, gettext('Note not found'), [], 404);
        }

        return SlimUtils::renderJSON($response, ['note' => noteToArray($note)]);
    });

    /**
     * @OA\Put(
     *     path="/note/{noteId}",
     *     operationId="updateNote",
     *     summary="Update a note's text and/or privacy",
     *     description="Only the note's author or an admin may update a note.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="noteId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"text"},
     *             @OA\Property(property="text", type="string", description="Updated HTML note content"),
     *             @OA\Property(property="private", type="boolean", description="Update privacy flag", example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated note",
     *         @OA\JsonContent(@OA\Property(property="note", type="object"))
     *     ),
     *     @OA\Response(response=400, description="Note text is required"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Only the note author or an admin may edit this note"),
     *     @OA\Response(response=404, description="Note not found")
     * )
     */
    $group->put('', function (Request $request, Response $response, array $args): Response {
        /** @var Note $note */
        $note = $request->getAttribute('note');
        $currentUser = AuthenticationManager::getCurrentUser();

        if (!$currentUser->isAdmin() && $note->getEnteredBy() !== $currentUser->getId()) {
            return SlimUtils::renderErrorJSON($response, gettext('Only the note author or an admin may edit this note'), [], 403);
        }

        $input = (array) $request->getParsedBody();
        $text = InputUtils::sanitizeHTML($input['text'] ?? '');
        if ($text === '') {
            return SlimUtils::renderErrorJSON($response, gettext('Note text is required'), [], 400);
        }

        $private = !empty($input['private']) ? $currentUser->getPersonId() : 0;

        $note->setText($text);
        $note->setPrivate($private);
        $note->setDateLastEdited(new \DateTime());
        $note->setEditedBy($currentUser->getId());
        $note->save();

        return SlimUtils::renderJSON($response, ['note' => noteToArray($note)]);
    });

    /**
     * @OA\Delete(
     *     path="/note/{noteId}",
     *     operationId="deleteNote",
     *     summary="Delete a note",
     *     description="Deletes the note. Requires Notes permission and Delete Records permission. Only the note's author or an admin may delete it.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="noteId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Note deleted",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Notes + Delete Records permission required, or not note author"),
     *     @OA\Response(response=404, description="Note not found")
     * )
     */
    $group->delete('', function (Request $request, Response $response, array $args): Response {
        /** @var Note $note */
        $note = $request->getAttribute('note');
        $currentUser = AuthenticationManager::getCurrentUser();

        if (!$currentUser->isAdmin() && $note->getEnteredBy() !== $currentUser->getId()) {
            return SlimUtils::renderErrorJSON($response, gettext('Only the note author or an admin may delete this note'), [], 403);
        }

        try {
            $note->delete();

            return SlimUtils::renderJSON($response, ['success' => true]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete note'), [], 500, $e, $request);
        }
    })->add(DeleteRecordRoleAuthMiddleware::class);
})->add(NoteMiddleware::class)->add(NotesRoleAuthMiddleware::class);
