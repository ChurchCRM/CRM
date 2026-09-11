<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Exceptions\PhotoSizeException;
use ChurchCRM\Service\ChurchLogoService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * @OA\Get(
 *     path="/system/church-logo",
 *     operationId="getChurchLogo",
 *     summary="Get the current church logo status",
 *     description="Reports whether an administrator has uploaded a church logo and the URL currently resolved for it.",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Church logo status",
 *         @OA\JsonContent(
 *             @OA\Property(property="hasCustomLogo", type="boolean", example=true),
 *             @OA\Property(property="url", type="string", example="/Images/church-logo.png?v=1718000000")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 * @OA\Post(
 *     path="/system/church-logo",
 *     operationId="uploadChurchLogo",
 *     summary="Upload the church logo (base64 encoded)",
 *     description="Accepts a base64 data URI (JPEG, PNG, GIF or WebP), downscales it to fit 1200x400 and stores it as Images/church-logo.png.",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="imgBase64", type="string", example="data:image/png;base64,iVBORw0KGgo...")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Logo stored",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="hasCustomLogo", type="boolean", example=true),
 *             @OA\Property(property="url", type="string", example="/Images/church-logo.png?v=1718000000")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Missing or unsupported image data"),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=413, description="Image exceeds the server upload limit")
 * )
 * @OA\Delete(
 *     path="/system/church-logo",
 *     operationId="deleteChurchLogo",
 *     summary="Remove the uploaded church logo",
 *     description="Deletes Images/church-logo.png. Idempotent — succeeds even when no logo is stored.",
 *     tags={"System"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Logo removed",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="hasCustomLogo", type="boolean", example=false),
 *             @OA\Property(property="url", type="string", example="/Images/logo-churchcrm-350.jpg")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=500, description="The stored logo could not be removed")
 * )
 */
$app->group('/system/church-logo', function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response, array $args): Response {
        return SlimUtils::renderJSON($response, [
            'hasCustomLogo' => ChurchMetaData::hasCustomLogo(),
            'url'           => ChurchMetaData::getChurchLogoPath(),
        ]);
    });

    $group->post('', function (Request $request, Response $response, array $args): Response {
        $input = $request->getParsedBody();

        if (empty($input) || !isset($input['imgBase64'])) {
            // PHP throws the whole request body away when it exceeds the size the
            // server accepts, so "nothing arrived at all" is the only honest
            // signal that the upload was too large. A body that *did* arrive but
            // carries no imgBase64 is a malformed request (400) whatever its
            // Content-Length claims — trusting the header alone lets an inflated
            // or spoofed value turn a bad request into a misleading 413.
            $body = $request->getBody();
            $bodySize = $body->getSize();
            $bodyWasDiscarded = empty($input)
                && ($bodySize === null || $bodySize === 0)
                && (string) $body === '';

            if ($bodyWasDiscarded) {
                $contentLength = (int) ($request->getServerParams()['CONTENT_LENGTH'] ?? 0);
                if ($contentLength > SystemService::getMaxUploadFileSize(false)) {
                    return SlimUtils::renderErrorJSON(
                        $response,
                        sprintf(gettext('File size exceeds the server limit of %s'), SystemService::getMaxUploadFileSize(true)),
                        [],
                        413
                    );
                }
            }

            return SlimUtils::renderErrorJSON($response, gettext('Missing image data in request'), [], 400);
        }

        try {
            ChurchLogoService::setImageFromBase64((string) $input['imgBase64']);

            return SlimUtils::renderJSON($response, [
                'success'       => true,
                'hasCustomLogo' => ChurchMetaData::hasCustomLogo(),
                'url'           => ChurchMetaData::getChurchLogoPath(),
            ]);
        } catch (PhotoSizeException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 413, $e, $request);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to upload church logo'), [], 400, $e, $request);
        }
    });

    $group->delete('', function (Request $request, Response $response, array $args): Response {
        // delete() is idempotent: it returns true when there is no logo to remove,
        // so false means unlink() genuinely failed (permissions, read-only mount)
        // and the logo is still being served. Reporting that as 200 lies to the UI.
        if (!ChurchLogoService::delete()) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to remove church logo'), [], 500);
        }

        return SlimUtils::renderJSON($response, [
            'success'       => true,
            'hasCustomLogo' => ChurchMetaData::hasCustomLogo(),
            'url'           => ChurchMetaData::getChurchLogoPath(),
        ]);
    });
})->add(AdminRoleAuthMiddleware::class);
