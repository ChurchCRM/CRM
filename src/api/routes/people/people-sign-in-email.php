<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\MemberSignInListService;
use ChurchCRM\Service\SignInEmailFixService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// "Email needs attention" for staff with Edit Records (member sign-in design
// §4, §9, §11.2): the list, Fix shared emails and Confirm selected. Each row
// is checked again on the server against the caller's permissions.
$app->group('/persons/sign-in-email', function (RouteCollectorProxy $group): void {
    $group->get('/attention', 'getSignInEmailAttentionAPI');
    $group->get('/attention/counts', 'getSignInEmailAttentionCountsAPI');
    $group->get('/shared', 'getSharedSignInEmailsAPI');
    $group->post('/shared/fix', 'fixSharedSignInEmailsAPI')->add(new CSRFMiddleware());
    $group->post('/confirm', 'confirmSignInEmailsAPI')->add(new CSRFMiddleware());
})->add(new EditRecordsRoleAuthMiddleware());

/**
 * @OA\Get(
 *     path="/persons/sign-in-email/attention",
 *     operationId="getSignInEmailAttention",
 *     summary="One page of Email needs attention: people the Member Portal sign-in rules let in whose email stops them (EditRecords role required)",
 *     description="Each row has the problem (no_email, shared_email or email_unconfirmed), who else shares a shared address, and for an unconfirmed one whether the caller may confirm it (canConfirmEmail, confirmEmailRefusal).",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="start", in="query", @OA\Schema(type="integer", default=0)),
 *     @OA\Parameter(name="length", in="query", @OA\Schema(type="integer", default=25, maximum=100)),
 *     @OA\Parameter(name="search", in="query", description="Part of a name or email", @OA\Schema(type="string")),
 *     @OA\Parameter(name="problem", in="query", @OA\Schema(type="string", enum={"no_email", "shared_email", "email_unconfirmed"})),
 *     @OA\Parameter(name="kind", in="query", description="With problem=email_unconfirmed: moved from the family email, or a replacement after the first sign-in", @OA\Schema(type="string", enum={"family_email", "replacement"})),
 *     @OA\Response(response=200, description="The page", @OA\JsonContent(
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="filtered", type="integer"),
 *         @OA\Property(property="rows", type="array", @OA\Items(type="object"))
 *     )),
 *     @OA\Response(response=400, description="An unknown problem or kind"),
 *     @OA\Response(response=403, description="EditRecords role required")
 * )
 */
function getSignInEmailAttentionAPI(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    $text = static fn (string $name): string => is_string($params[$name] ?? null) ? $params[$name] : '';

    try {
        $page = (new MemberSignInListService(null, AuthenticationManager::getCurrentUser()->isAdmin()))->page(
            MemberSignInListService::EMAIL_ATTENTION,
            (int) ($params['start'] ?? 0),
            (int) ($params['length'] ?? MemberSignInListService::DEFAULT_PAGE_SIZE),
            $text('search'),
            $text('problem'),
            $text('kind'),
        );
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, $page);
}

/**
 * @OA\Get(
 *     path="/persons/sign-in-email/attention/counts",
 *     operationId="getSignInEmailAttentionCounts",
 *     summary="How many people Email needs attention lists per problem, and the shared addresses behind them (EditRecords role required)",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="The counts", @OA\JsonContent(
 *         @OA\Property(property="emailProblems", type="object", example={"no_email": 3, "shared_email": 4, "email_unconfirmed": 1}),
 *         @OA\Property(property="sharedAddresses", type="integer", example=2),
 *         @OA\Property(property="unconfirmedKinds", type="object", example={"family_email": 1, "replacement": 0})
 *     )),
 *     @OA\Response(response=403, description="EditRecords role required")
 * )
 */
function getSignInEmailAttentionCountsAPI(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, (new MemberSignInListService())->emailAttentionCounts());
}

/**
 * @OA\Get(
 *     path="/persons/sign-in-email/shared",
 *     operationId="getSharedSignInEmails",
 *     summary="Fix shared emails preview: every shared personal email that stops sign-in, with who keeps it (EditRecords role required)",
 *     description="status fix: everyone sharing it is in one family; the head of household among them keeps it, else the spouse, the oldest first, and the people in losers would have it cleared. status by_hand: shared across families or with no head of household or spouse among them; the tool never changes these. canApply is false, with refusal, where the caller may not apply the row: only an administrator may fix a row in which anyone has a staff account or given portal permissions.",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="The preview", @OA\JsonContent(@OA\Property(property="rows", type="array", @OA\Items(type="object",
 *         @OA\Property(property="email", type="string"),
 *         @OA\Property(property="status", type="string", enum={"fix", "by_hand"}),
 *         @OA\Property(property="reason", type="string", nullable=true, enum={"families", "no_adult"}),
 *         @OA\Property(property="familyId", type="integer", nullable=true),
 *         @OA\Property(property="familyName", type="string", nullable=true),
 *         @OA\Property(property="keeper", type="object", nullable=true),
 *         @OA\Property(property="losers", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="people", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="needsAdministrator", type="boolean"),
 *         @OA\Property(property="canApply", type="boolean"),
 *         @OA\Property(property="refusal", type="string", nullable=true)
 *     )))),
 *     @OA\Response(response=403, description="EditRecords role required")
 * )
 */
