<?php

namespace ChurchCRM\Slim\Controller;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Controller for photo upload page
 * Handles centralized photo upload for Person and Family entities
 */
class PhotoUploadController
{
    /**
     * Display the photo upload page
     *
     * @param Request $request The request
     * @param Response $response The response
     * @param array $args Route parameters (type, id)
     * @return Response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $renderer = new PhpRenderer('templates/photo/');
        
        $type = $args['type'] ?? ''; // 'person' or 'family'
        $id = (int)($args['id'] ?? 0);
        
        // Validate type
        if (!in_array($type, ['person', 'family'], true)) {
            $response->getBody()->write('Invalid type. Must be "person" or "family".');
            return $response->withStatus(400);
        }
        
        // Validate ID
        if ($id <= 0) {
            $response->getBody()->write('Invalid ID.');
            return $response->withStatus(400);
        }
        
        // Determine API endpoint based on type
        $uploadUrl = SystemURLs::getRootPath() . "/api/{$type}/{$id}/photo";
        
        // Determine return URL based on type
        $returnUrl = $type === 'person' 
            ? SystemURLs::getRootPath() . "/v2/people/person/view/{$id}"
            : SystemURLs::getRootPath() . "/v2/people/family/view/{$id}";
        
        $pageArgs = [
            'sRootPath'      => SystemURLs::getRootPath(),
            'sCSPNonce'      => SystemURLs::getCSPNonce(),
            'type'           => $type,
            'id'             => $id,
            'uploadUrl'      => $uploadUrl,
            'maxFileSize'    => SystemService::getMaxUploadFileSize(true),
            'maxFileSizeBytes' => SystemService::getMaxUploadFileSize(false),
            'photoWidth'     => SystemConfig::getValue('iPhotoWidth'),
            'photoHeight'    => SystemConfig::getValue('iPhotoHeight'),
            'returnUrl'      => $returnUrl,
        ];

        return $renderer->render($response, 'photo-upload.php', $pageArgs);
    }
}
