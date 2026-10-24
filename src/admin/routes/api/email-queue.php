<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\EmailQueueService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/email/queue', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Delete(
     *     path="/admin/api/email/queue",
     *     operationId="deleteEmailQueue",
     *     summary="Delete every queued composer message so it is never sent",
     *     description="Deletes the email history rows whose status is `queued`, in every batch. Messages being sent at that moment (`sending`) and the history of sent, failed and skipped mail are left alone. A composer still sending one of the deleted batches gets 404 for it. The deletion is written to the app log with the administrator's name.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="The queue was emptied",
     *         @OA\JsonContent(@OA\Property(property="deleted", type="integer", example=12, description="How many queued messages were deleted"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden — Admin role required")
     * )
     */
    $group->delete('', function (Request $request, Response $response): Response {
        try {
            $deleted = (new EmailQueueService())->deleteQueued(AuthenticationManager::getCurrentUser());

            return SlimUtils::renderJSON($response, ['deleted' => $deleted]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete the email queue'), [], 500, $e, $request);
        }
    });
});
