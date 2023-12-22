<?php

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\PhpRenderer;

function renderPage(Response $response, string $renderPath, string $renderFile, string $title = ''): Response
{
    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext($title),
    ];

    $renderer = new PhpRenderer($renderPath);

    return $renderer->render($response, $renderFile, $pageArgs);
}
