<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\EmailComposerService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\EmailRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * @OA\Post(
 *     path="/email/send",
 *     summary="Send a composer email, one message per recipient, through the configured SMTP server",
 *     description="Recipients are given as person and family ids, never as addresses: the server resolves each id to its current email address, applies the do-not-email property, skips deceased people, inactive families and ids without an address, deduplicates addresses, and reports every skipped recipient by name. Each recipient receives their own message.",
 *     tags={"Email"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"subject","body"},
 *             @OA\Property(property="personIds", type="array", @OA\Items(type="integer"),
 *                 description="Person ids to email at their primary address (falls back to the family address)"),
 *             @OA\Property(property="familyIds", type="array", @OA\Items(type="integer"),
 *                 description="Family ids to email at the family address"),
 *             @OA\Property(property="subject", type="string", maxLength=255, description="Subject line (plain text)"),
 *             @OA\Property(property="body", type="string", description="Plain-text body; newlines are preserved")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Per-recipient outcome",
 *         @OA\JsonContent(
 *             @OA\Property(property="sent", type="array", description="Recipients whose message the SMTP server accepted (acceptance is not delivery)",
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
 *                     @OA\Property(property="reason", type="string", enum={"not-found","no-email","do-not-email","deceased","inactive","duplicate-address"}))),
 *             @OA\Property(property="failed", type="array", description="Recipients the SMTP server rejected",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="personId", type="integer", nullable=true),
 *                     @OA\Property(property="familyId", type="integer", nullable=true),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="email", type="string"),
 *                     @OA\Property(property="error", type="string"))),
 *             @OA\Property(property="counts", type="object",
 *                 @OA\Property(property="sent", type="integer"),
 *                 @OA\Property(property="skipped", type="integer"),
 *                 @OA\Property(property="failed", type="integer"))
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid request: raw addresses, no ids, bad ids, more than 500 ids, or empty subject/body"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Email permission required"),
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
            $result = $service->send(
                $resolved['recipients'],
                $subject,
                $body,
                AuthenticationManager::getCurrentUser()->getUserName(),
            );

            return SlimUtils::renderJSON($response, [
                'sent'    => $result['sent'],
                'skipped' => $resolved['skipped'],
                'failed'  => $result['failed'],
                'counts'  => [
                    'sent'    => count($result['sent']),
                    'skipped' => count($resolved['skipped']),
                    'failed'  => count($result['failed']),
                ],
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to send email'), [], 500, $e, $request);
        }
    })->add(new InputSanitizationMiddleware(['subject' => 'text', 'body' => 'text']));
})->add(EmailRoleAuthMiddleware::class);
