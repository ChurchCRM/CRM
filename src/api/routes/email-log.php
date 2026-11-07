<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Email history (email_log_eml): read-only. Rows are written by BaseEmail::send() and, for
 * queued composer messages, by EmailQueueService.
 *
 * @OA\Get(
 *     path="/email/log",
 *     summary="Page through the email history",
 *     description="Newest first. With personId or familyId the caller must be allowed to view that record. Without either it is the email log: an administrator sees everyone's mail, a user with the Email permission only the mail they queued, filtered by status, kind, sentBy and q.",
 *     tags={"Email"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="familyId", in="query", @OA\Schema(type="integer"), description="Rows for the family address and its current members"),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"sent","failed","skipped","queued","sending"}), description="Email log only"),
 *     @OA\Parameter(name="kind", in="query", @OA\Schema(type="string", maxLength=50), description="Email log only: one email type, e.g. composer or birthday"),
 *     @OA\Parameter(name="sentBy", in="query", @OA\Schema(type="string"), description="Email log only: a user id, or automatic for mail no user queued. A user without admin rights may pass only their own id."),
 *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string", maxLength=100), description="Email log only: every word must appear in the subject, the address or the recipient's name"),
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=25, maximum=100)),
 *     @OA\Response(response=200, description="A page of history rows",
 *         @OA\JsonContent(
 *             @OA\Property(property="rows", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="personId", type="integer", nullable=true),
 *                 @OA\Property(property="familyId", type="integer", nullable=true),
 *                 @OA\Property(property="sentByUserId", type="integer", nullable=true),
 *                 @OA\Property(property="sentBy", type="string", nullable=true, description="Name of the person who wrote a composer message"),
 *                 @OA\Property(property="address", type="string"),
 *                 @OA\Property(property="kind", type="string"),
 *                 @OA\Property(property="kindLabel", type="string"),
 *                 @OA\Property(property="subject", type="string"),
 *                 @OA\Property(property="status", type="string", enum={"sent","failed","skipped","queued","sending"}, description="queued: waiting to be sent (a retry keeps the last error); sending: a run is sending it now"),
 *                 @OA\Property(property="statusLabel", type="string"),
 *                 @OA\Property(property="attempts", type="integer", description="Delivery attempts so far"),
 *                 @OA\Property(property="error", type="string", nullable=true, description="The mail server's error; null for callers without admin rights"),
 *                 @OA\Property(property="dateSent", type="string"),
 *                 @OA\Property(property="hasBody", type="boolean"),
 *                 @OA\Property(property="recipientName", type="string", nullable=true, description="Email log only: the person, or else the family, the address belonged to"),
 *                 @OA\Property(property="recipientUrl", type="string", nullable=true, description="Email log only: that record's page"))),
 *             @OA\Property(property="total", type="integer"),
 *             @OA\Property(property="page", type="integer"),
 *             @OA\Property(property="limit", type="integer"),
 *             @OA\Property(property="pages", type="integer")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid status, kind, sentBy or q"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Not allowed to view this record's history, the Email permission is missing, or sentBy names another sender without admin rights")
 * )
 * @OA\Get(
 *     path="/email/log/{id}",
 *     summary="One email history row with its stored body",
 *     tags={"Email"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="The row; body is null when the email class does not store bodies (account emails)"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Not allowed to view this record's history"),
 *     @OA\Response(response=404, description="No such row")
 * )
 */
$app->group('/email/log', function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response): Response {
        try {
            $user = AuthenticationManager::getCurrentUser();
            $q = $request->getQueryParams();
            $personId = (int) ($q['personId'] ?? 0);
            $familyId = (int) ($q['familyId'] ?? 0);
            $page = (int) ($q['page'] ?? 1);
            $limit = (int) ($q['limit'] ?? EmailLogService::DEFAULT_PAGE_SIZE);
            $service = new EmailLogService();
            $forViewer = static fn (array $log): array => array_replace($log, [
                'rows' => array_map(static fn (array $row): array => $service->forViewer($row, $user->isAdmin()), $log['rows']),
            ]);

            if ($personId > 0) {
                if (!$user->canReadPerson($personId)) {
                    return SlimUtils::renderErrorJSON($response, gettext('Not allowed to view this person'), [], 403, null, $request);
                }

                return SlimUtils::renderJSON($response, $forViewer($service->getForPerson($personId, $page, $limit)));
            }
            if ($familyId > 0) {
                if (!$user->canReadFamily($familyId)) {
                    return SlimUtils::renderErrorJSON($response, gettext('Not allowed to view this family'), [], 403, null, $request);
                }
                $family = FamilyQuery::create()->findPk($familyId);
                $memberIds = [];
                if ($family !== null) {
                    foreach ($family->getPeople() as $member) {
                        $memberIds[] = (int) $member->getId();
                    }
                }

                return SlimUtils::renderJSON($response, $forViewer($service->getForFamily($familyId, $memberIds, $page, $limit)));
            }
            if (!$user->isEmailEnabled()) {
                return SlimUtils::renderErrorJSON($response, gettext('User must have Email permission'), [], 403, null, $request);
            }
            $text = static fn (string $name): string => is_scalar($q[$name] ?? null) ? (string) $q[$name] : '';
            $status = $text('status');
            if ($status !== '' && !in_array($status, EmailLogService::STATUSES, true)) {
                return SlimUtils::renderErrorJSON($response, gettext('Invalid status'), [], 400, null, $request);
            }
            $kind = $text('kind');
            if (mb_strlen($kind) > 50) {
                return SlimUtils::renderErrorJSON($response, gettext('Invalid type value'), [], 400, null, $request);
            }
            $sentBy = $text('sentBy');
            if ($sentBy !== '' && $sentBy !== EmailLogService::SENT_BY_AUTOMATIC && !ctype_digit($sentBy)) {
                return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, null, $request);
            }
            $search = trim($text('q'));
            if (mb_strlen($search) > EmailLogService::MAX_SEARCH_LENGTH) {
                return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400, null, $request);
            }
            $sentBy = ctype_digit($sentBy) ? (int) $sentBy : ($sentBy !== '' ? $sentBy : null);
            $senderUserId = $user->isAdmin() ? null : (int) $user->getId();
            if ($senderUserId !== null && $sentBy !== null && $sentBy !== $senderUserId) {
                return SlimUtils::renderErrorJSON($response, gettext('Administrator permission required'), [], 403, null, $request);
            }

            return SlimUtils::renderJSON($response, $forViewer($service->getLog(
                ['status' => $status, 'kind' => $kind, 'sentBy' => $sentBy, 'search' => $search],
                $senderUserId,
                $page,
                $limit,
            )));
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to load email history'), [], 500, $e, $request);
        }
    });

    $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $user = AuthenticationManager::getCurrentUser();
            $service = new EmailLogService();
            $row = $service->find((int) $args['id']);
            if ($row === null) {
                return SlimUtils::renderErrorJSON($response, gettext('Email not found'), [], 404, null, $request);
            }
            $allowed = $user->isAdmin();
            if (!$allowed && $row->getPerId() !== null) {
                $allowed = $user->canReadPerson((int) $row->getPerId());
            }
            if (!$allowed && $row->getFamId() !== null) {
                $allowed = $user->canReadFamily((int) $row->getFamId());
            }
            if (!$allowed) {
                return SlimUtils::renderErrorJSON($response, gettext('Not allowed to view this email'), [], 403, null, $request);
            }

            return SlimUtils::renderJSON($response, $service->forViewer($service->toArray($row, true), $user->isAdmin()));
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to load email'), [], 500, $e, $request);
        }
    });
});
