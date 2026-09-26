<?php

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalApiMiddleware;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/*
 * `/api/portal/me/emails` — the member's own email history, the read side of
 * the history #9877 writes (design P11).
 *
 * Three properties hold here, and they are why the portal does not reuse
 * `/api/email/log`:
 *
 *  - **The actor is the session.** The staff endpoint takes a `personId` and
 *    checks it against the caller's permissions; this one takes no id at all,
 *    so pointing it at somebody else is not a permission failure, it is
 *    unexpressible. `PortalApiMiddleware` resolves the acting person.
 *  - **Session only.** An API key is refused outright by the same middleware,
 *    for the same reason every other portal route refuses one.
 *  - **A foreign row is 404, never 403.** A 403 would confirm that an id names
 *    a real email; a member must not be able to enumerate the church's sends.
 *    A missing id and somebody else's id are the same answer — the treatment
 *    `GET /api/portal/family/members/{personId}/photo` already gives a person
 *    id outside the member's family (design P12).
 *
 * **Scope decision — the family's shared address is not included.** A row is
 * shown only when `eml_per_ID` is this person. An email addressed to the
 * family's own address lands in `email_log_eml` with `eml_per_ID` NULL and
 * `eml_fam_ID` set (see `EmailLogService::resolveAddress()`), and this first
 * version deliberately leaves those out: every adult of a household would
 * otherwise see mail that was not addressed to them by name, which is a
 * privacy call for the product owner rather than an implementation detail.
 * `EmailLogService::getForFamily()` is what a later version would call, once
 * that decision is made; the staff page already uses it.
 */

/** The acting person, put on the request by PortalApiMiddleware. */
$portalEmailActor = static function (Request $request): Person {
    /** @var Person $person */
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);

    return $person;
};

/**
 * A positive integer from a query string, or the fallback. Anything that is
 * not a whole number greater than zero — a word, a negative, an empty string —
 * is the fallback rather than a 400: a member who edits the URL gets the first
 * page, not an error page.
 */
$portalEmailInt = static function (array $query, string $key, int $fallback): int {
    $raw = $query[$key] ?? null;
    if (!is_string($raw) && !is_int($raw)) {
        return $fallback;
    }
    $value = filter_var($raw, FILTER_VALIDATE_INT);

    return ($value === false || $value < 1) ? $fallback : $value;
};

$app->group('/portal/me/emails', function (RouteCollectorProxy $group) use ($portalEmailActor, $portalEmailInt): void {
    /**
     * @OA\Get(
     *     path="/portal/me/emails",
     *     operationId="getPortalMyEmails",
     *     summary="A page of the signed-in member's own email history",
     *     description="Newest first, in the same envelope GET /email/log answers with, but scoped to the acting member: only rows whose eml_per_ID is this person. There is no person id to pass. Rows never carry the body; GET /portal/me/emails/{id} does. Session only — API keys are refused.",
     *     tags={"Member Portal"},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=25, maximum=100)),
     *     @OA\Response(response=200, description="A page of the member's history",
     *         @OA\JsonContent(
     *             @OA\Property(property="rows", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="personId", type="integer", nullable=true),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="kind", type="string"),
     *                 @OA\Property(property="kindLabel", type="string"),
     *                 @OA\Property(property="subject", type="string"),
     *                 @OA\Property(property="status", type="string", enum={"sent","failed","skipped"}),
     *                 @OA\Property(property="dateSent", type="string"),
     *                 @OA\Property(property="hasBody", type="boolean"))),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="page", type="integer"),
     *             @OA\Property(property="limit", type="integer"),
     *             @OA\Property(property="pages", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No signed-in session"),
     *     @OA\Response(response=403, description="API-key caller, or an account with no person record")
     * )
     */
    $group->get('', function (Request $request, Response $response) use ($portalEmailActor, $portalEmailInt): Response {
        $query = $request->getQueryParams();
        $page = $portalEmailInt($query, 'page', 1);
        $limit = $portalEmailInt($query, 'limit', EmailLogService::DEFAULT_PAGE_SIZE);
        $limit = min($limit, EmailLogService::MAX_PAGE_SIZE);

        return SlimUtils::renderJSON(
            $response,
            (new EmailLogService())->getForPerson((int) $portalEmailActor($request)->getId(), $page, $limit)
        );
    });

    /**
     * @OA\Get(
     *     path="/portal/me/emails/{id}",
     *     operationId="getPortalMyEmail",
     *     summary="One email from the signed-in member's own history, with its body",
     *     description="The row is returned only when it was addressed to the acting member. Somebody else's row and an id that does not exist are the same 404, so a member cannot learn that an email exists by asking for it. The body is null for the email classes that never store one (account emails carrying a password or a one-time link).",
     *     tags={"Member Portal"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="The row, with its stored body"),
     *     @OA\Response(response=401, description="No signed-in session"),
     *     @OA\Response(response=403, description="API-key caller, or an account with no person record"),
     *     @OA\Response(response=404, description="No such email in this member's history")
     * )
     */
    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($portalEmailActor): Response {
        $service = new EmailLogService();
        $row = $service->find((int) $args['id']);

        if ($row === null || (int) $row->getPerId() !== (int) $portalEmailActor($request)->getId()) {
            return SlimUtils::renderErrorJSON($response, gettext('Email not found'), [], 404);
        }

        return SlimUtils::renderJSON($response, $service->toArray($row, true));
    });
})->add(new PortalApiMiddleware());
