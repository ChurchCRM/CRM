<?php

use ChurchCRM\Exceptions\ImageStorageException;
use ChurchCRM\Exceptions\PhotoSizeException;
use ChurchCRM\Service\TaxSignatureService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * @OA\Get(
 *     path="/admin/api/system/tax-signature", operationId="getTaxSignatureStatus",
 *     summary="Get private tax-signature status", tags={"Admin"}, security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Signature status", @OA\JsonContent(@OA\Property(property="hasSignature", type="boolean"))),
 *     @OA\Response(response=401, description="Authentication required"), @OA\Response(response=403, description="Admin required")
 * )
 * @OA\Get(
 *     path="/admin/api/system/tax-signature/image", operationId="getTaxSignatureImage",
 *     summary="Read the private tax-signature PNG", tags={"Admin"}, security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Private PNG; never cached", @OA\MediaType(mediaType="image/png", @OA\Schema(type="string", format="binary"))),
 *     @OA\Response(response=404, description="No signature stored"),
 *     @OA\Response(response=401, description="Authentication required"), @OA\Response(response=403, description="Admin required")
 * )
 * @OA\Post(
 *     path="/admin/api/system/tax-signature", operationId="uploadTaxSignature",
 *     summary="Upload a private PNG or JPEG tax signature", tags={"Admin"}, security={{"ApiKeyAuth":{}}},
 *     description="Maximum 2 MiB and 2000 by 1000 pixels. Re-encoded as PNG in private storage outside the web root. Session requests require a CSRF token.",
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"imgBase64"}, @OA\Property(property="imgBase64", type="string"))),
 *     @OA\Response(response=200, description="Signature stored"), @OA\Response(response=400, description="Invalid image"),
 *     @OA\Response(response=413, description="Image exceeds limits"), @OA\Response(response=500, description="Private storage unavailable"),
 *     @OA\Response(response=401, description="Authentication required"), @OA\Response(response=403, description="Admin or CSRF check failed")
 * )
 * @OA\Delete(
 *     path="/admin/api/system/tax-signature", operationId="deleteTaxSignature",
 *     summary="Remove the private tax signature", tags={"Admin"}, security={{"ApiKeyAuth":{}}},
 *     description="Session requests require a CSRF token.",
 *     @OA\Response(response=200, description="Signature removed"), @OA\Response(response=500, description="Removal failed"),
 *     @OA\Response(response=401, description="Authentication required"), @OA\Response(response=403, description="Admin or CSRF check failed")
 * )
 */
$app->group('/api/system/tax-signature', function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response): Response {
        return SlimUtils::renderJSON($response, ['hasSignature' => TaxSignatureService::hasUploaded()]);
    });

    $group->get('/image', function (Request $request, Response $response): Response {
        $image = TaxSignatureService::image();
        if ($image === null) {
            return $response->withStatus(404)->withHeader('Cache-Control', 'no-store');
        }
        $response->getBody()->write($image);

        return $response->withHeader('Content-Type', 'image/png')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    });

    $group->post('', function (Request $request, Response $response): Response {
        $input = $request->getParsedBody();
        if (!is_array($input) || !is_string($input['imgBase64'] ?? null) || count(array_diff(array_keys($input), ['imgBase64', 'csrf_token'])) > 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Provide only PNG or JPEG signature image data.'), [], 400);
        }
        try {
            TaxSignatureService::setImageFromBase64($input['imgBase64']);

            return SlimUtils::renderJSON($response, ['success' => true, 'hasSignature' => true]);
        } catch (PhotoSizeException $error) {
            return SlimUtils::renderErrorJSON($response, $error->getMessage(), [], 413, $error, $request);
        } catch (ImageStorageException $error) {
            return SlimUtils::renderErrorJSON($response, gettext('Cannot store the private signature. Check private storage permissions outside the application document root.'), [], 500, $error, $request);
        } catch (InvalidArgumentException $error) {
            return SlimUtils::renderErrorJSON($response, $error->getMessage(), [], 400, $error, $request);
        }
    });

    $group->delete('', function (Request $request, Response $response): Response {
        try {
            TaxSignatureService::delete();

            return SlimUtils::renderJSON($response, ['success' => true, 'hasSignature' => false]);
        } catch (ImageStorageException $error) {
            return SlimUtils::renderErrorJSON($response, gettext('Cannot remove the private signature.'), [], 500, $error, $request);
        }
    });
})->add(new CSRFMiddleware())->add(AdminRoleAuthMiddleware::class);
