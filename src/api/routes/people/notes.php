<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Middleware\Api\FamilyMiddleware;
use ChurchCRM\Slim\Middleware\Api\NoteMiddleware;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\NotesReadAuthMiddleware;
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
     *     description="Returns all notes of type 'note' for the person, filtered by visibility for the current user. Requires Notes=1 or Admin. Private notes authored by others are excluded for Notes=1 users; admins see all including private.",
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
     *     @OA\Response(response=403, description="Notes read permission required"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    $group->get('/notes', function (Request $request, Response $response, array $args): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        $currentUser = AuthenticationManager::getCurrentUser();

        // EditSelf-only scope: restrict to own family.
        // (Preserved as ABAC hook for future per-record holds — EditSelf+Notes users
        //  are not currently allowed past hasNoAdminPermissions() entry gate, but
        //  this check ensures correctness if that ever changes.)
        $personFamilyId = (int) $person->getFamId();
        if ($personFamilyId > 0 && !$currentUser->canViewFamily($personFamilyId)) {
            return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
        }

        $notes = NoteQuery::create()
            ->filterByPerId($person->getId())
            ->filterByType('note')
            ->orderByDateEntered('DESC')
            ->find();

        $result = [];
        foreach ($notes as $note) {
            // isVisibleTo() enforces: public→all, private→author+admin only.
            // Notes=1 non-admin non-author users get private notes filtered out
            // (returns 200 with subset, not 403).
            if ($note->isVisibleTo($currentUser)) {
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
     *     description="Creates a note on a person's record. Requires Notes=1 OR Admin, OR canEditPerson() (EditRecords/EditSelf-own-family). A plain Notes=1 user with no edit permissions cannot write person notes.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"text"},
     *             @OA\Property(property="text", type="string", description="HTML note content (Quill output)"),
     *             @OA\Property(property="private", type="boolean", description="Mark note as private (visible to admins and author only)", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Note created",
     *         @OA\JsonContent(@OA\Property(property="note", type="object"))
     *     ),
     *     @OA\Response(response=400, description="Note text is required"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Notes permission required or access denied"),
     *     @OA\Response(response=404, description="Person not found")
     * )
     */
    $group->post('/note', function (Request $request, Response $response, array $args): Response {
        /** @var Person $person */
        $person = $request->getAttribute('person');
        $currentUser = AuthenticationManager::getCurrentUser();
        $input = (array) $request->getParsedBody();

        // Write gate: Notes=1 OR Admin can write person notes (policy §3/#9036).
        // Additionally, EditRecords/EditSelf users with canEditPerson() can also
        // write notes on records they may edit — this covers the legacy case
        // where a user has EditRecords but not the Notes flag.
        // The primary gate is canReadNotes() (Notes=1 or Admin).
        $canWrite = $currentUser->canReadNotes()
            || $currentUser->canEditPerson((int) $person->getId(), (int) $person->getFamId());

        if (!$canWrite) {
            return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
        }

        $text = InputUtils::sanitizeHTML($input['text'] ?? '');
        if ($text === '') {
            return SlimUtils::renderErrorJSON($response, gettext('Note text is required'), [], 400);
        }

        $private = !empty($input['private']) ? 1 : 0;

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
     *     description="Returns all notes of type 'note' for the family, filtered by visibility for the current user. Requires Notes=1 or Admin. Private notes authored by others are excluded for Notes=1 users; admins see all including private.",
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
     *     @OA\Response(response=403, description="Notes read permission required"),
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
            // isVisibleTo() enforces: public→all, private→author+admin only.
            if ($note->isVisibleTo($currentUser)) {
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
     *     description="Creates a note on a family record. Requires Notes=1 or Admin. Cross-family writes are intentional (e.g. visitation teams adding notes on families they visit). See canWriteNoteOnFamily().",
     *     tags={"Families"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="familyId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"text"},
     *             @OA\Property(property="text", type="string", description="HTML note content (Quill output)"),
     *             @OA\Property(property="private", type="boolean", description="Mark note as private (visible to admins and author only)", example=false)
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

        // canWriteNoteOnFamily() enforces Notes=1/Admin; cross-family is intentional.
        if (!$currentUser->canWriteNoteOnFamily((int) $family->getId())) {
            return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
        }

        $text = InputUtils::sanitizeHTML($input['text'] ?? '');
        if ($text === '') {
            return SlimUtils::renderErrorJSON($response, gettext('Note text is required'), [], 400);
        }

        $private = !empty($input['private']) ? 1 : 0;

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
     *     description="Returns the note if it is visible to the current user. Admins see all notes including private. Notes=1 non-admin users see only public notes and their own private notes. Returns 404 for notes not visible to the current user (including private notes the caller cannot read — to avoid leaking note existence).",
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

        // isVisibleTo() implements: public→all, private→author+admin.
        // 404 (not 403) is intentional: avoids leaking that a private note exists.
        if (!$note->isVisibleTo($currentUser)) {
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

        $private = !empty($input['private']) ? 1 : 0;

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
     *     description="Deletes the note. Requires Notes permission. Only the note's author or an admin may delete it.",
     *     tags={"People"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="noteId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Note deleted",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Notes permission required or not note author/admin"),
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

        // Capture before deletion
        $perId    = (int) $note->getPerId();
        $famId    = (int) $note->getFamId();
        $authorId = $note->getEnteredBy();

        $authorPerson = PersonQuery::create()->findPk($authorId);
        $authorName   = $authorPerson !== null ? $authorPerson->getFullName() : gettext('Unknown');

        try {
            $note->delete();

            // Write a system timeline entry so the deletion is auditable
            $audit = new Note();
            if ($perId > 0) {
                $audit->setPerId($perId);
            }
            if ($famId > 0) {
                $audit->setFamId($famId);
            }
            $audit->setType('delete-note');
            $audit->setText(sprintf(gettext('Note by %s deleted'), $authorName));
            $audit->setEntered($currentUser->getId());
            $audit->save();

            return SlimUtils::renderJSON($response, ['success' => true]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete note'), [], 500, $e, $request);
        }
    });
})->add(NoteMiddleware::class)->add(NotesRoleAuthMiddleware::class);
