<?php

use ChurchCRM\dto\SystemURLs;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->group('/person', function () {
    $this->get('/not-found', 'viewPersonNotFound');
});


function viewPersonNotFound(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/common/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'memberType' => "Person",
        'id' => $request->getParam("id")
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}
