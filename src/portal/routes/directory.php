<?php

use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\RequirePortalCapability;
use ChurchCRM\Portal\PortalDirectoryService;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$directoryHandler = static function (Request $request, Response $response): Response {
    $query = $request->getQueryParams()['q'] ?? '';
    $found = PortalDirectoryService::find(is_string($query) ? $query : '');

    return PortalTwig::render($response, 'directory/index.html.twig', $found + [
        'pageTitle' => gettext('Directory'),
        'entryCount' => array_sum(array_map(static fn (array $group): int => count($group['entries']), $found['groups'])),
        'searchMinLength' => PortalDirectoryService::SEARCH_MIN_LENGTH,
        'searchLimit' => PortalDirectoryService::SEARCH_LIMIT,
    ], PortalNav::DIRECTORY);
};

$group->get('/directory', $directoryHandler)->add(RequirePortalCapability::any(CorePortalCapabilities::DIRECTORY_VIEW));
$group->get('/directory/', $directoryHandler)->add(RequirePortalCapability::any(CorePortalCapabilities::DIRECTORY_VIEW));
