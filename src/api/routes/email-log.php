<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Email history (email_log_eml): read-only. Rows are written only by BaseEmail::send().
 *
 * @OA\Get(
 *     path="/email/log",
 *     summary="Page through the email history",
 *     description="Newest first. With personId or familyId the caller must be allowed to view that record; without either the caller must be an administrator (recent sends across everyone).",
 *     tags={"Email"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="familyId", in="query", @OA\Schema(type="integer"), description="Rows for the family address and its current members"),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"sent","failed","skipped"}), description="Only honoured on the unscoped (admin) list"),
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=25, maximum=100)),
 *     @OA\Response(response=200, description="A page of history rows",
 *         @OA\JsonContent(
 *             @OA\Property(property="rows", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="personId", type="integer", nullable=true),
 *                 @OA\Property(property="familyId", type="integer", nullable=true),
 *                 @OA\Property(property="sentByUserId", type="integer", nullable=true),
 *                 @OA\Property(property="sentBy", type="string", nullable=true, description="Name of the user who wrote a composer message"),
 *                 @OA\Property(property="address", type="string"),
 *                 @OA\Property(property="kind", type="string"),
 *                 @OA\Property(property="kindLabel", type="string"),
 *                 @OA\Property(property="subject", type="string"),
 *                 @OA\Property(property="status", type="string", enum={"sent","failed","skipped"}),
 *                 @OA\Property(property="error", type="string", nullable=true),
 *                 @OA\Property(property="dateSent", type="string"),
 *                 @OA\Property(property="hasBody", type="boolean"))),
 *             @OA\Property(property="total", type="integer"),
 *             @OA\Property(property="page", type="integer"),
 *             @OA\Property(property="limit", type="integer"),
 *             @OA\Property(property="pages", type="integer")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Not allowed to view this record's history")
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

            if ($personId > 0) {
                if (!$user->canReadPerson($personId)) {
                    return SlimUtils::renderErrorJSON($response, gettext('Not allowed to view this person'), [], 403, null, $request);
                }

                return SlimUtils::renderJSON($response, $service->getForPerson($personId, $page, $limit));
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

                return SlimUtils::renderJSON($response, $service->getForFamily($familyId, $memberIds, $page, $limit));
            }
            if (!$user->isAdmin()) {
                return SlimUtils::renderErrorJSON($response, gettext('Administrator permission required'), [], 403, null, $request);
            }
            $status = isset($q['status']) ? (string) $q['status'] : null;
            if ($status !== null && !in_array($status, [EmailLogService::STATUS_SENT, EmailLogService::STATUS_FAILED, EmailLogService::STATUS_SKIPPED], true)) {
                return SlimUtils::renderErrorJSON($response, gettext('Invalid status'), [], 400, null, $request);
            }

            return SlimUtils::renderJSON($response, $service->getRecent($status, $page, $limit));
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

            return SlimUtils::renderJSON($response, $service->toArray($row, true));
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to load email'), [], 500, $e, $request);
        }
    });
});
