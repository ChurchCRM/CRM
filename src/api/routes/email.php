<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\EmailComposerService;
use ChurchCRM\Service\EmailQueueService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\EmailRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * @OA\Post(
 *     path="/email/send",
 *     summary="Queue a composer email, one message per recipient, for sending through the configured SMTP server",
 *     description="Recipients are given as person and family ids, never as addresses: the server resolves each id to its current email address, applies the do-not-email property, skips deceased people, inactive families and ids without an address, deduplicates addresses, and reports every skipped recipient by name. Each recipient receives their own message. Nothing is sent inside this request: every message is queued (email history status `queued`) under one batch id and the call returns at once. POST /email/batches/{batch}/send sends the batch now in bounded steps; whatever is left goes out with the timer jobs (cron, or the page-load fallback). `sent` and `failed` are always empty here and are kept for older clients; follow the batch for outcomes.",
 *     tags={"Email"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"subject","body"},
 *             @OA\Property(property="personIds", type="array", @OA\Items(type="integer"),
 *                 description="Person ids to email at their own address"),
 *             @OA\Property(property="familyIds", type="array", @OA\Items(type="integer"),
 *                 description="Family ids to email at the head of household's address, else the spouse's"),
 *             @OA\Property(property="subject", type="string", maxLength=255, description="Subject line (plain text)"),
 *             @OA\Property(property="body", type="string", description="Plain-text body; newlines are preserved")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Per-recipient outcome of the queueing",
 *         @OA\JsonContent(
 *             @OA\Property(property="batch", type="string", nullable=true, example="3f2a9c0d4b6e8f1a2c3d4e5f60718293", description="Batch id of the queued messages; null when nobody could be emailed"),
 *             @OA\Property(property="queued", type="array", description="Recipients whose message was queued",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="personId", type="integer", nullable=true),
 *                     @OA\Property(property="familyId", type="integer", nullable=true),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="email", type="string"))),
 *             @OA\Property(property="skipped", type="array", description="Requested recipients that were not emailed",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="personId", type="integer", nullable=true),
 *                     @OA\Property(property="familyId", type="integer", nullable=true),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="reason", type="string", enum={"not-found","no-email","no-adult-email","do-not-email","deceased","inactive","duplicate-address"}))),
 *             @OA\Property(property="sent", type="array", @OA\Items(type="object"), description="Always empty: sending happens after the request (kept for older clients)"),
 *             @OA\Property(property="failed", type="array", @OA\Items(type="object"), description="Always empty: sending happens after the request (kept for older clients)"),
 *             @OA\Property(property="counts", type="object",
 *                 @OA\Property(property="queued", type="integer"),
 *                 @OA\Property(property="sent", type="integer", description="Always 0"),
 *                 @OA\Property(property="skipped", type="integer"),
 *                 @OA\Property(property="failed", type="integer", description="Always 0"))
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid request: raw addresses, no ids, bad ids, more than 500 ids, or empty subject/body"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Email permission required"),
 *     @OA\Response(response=422, description="Email sending is disabled or SMTP is not configured")
 * )
 *
 * @OA\Schema(
 *     schema="EmailBatchProgress",
 *     @OA\Property(property="batch", type="string"),
 *     @OA\Property(property="total", type="integer", description="Messages in the batch; queued messages an administrator deleted are no longer counted"),
 *     @OA\Property(property="queued", type="integer", description="Waiting to be sent, due now or waiting for a retry"),
 *     @OA\Property(property="sending", type="integer", description="Being sent by a run right now"),
 *     @OA\Property(property="sent", type="integer", description="Accepted by the SMTP server (acceptance is not delivery)"),
 *     @OA\Property(property="failed", type="integer", description="Refused, or still failing after the last attempt"),
 *     @OA\Property(property="skipped", type="integer", description="Email was switched off when the message's turn came"),
 *     @OA\Property(property="due", type="integer", description="Queued messages that may be sent now"),
 *     @OA\Property(property="nextAttempt", type="string", nullable=true, example="2026-10-14 10:25:00", description="When the earliest retry is due"),
 *     @OA\Property(property="done", type="boolean", description="Nothing is queued or being sent"),
 *     @OA\Property(property="failures", type="array", description="The failed messages and the server's answer",
 *         @OA\Items(type="object",
 *             @OA\Property(property="address", type="string"),
 *             @OA\Property(property="error", type="string")))
 * )
 *
 * @OA\Get(
 *     path="/email/batches/{batch}",
 *     summary="Progress of a queued composer send",
 *     description="Counts of the batch's messages by email history status. Only the user who queued the batch, or an administrator, may read it.",
 *     tags={"Email"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="batch", in="path", required=true, @OA\Schema(type="string", pattern="^[0-9a-f]{32}$")),
 *     @OA\Response(response=200, description="Batch progress", @OA\JsonContent(ref="#/components/schemas/EmailBatchProgress")),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Email permission required, or the batch belongs to another user"),
 *     @OA\Response(response=404, description="No such batch, or every message in it was still queued when an administrator deleted the queue")
 * )
 *
 * @OA\Post(
 *     path="/email/batches/{batch}/send",
 *     summary="Send the due messages of a queued composer send now",
 *     description="Sends the batch's due messages for up to 15 seconds or 100 messages, then returns its progress. The composer calls it until nothing is due; messages waiting for a retry, and anything left when the composer closes, go out with the timer jobs. Concurrent calls and timer-job runs never send the same message twice. Only the user who queued the batch, or an administrator, may call it.",
 *     tags={"Email"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="batch", in="path", required=true, @OA\Schema(type="string", pattern="^[0-9a-f]{32}$")),
 *     @OA\Response(response=200, description="Batch progress after this step", @OA\JsonContent(ref="#/components/schemas/EmailBatchProgress")),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Email permission required, or the batch belongs to another user"),
 *     @OA\Response(response=404, description="No such batch, or every message in it was still queued when an administrator deleted the queue"),
 *     @OA\Response(response=422, description="Email sending is disabled or SMTP is not configured")
 * )
 */
