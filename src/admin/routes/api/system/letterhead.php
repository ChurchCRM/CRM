<?php

use ChurchCRM\Exceptions\PhotoSizeException;
use ChurchCRM\Service\LetterheadService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * @OA\Get(
 *     path="/admin/api/system/letterhead",
 *     operationId="getLetterhead",
 *     summary="Get the letterhead status",
 *     description="Reports whether an administrator has uploaded a letterhead image for PDF letters.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Letterhead status",
 *         @OA\JsonContent(
 *             @OA\Property(property="hasLetterhead", type="boolean", example=true),
 *             @OA\Property(property="url", type="string", nullable=true, example="/Images/church-letterhead.png?v=3f2a9c1e8b7d6a54")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 * @OA\Post(
 *     path="/admin/api/system/letterhead",
 *     operationId="uploadLetterhead",
 *     summary="Upload the letterhead image (base64 encoded)",
 *     description="Accepts a base64 data URI (JPEG, PNG, GIF or WebP), downscales it to fit 2400x600 and stores it as Images/church-letterhead.png.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(@OA\Property(property="imgBase64", type="string", example="data:image/png;base64,iVBORw0KGgo..."))
 *     ),
 *     @OA\Response(response=200, description="Letterhead stored",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="hasLetterhead", type="boolean", example=true),
 *             @OA\Property(property="url", type="string", example="/Images/church-letterhead.png?v=3f2a9c1e8b7d6a54")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Missing or unsupported image data"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=413, description="Image exceeds the server upload limit or the source image size limit")
 * )
 * @OA\Delete(
 *     path="/admin/api/system/letterhead",
 *     operationId="deleteLetterhead",
 *     summary="Remove the uploaded letterhead",
 *     description="Deletes Images/church-letterhead.png. Idempotent: succeeds even when none is stored.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Letterhead removed",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="hasLetterhead", type="boolean", example=false),
 *             @OA\Property(property="url", type="string", nullable=true, example=null)
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=500, description="The stored letterhead could not be removed")
 * )
 */
$app->group('/api/system/letterhead', function (RouteCollectorProxy $group): void {
    $state = static fn (): array => [
        'hasLetterhead' => LetterheadService::hasUploaded(),
        'url' => LetterheadService::getUrlPath(),
    ];

    $group->get('', function (Request $request, Response $response, array $args) use ($state): Response {
        return SlimUtils::renderJSON($response, $state());
    });

    $group->post('', function (Request $request, Response $response, array $args) use ($state): Response {
        $input = $request->getParsedBody();

        if (empty($input) || !isset($input['imgBase64'])) {
            // PHP discards a request body over its limit, so an empty body with a large
            // Content-Length is an oversized upload; any other missing field is a 400.
            $body = $request->getBody();
            $bodyWasDiscarded = empty($input) && ($body->getSize() ?? 0) === 0 && (string) $body === '';
            if ($bodyWasDiscarded && (int) ($request->getServerParams()['CONTENT_LENGTH'] ?? 0) > SystemService::getMaxUploadFileSize(false)) {
                return SlimUtils::renderErrorJSON(
                    $response,
                    sprintf(gettext('File size exceeds the server limit of %s'), SystemService::getMaxUploadFileSize(true)),
                    [],
                    413
                );
            }

            return SlimUtils::renderErrorJSON($response, gettext('Missing image data in request'), [], 400);
        }

        try {
            LetterheadService::setImageFromBase64((string) $input['imgBase64']);

            return SlimUtils::renderJSON($response, ['success' => true] + $state());
        } catch (PhotoSizeException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 413, $e, $request);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to upload letterhead'), [], 400, $e, $request);
        }
    });

    $group->delete('', function (Request $request, Response $response, array $args) use ($state): Response {
        if (!LetterheadService::delete()) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to remove letterhead'), [], 500);
        }

        return SlimUtils::renderJSON($response, ['success' => true] + $state());
    });
});
