<?php

use ChurchCRM\dto\SystemURLs;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

function renderPage(Response $response, $renderPath, $renderFile, $title = "")
{
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext($title)
    ];

    $renderer = new PhpRenderer($renderPath);
    return $renderer->render($response, $renderFile, $pageArgs);
}