<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\data\GradePresets;
use ChurchCRM\Service\GradePresetChangedException;
use ChurchCRM\Service\GradePromotionService;
use ChurchCRM\Service\GradeService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/grades', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/admin/api/grades/promotion",
     *     operationId="getGradePromotionStatus",
     *     summary="Get the yearly grade promotion settings, last run and preview",
     *     description="The promotion month and day, the next automatic promotion date, the last promotion marker and result, and how many people a promotion would move up or clear from the last grade.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Promotion status",
     *         @OA\JsonContent(
     *             @OA\Property(property="month", type="integer", description="1-12, or 0 when automatic promotion is off", example=6),
     *             @OA\Property(property="day", type="integer", example=1),
     *             @OA\Property(property="nextDate", type="string", format="date", nullable=true, example="2027-06-01"),
     *             @OA\Property(property="nextDateText", type="string", example="June 01 2027"),
     *             @OA\Property(property="lastAt", type="string", description="Last promotion marker; send it back as expectedLastAt", example="2026-06-01 00:05:12.123456"),
     *             @OA\Property(property="lastRunText", type="string", nullable=true),
     *             @OA\Property(property="promotedThisYear", type="boolean"),
     *             @OA\Property(property="lastResult", type="object", nullable=true,
     *                 @OA\Property(property="ranAt", type="string"),
     *                 @OA\Property(property="trigger", type="string", enum={"automatic", "manual"}),
     *                 @OA\Property(property="userId", type="integer"),
     *                 @OA\Property(property="moved", type="integer"),
     *                 @OA\Property(property="finished", type="integer")
     *             ),
     *             @OA\Property(property="preview", type="object",
     *                 @OA\Property(property="moved", type="integer"),
     *                 @OA\Property(property="finished", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->get('/promotion', function (Request $request, Response $response): Response {
        return SlimUtils::renderJSON($response, GradePromotionService::status());
    });

    /**
     * @OA\Post(
     *     path="/admin/api/grades/promotion",
     *     operationId="promoteGradesNow",
     *     summary="Move everyone with a grade up one grade now",
     *     description="Counts as this year's promotion. The last grade is cleared, deceased people are skipped and each moved person gets a timeline note. A browser session must send the CSRF token in X-CSRF-Token; API-key calls do not.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(@OA\Property(property="expectedLastAt", type="string", description="The lastAt the caller was shown; empty when grades were never promoted", example=""))
     *     ),
     *     @OA\Response(response=200, description="Promotion result and the new status",
     *         @OA\JsonContent(
     *             @OA\Property(property="result", type="object",
     *                 @OA\Property(property="ranAt", type="string"),
     *                 @OA\Property(property="trigger", type="string", example="manual"),
     *                 @OA\Property(property="userId", type="integer"),
     *                 @OA\Property(property="moved", type="integer"),
     *                 @OA\Property(property="finished", type="integer")
     *             ),
     *             @OA\Property(property="status", type="object", description="Same shape as GET /admin/api/grades/promotion")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required, or the CSRF token is missing"),
     *     @OA\Response(response=409, description="Grades were promoted after the caller's expectedLastAt")
     * )
     */
    $group->post('/promotion', function (Request $request, Response $response): Response {
        $input = (array) $request->getParsedBody();
        $result = GradePromotionService::promoteNow(
            (int) AuthenticationManager::getCurrentUser()->getId(),
            (string) ($input['expectedLastAt'] ?? '')
        );
        if ($result === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Grades were promoted by someone else in the meantime. Reload the page to see the new grades.'), [], 409);
        }

        return SlimUtils::renderJSON($response, ['result' => $result, 'status' => GradePromotionService::status()]);
    });

    /**
     * @OA\Get(
     *     path="/admin/api/grades/presets",
     *     operationId="listGradePresets",
     *     summary="List the grade names available for each country",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Countries and their grade names",
     *         @OA\JsonContent(
     *             @OA\Property(property="current", type="string", description="Key of the names last applied", example="us"),
     *             @OA\Property(property="presets", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="key", type="string", example="england-wales"),
     *                 @OA\Property(property="name", type="string", example="England and Wales"),
     *                 @OA\Property(property="grades", type="array", @OA\Items(type="string", example="Year 1"))
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->get('/presets', function (Request $request, Response $response): Response {
        $presets = [];
        foreach (GradePresets::all() as $key => $preset) {
            $presets[] = ['key' => $key, 'name' => $preset['name'], 'grades' => array_values($preset['grades'])];
        }

        return SlimUtils::renderJSON($response, ['current' => GradeService::currentPresetKey(), 'presets' => $presets]);
    });

    /**
     * @OA\Get(
     *     path="/admin/api/grades/presets/{key}/preview",
     *     operationId="previewGradePreset",
     *     summary="Preview what applying a country's grade names would change",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="key", in="path", required=true, @OA\Schema(type="string"), example="england-wales"),
     *     @OA\Response(response=200, description="The planned changes and the state token to apply them",
     *         @OA\JsonContent(
     *             @OA\Property(property="preset", type="string", example="england-wales"),
     *             @OA\Property(property="renamed", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="from", type="string", example="1st"),
     *                 @OA\Property(property="to", type="string", example="Year 2")
     *             )),
     *             @OA\Property(property="added", type="array", @OA\Items(type="string", example="Nursery")),
     *             @OA\Property(property="removed", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="people", type="integer")
     *             )),
     *             @OA\Property(property="merged", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="people", type="integer")
     *             )),
     *             @OA\Property(property="cleared", type="integer", description="People whose grade a removed grade clears"),
     *             @OA\Property(property="state", type="string", description="Send back as expectedState when applying")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required"),
     *     @OA\Response(response=404, description="No grade names for that key")
     * )
     */
    $group->get('/presets/{key:[a-z-]+}/preview', function (Request $request, Response $response, array $args): Response {
        if (GradePresets::get($args['key']) === null) {
            throw new HttpNotFoundException($request, gettext('Grade names not found'));
        }

        return SlimUtils::renderJSON($response, GradeService::applyPreset($args['key'], (int) AuthenticationManager::getCurrentUser()->getId(), true));
    });

    /**
     * @OA\Post(
     *     path="/admin/api/grades/presets/{key}",
     *     operationId="applyGradePreset",
     *     summary="Rename the grades to a country's names, as previewed",
     *     description="People keep their grade. The plan is checked against expectedState under row locks before anything changes. A browser session must send the CSRF token in X-CSRF-Token; API-key calls do not.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="key", in="path", required=true, @OA\Schema(type="string"), example="england-wales"),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(@OA\Property(property="expectedState", type="string", description="The state token from the preview"))
     *     ),
     *     @OA\Response(response=200, description="The changes that were applied (same shape as the preview)"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Admin role required, or the CSRF token is missing"),
     *     @OA\Response(response=404, description="No grade names for that key"),
     *     @OA\Response(response=409, description="The grades changed after the preview; nothing was changed")
     * )
     */
    $group->post('/presets/{key:[a-z-]+}', function (Request $request, Response $response, array $args): Response {
        if (GradePresets::get($args['key']) === null) {
            throw new HttpNotFoundException($request, gettext('Grade names not found'));
        }

        $input = (array) $request->getParsedBody();
        try {
            $summary = GradeService::applyPreset(
                $args['key'],
                (int) AuthenticationManager::getCurrentUser()->getId(),
                false,
                (string) ($input['expectedState'] ?? '')
            );
        } catch (GradePresetChangedException) {
            return SlimUtils::renderErrorJSON($response, gettext('The grades changed after the preview. Review the changes again before applying them.'), [], 409);
        }

        return SlimUtils::renderJSON($response, $summary);
    });
})->add(new CSRFMiddleware());
