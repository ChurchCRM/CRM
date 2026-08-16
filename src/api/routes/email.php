<?php

use ChurchCRM\Emails\BulkEmail;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\EmailRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * @OA\Post(
 *     path="/email/send",
 *     summary="Send a bulk email to a list of recipients via the server-side SMTP configuration",
 *     tags={"Email"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"recipients","subject","body"},
 *             @OA\Property(property="recipients", type="array", @OA\Items(type="string"),
 *                 description="List of recipient email addresses"),
 *             @OA\Property(property="subject", type="string", description="Email subject line"),
 *             @OA\Property(property="body",    type="string", description="Plain-text email body"),
 *             @OA\Property(property="bcc",     type="boolean",
 *                 description="When true, recipients are placed in BCC instead of To (default false)")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Email sent successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="sent",    type="integer", description="Number of messages sent"),
 *             @OA\Property(property="failed",  type="integer", description="Number of messages that failed"),
 *             @OA\Property(property="errors",  type="array",   @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid or missing request data"),
 *     @OA\Response(response=422, description="SMTP is not configured — cannot send server-side"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Email permission required")
 * )
 */
$app->group('/email', function (RouteCollectorProxy $group): void {
    $group->post('/send', function (Request $request, Response $response): Response {
        // Guard: SMTP must be configured before we try to send.
        if (!SystemConfig::isEmailEnabled()) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Email sending is not configured. Please set up SMTP settings before sending.'),
                [],
                422,
                null,
                $request,
            );
        }

        $payload = $request->getParsedBody();

        // ── Validate required fields ──────────────────────────────────── //
        if (!isset($payload['recipients']) || !is_array($payload['recipients']) || count($payload['recipients']) === 0) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('recipients must be a non-empty array of email addresses'),
                [],
                400,
                null,
                $request,
            );
        }

        if (empty($payload['subject']) || !is_string($payload['subject'])) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('subject is required'),
                [],
                400,
                null,
                $request,
            );
        }

        if (!isset($payload['body']) || !is_string($payload['body'])) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('body is required'),
                [],
                400,
                null,
                $request,
            );
        }

        // ── Sanitise inputs ──────────────────────────────────────────── //
        $rawRecipients = $payload['recipients'];
        $recipients    = [];
        foreach ($rawRecipients as $addr) {
            if (!is_string($addr)) {
                continue;
            }
            $addr = trim($addr);
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $addr;
            }
        }

        if (count($recipients) === 0) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('No valid email addresses found in recipients list'),
                [],
                400,
                null,
                $request,
            );
        }

        $subject = trim($payload['subject']);
        $body    = trim($payload['body']);
        $bcc     = !empty($payload['bcc']) && $payload['bcc'] !== false && $payload['bcc'] !== 'false';

        // ── Limit batch size to guard against accidental spam ─────────── //
        $maxBatchSize = 500;
        if (count($recipients) > $maxBatchSize) {
            return SlimUtils::renderErrorJSON(
                $response,
                sprintf(gettext('Recipient count (%d) exceeds the maximum allowed per send (%d)'), count($recipients), $maxBatchSize),
                [],
                400,
                null,
                $request,
            );
        }

        // ── Send ─────────────────────────────────────────────────────── //
        try {
            $email = new BulkEmail($recipients, $subject, $body, $bcc);
            $sent  = $email->send() ? count($recipients) : 0;
            $errors = $sent === 0 ? [$email->getError()] : [];

            return SlimUtils::renderJSON($response, [
                'sent'    => $sent,
                'failed'  => count($recipients) - $sent,
                'errors'  => array_filter($errors),
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Failed to send email'),
                [],
                500,
                $e,
                $request,
            );
        }
    });
})->add(EmailRoleAuthMiddleware::class);
