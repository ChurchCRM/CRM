<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\FamilyQuery;
use ChurchCRM\Service\TimelineService;

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