$app->group('/email', function (RouteCollectorProxy $group): void {
    $group->post('/send', function (Request $request, Response $response): Response {
        // Same check BaseEmail::send() applies, so the API never claims a send it would skip.
        if (!SystemConfig::isEmailEnabled()) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Email sending is not enabled. Configure SMTP settings and enable email before sending.'),
                [],
                422,
                null,
                $request,
            );
        }

        $payload = (array) $request->getParsedBody();

        // Raw addresses are refused on purpose: the server resolves ids so every send is
        // attributable to a record and the church SMTP relay cannot be used as an open relay.
        if (array_key_exists('recipients', $payload)) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Recipients must be given as personIds and familyIds, not as email addresses'),
                [],
                400,
                null,
                $request,
            );
        }

        $rawPersonIds = $payload['personIds'] ?? [];
        $rawFamilyIds = $payload['familyIds'] ?? [];
        $isPositiveInt = static fn ($id): bool => (is_int($id) && $id > 0) || (is_string($id) && ctype_digit($id) && (int) $id > 0);
        if (!is_array($rawPersonIds) || !is_array($rawFamilyIds)
            || array_filter($rawPersonIds, $isPositiveInt) !== $rawPersonIds
            || array_filter($rawFamilyIds, $isPositiveInt) !== $rawFamilyIds
        ) {
            return SlimUtils::renderErrorJSON($response, gettext('personIds and familyIds must be arrays of positive integers'), [], 400, null, $request);
        }
        // Duplicate ids in the request collapse to one recipient.
        $personIds = EmailComposerService::normalizeIds($rawPersonIds);
        $familyIds = EmailComposerService::normalizeIds($rawFamilyIds);
        if ($personIds === [] && $familyIds === []) {
            return SlimUtils::renderErrorJSON($response, gettext('At least one personId or familyId is required'), [], 400, null, $request);
        }
        if (count($personIds) + count($familyIds) > EmailComposerService::MAX_RECIPIENTS) {
            return SlimUtils::renderErrorJSON(
                $response,
                sprintf(gettext('Recipient count (%d) exceeds the maximum allowed per send (%d)'), count($personIds) + count($familyIds), EmailComposerService::MAX_RECIPIENTS),
                [],
                400,
                null,
                $request,
            );
        }

        // subject and body were sanitized (tags stripped, trimmed) by InputSanitizationMiddleware.
        $subject = (string) ($payload['subject'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        if ($subject === '') {
            return SlimUtils::renderErrorJSON($response, gettext('subject is required'), [], 400, null, $request);
        }
        if (mb_strlen($subject) > 255) {
            return SlimUtils::renderErrorJSON($response, gettext('subject must be 255 characters or fewer'), [], 400, null, $request);
        }
        if ($body === '') {
            return SlimUtils::renderErrorJSON($response, gettext('body is required'), [], 400, null, $request);
        }

        try {
            $service = new EmailComposerService();
            $resolved = $service->resolveRecipients($personIds, $familyIds);
            $queued = $resolved['recipients'];
            $batch = $queued !== []
                ? $service->queue($queued, $subject, $body, AuthenticationManager::getCurrentUser())
                : null;

            return SlimUtils::renderJSON($response, [
                'batch'   => $batch,
                'queued'  => $queued,
                'skipped' => $resolved['skipped'],
                'sent'    => [],
                'failed'  => [],
                'counts'  => [
                    'queued'  => count($queued),
                    'sent'    => 0,
                    'skipped' => count($resolved['skipped']),
                    'failed'  => 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to queue the email'), [], 500, $e, $request);
        }
    })->add(new InputSanitizationMiddleware(['subject' => 'text', 'body' => 'text']));

    $group->get('/batches/{batch:[0-9a-f]{32}}', function (Request $request, Response $response, array $args): Response {
        $denied = emailBatchAccessError($request, $response, $args['batch']);
        if ($denied !== null) {
            return $denied;
        }

        return SlimUtils::renderJSON($response, (new EmailQueueService())->getBatchProgress($args['batch']));
    });

    $group->post('/batches/{batch:[0-9a-f]{32}}/send', function (Request $request, Response $response, array $args): Response {
        $denied = emailBatchAccessError($request, $response, $args['batch']);
        if ($denied !== null) {
            return $denied;
        }
        if (!SystemConfig::isEmailEnabled()) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Email sending is not enabled. Configure SMTP settings and enable email before sending.'),
                [],
                422,
                null,
                $request,
            );
        }

        try {
            $queue = new EmailQueueService();
            $queue->drain($args['batch'], EmailQueueService::WEB_MAX_SECONDS, EmailQueueService::WEB_MAX_MESSAGES);

            return SlimUtils::renderJSON($response, $queue->getBatchProgress($args['batch']));
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to send email'), [], 500, $e, $request);
        }
    });

    /**
     * @OA\Post(
     *     path="/email/preview",
     *     summary="Render a composer message for its first recipient without sending it",
     *     tags={"Email"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"subject","body"},
     *         @OA\Property(property="personIds", type="array", @OA\Items(type="integer")),
     *         @OA\Property(property="familyIds", type="array", @OA\Items(type="integer")),
     *         @OA\Property(property="subject", type="string"),
     *         @OA\Property(property="body", type="string"))),
     *     @OA\Response(response=200, description="The rendered HTML and who it is addressed to",
     *         @OA\JsonContent(
     *             @OA\Property(property="html", type="string"),
     *             @OA\Property(property="recipient", type="object",
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string")),
     *             @OA\Property(property="recipientCount", type="integer", description="How many recipients the send would reach"))),
     *     @OA\Response(response=400, description="No resolvable recipient, or empty subject/body"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Email permission required")
     * )
     */
    $group->post('/preview', function (Request $request, Response $response): Response {
        $payload = (array) $request->getParsedBody();
        $isPositiveInt = static fn ($id): bool => (is_int($id) && $id > 0) || (is_string($id) && ctype_digit($id) && (int) $id > 0);
        $rawPersonIds = $payload['personIds'] ?? [];
        $rawFamilyIds = $payload['familyIds'] ?? [];
        if (!is_array($rawPersonIds) || !is_array($rawFamilyIds)
            || array_filter($rawPersonIds, $isPositiveInt) !== $rawPersonIds
            || array_filter($rawFamilyIds, $isPositiveInt) !== $rawFamilyIds
        ) {
            return SlimUtils::renderErrorJSON($response, gettext('personIds and familyIds must be arrays of positive integers'), [], 400, null, $request);
        }
        $subject = (string) ($payload['subject'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        if ($subject === '' || $body === '') {
            return SlimUtils::renderErrorJSON($response, gettext('subject and body are required'), [], 400, null, $request);
        }
        try {
            $service = new EmailComposerService();
            $resolved = $service->resolveRecipients(
                EmailComposerService::normalizeIds($rawPersonIds),
                EmailComposerService::normalizeIds($rawFamilyIds),
            );
            if ($resolved['recipients'] === []) {
                return SlimUtils::renderErrorJSON($response, gettext('None of the recipients can be emailed'), ['skipped' => $resolved['skipped']], 400, null, $request);
            }
            $first = $resolved['recipients'][0];

            return SlimUtils::renderJSON($response, [
                'html'           => $service->preview($first, $subject, $body),
                'recipient'      => ['name' => $first['name'], 'email' => $first['email']],
                'recipientCount' => count($resolved['recipients']),
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to build the preview'), [], 500, $e, $request);
        }
    })->add(new InputSanitizationMiddleware(['subject' => 'text', 'body' => 'text']));
})->add(EmailRoleAuthMiddleware::class);

/** 404 for an unknown batch, 403 for someone else's (administrators may follow any batch), null when allowed. */
function emailBatchAccessError(Request $request, Response $response, string $batch): ?Response
{
    $first = (new EmailQueueService())->findBatch($batch);
    if ($first === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Email batch not found. If it was still queued, an administrator deleted the queue and it will not be sent.'), [], 404, null, $request);
    }
    $user = AuthenticationManager::getCurrentUser();
    if (!$user->isAdmin() && (int) $first->getUsrId() !== (int) $user->getId()) {
        return SlimUtils::renderErrorJSON($response, gettext('This email batch was queued by another user'), [], 403, null, $request);
    }

    return null;
}
