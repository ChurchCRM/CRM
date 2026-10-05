<?php

use ChurchCRM\Service\FamilyService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/api/map/geocode-all', 'geocodeAllFamilies');

/**
 * @OA\Post(
 *     path="/admin/api/map/geocode-all",
 *     summary="Geocode all active families missing coordinates",
 *     description="Iterates active families that have a street address but no usable coordinates, geocoding each via the configured geocoding services (Map Settings) at ~1 request/second. Families are taken in ID order; up to 50 per call. Pass 'skip' = the number of families that failed in earlier calls so those are not re-queried, and repeat while 'remaining' > 'skip'. Admin-only.",
 *     tags={"Map"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             @OA\Property(property="skip", type="integer", minimum=0, default=0, description="Families (in ID order) to skip before the batch — the running count of failures from previous calls")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Geocoding batch summary",
 *         @OA\JsonContent(
 *             @OA\Property(property="total",     type="integer", description="Total families missing coordinates before this run"),
 *             @OA\Property(property="skip",      type="integer", description="Offset applied to this batch (echoed back)"),
 *             @OA\Property(property="processed", type="integer", description="Families examined in this batch (0 when skip >= total)"),
 *             @OA\Property(property="geocoded",  type="integer", description="Families successfully geocoded in this batch"),
 *             @OA\Property(property="failed",    type="integer", description="Families that could not be geocoded"),
 *             @OA\Property(property="remaining", type="integer", description="Families still missing coordinates after this batch, including the ones that failed (run again while remaining > failures so far)"),
 *             @OA\Property(
 *                 property="failures",
 *                 type="array",
 *                 description="Per-family failure details (capped at 20 entries). Empty array when all families were geocoded.",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id",      type="integer", description="Family ID"),
 *                     @OA\Property(property="name",    type="string",  description="Family name"),
 *                     @OA\Property(property="address", type="string",  description="Full street address"),
 *                     @OA\Property(property="editUrl", type="string",  description="URL to the family editor page"),
 *                     @OA\Property(property="reason",  type="string",  description="Machine code: 'incomplete_address' | 'no_result' | 'error'")
 *                 )
 *             ),
 *             @OA\Property(property="failuresTruncated", type="boolean", description="True when failed > 20 and some failures are omitted from the array")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function geocodeAllFamilies(Request $request, Response $response, array $args): Response
{
    // Allow extended execution time for the throttled Nominatim loop (~1 req/sec × up to 50 families)
    set_time_limit(240);

    $input = $request->getParsedBody();
    $skip = is_array($input) && isset($input['skip']) && is_numeric($input['skip']) ? (int) $input['skip'] : 0;
    if ($skip < 0) {
        return SlimUtils::renderErrorJSON($response, gettext('skip must be zero or a positive integer'), [], 400, null, $request);
    }

    try {
        $summary = (new FamilyService())->geocodeAllMissingFamilies($skip);
        return SlimUtils::renderJSON($response, $summary);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to update family coordinates'),
            [],
            500,
            $e,
            $request
        );
    }
}