function getSharedSignInEmailsAPI(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, ['rows' => (new SignInEmailFixService())->sharedEmailPlan(AuthenticationManager::getCurrentUser())]);
}

/**
 * @OA\Post(
 *     path="/persons/sign-in-email/shared/fix",
 *     operationId="fixSharedSignInEmails",
 *     summary="Fix shared emails: clear the address from everyone but its keeper, for the chosen rows (EditRecords role required)",
 *     description="Each row repeats the address, keeper and losers the preview showed. A row whose sharers changed since is skipped; a by-hand row, or one the caller may not apply, is refused. Each cleared address is a staff email change (member sign-in design §9): audited as email_changed via shared_email_fix and, for a person who has signed in, their sessions and remembered devices end and the old address gets a notice.",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"rows"}, @OA\Property(property="rows", type="array", @OA\Items(type="object",
 *         required={"email", "keeperId", "loserIds"},
 *         @OA\Property(property="email", type="string", example="pat.and.sam@example.com"),
 *         @OA\Property(property="keeperId", type="integer", example=12),
 *         @OA\Property(property="loserIds", type="array", @OA\Items(type="integer"), example={13})
 *     )))),
 *     @OA\Response(response=200, description="What happened to each row", @OA\JsonContent(
 *         @OA\Property(property="changed", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="skipped", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="refused", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="peopleChanged", type="integer")
 *     )),
 *     @OA\Response(response=400, description="No rows, too many rows, or a malformed row"),
 *     @OA\Response(response=403, description="EditRecords role required or an invalid CSRF token")
 * )
 */
function fixSharedSignInEmailsAPI(Request $request, Response $response): Response
{
    $rows = signInEmailRows($request, static fn (array $row): ?array => is_string($row['email'] ?? null)
        && is_int($row['keeperId'] ?? null)
        && is_array($row['loserIds'] ?? null)
        && array_filter($row['loserIds'], static fn ($id): bool => !is_int($id)) === []
            ? ['email' => $row['email'], 'keeperId' => $row['keeperId'], 'loserIds' => $row['loserIds']]
            : null);
    if (is_string($rows)) {
        return SlimUtils::renderErrorJSON($response, $rows, [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, (new SignInEmailFixService())->fixSharedEmails($rows, AuthenticationManager::getCurrentUser()));
}

/**
 * @OA\Post(
 *     path="/persons/sign-in-email/confirm",
 *     operationId="confirmSignInEmails",
 *     summary="Confirm selected: confirm many unconfirmed emails after checking who each person is (EditRecords role required)",
 *     description="Each row goes through the same check as POST /person/{id}/sign-in/confirm-email: staff with Edit Records may confirm an address moved from the family email; a replacement address after the first sign-in, and any address of a person with a staff account or given portal permissions, only an administrator. Audited as email_confirmed via bulk_confirm.",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"rows"}, @OA\Property(property="rows", type="array", @OA\Items(type="object",
 *         required={"personId", "email"},
 *         @OA\Property(property="personId", type="integer", example=42),
 *         @OA\Property(property="email", type="string", description="The unconfirmed address being confirmed", example="pat@example.com")
 *     )))),
 *     @OA\Response(response=200, description="What happened to each row", @OA\JsonContent(
 *         @OA\Property(property="confirmed", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="skipped", type="array", description="Nothing to confirm, or the address changed", @OA\Items(type="object")),
 *         @OA\Property(property="refused", type="array", description="The caller may not confirm it", @OA\Items(type="object"))
 *     )),
 *     @OA\Response(response=400, description="No rows, too many rows, or a malformed row"),
 *     @OA\Response(response=403, description="EditRecords role required or an invalid CSRF token")
 * )
 */
function confirmSignInEmailsAPI(Request $request, Response $response): Response
{
    $rows = signInEmailRows($request, static fn (array $row): ?array => is_int($row['personId'] ?? null) && is_string($row['email'] ?? null)
        ? ['personId' => $row['personId'], 'email' => $row['email']]
        : null);
    if (is_string($rows)) {
        return SlimUtils::renderErrorJSON($response, $rows, [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, (new SignInEmailFixService())->confirmEmails($rows, AuthenticationManager::getCurrentUser()));
}

/**
 * The request's `rows`, each passed through `$shape`, or why they can't be used.
 *
 * @param callable(array<string, mixed>): ?array<string, mixed> $shape
 *
 * @return list<array<string, mixed>>|string
 */
function signInEmailRows(Request $request, callable $shape): array|string
{
    $body = $request->getParsedBody();
    $rows = is_array($body) ? ($body['rows'] ?? null) : null;
    if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
        return gettext('Choose at least one row.');
    }
    if (count($rows) > SignInEmailFixService::MAX_ROWS) {
        return sprintf(gettext('Choose at most %d rows at a time.'), SignInEmailFixService::MAX_ROWS);
    }

    $shaped = [];
    foreach ($rows as $row) {
        $valid = is_array($row) ? $shape($row) : null;
        if ($valid === null) {
            return gettext('A row is not in the expected form.');
        }
        $shaped[] = $valid;
    }

    return $shaped;
}
