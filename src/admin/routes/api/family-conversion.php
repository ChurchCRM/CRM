<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Served at /admin/api/*. AdminRoleAuthMiddleware gates every route in this app.
$app->post('/api/person/{personId:[0-9]+}/convert-to-family', 'convertPersonToFamilyAPI')
    ->add(new PersonMiddleware())
    ->add(new CSRFMiddleware());

/**
 * @OA\Post(
 *     path="/api/person/{personId}/convert-to-family",
 *     operationId="convertPersonToFamily",
 *     summary="Make a person with no family the only member of a new family (Admin role)",
 *     description="The family takes the person's last name, address and home phone, and those fields move off the person.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer", example=2)),
 *     @OA\Response(response=200, description="Converted",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="family", type="object",
 *                 @OA\Property(property="id", type="integer", example=12),
 *                 @OA\Property(property="name", type="string", example="Smith"),
 *                 @OA\Property(property="url", type="string", example="/people/family/12")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="The person already belongs to a family"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required, or an invalid CSRF token"),
 *     @OA\Response(response=404, description="Person not found")
 * )
 */
function convertPersonToFamilyAPI(Request $request, Response $response): Response
{
    try {
        $family = (new FamilyService())->convertPersonToFamily($request->getAttribute('person'), (int) AuthenticationManager::getCurrentUser()->getId());
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'family' => [
            'id' => (int) $family->getId(),
            'name' => (string) $family->getName(),
            'url' => $family->getViewURI(),
        ],
    ]);
}
