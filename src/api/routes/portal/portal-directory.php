<?php

use ChurchCRM\dto\Photo;
use ChurchCRM\Portal\Auth\PortalSessionMiddleware;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\RequirePortalCapability;
use ChurchCRM\Portal\PortalApiMiddleware;
use ChurchCRM\Portal\PortalDirectoryService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/*
 * `/api/portal/directory/*` — the member directory the Directory page shows. Who is listed
 * and what is shown come from Admin → Member Portal → Directory, never from the caller.
 * A photo of someone the directory does not show and a missing photo are the same 404.
 */
$app->group('/portal/directory', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/portal/directory",
     *     operationId="getPortalDirectory",
     *     summary="The member directory, or the entries matching a name, grouped by first letter",
     *     tags={"Portal"},
     *     @OA\Parameter(name="q", in="query", required=false, description="Words that must all appear in an entry's names. Required, with at least two characters, when the directory is search only; that answer holds at most 20 entries", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Groups of family and person entries"),
     *     @OA\Response(response=403, description="The member's classification may not see the directory"),
     *     @OA\Response(response=404, description="The directory is switched off")
     * )
     */
    $group->get('', function (Request $request, Response $response): Response {
        $query = $request->getQueryParams()['q'] ?? '';

        return SlimUtils::renderJSON($response, PortalDirectoryService::find(is_string($query) ? $query : ''));
    });

    /**
     * @OA\Get(
     *     path="/portal/directory/{type}/{id}/photo",
     *     operationId="getPortalDirectoryPhoto",
     *     summary="The photo of a person or family the directory shows",
     *     tags={"Portal"},
     *     @OA\Parameter(name="type", in="path", required=true, @OA\Schema(type="string", enum={"person", "family"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="The image"),
     *     @OA\Response(response=404, description="Not shown in the directory, or no photo")
     * )
     */
    $group->get('/{type:person|family}/{id:[0-9]+}/photo', function (Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $isPerson = $args['type'] === 'person';
        $shown = $isPerson ? PortalDirectoryService::showsPersonPhoto($id) : PortalDirectoryService::showsFamilyPhoto($id);
        $photo = new Photo($isPerson ? 'Person' : 'Family', $id);
        if (!$shown || !$photo->hasUploadedPhoto()) {
            return SlimUtils::renderErrorJSON($response, gettext('No photo has been uploaded for this person.'), [], 404);
        }

        // Not cached: on a shared browser the next member signed in could be served it
        // without the directory check running again.
        return SlimUtils::renderPhoto($response, $photo)->withHeader('Cache-Control', 'private, no-store');
    });
})->add(RequirePortalCapability::any(CorePortalCapabilities::DIRECTORY_VIEW))
    ->add(new PortalApiMiddleware())->add(new PortalSessionMiddleware());
