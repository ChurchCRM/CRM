<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;
use ChurchCRM\dto\SystemURLs;

$app->group('/people', function () {
    $this->get('/verify', 'viewPeopleVerify');
});


function viewPeopleVerify(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/people/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
    ];

    if ($request->getParam("EmailsError")) {
        array_merge($pageArgs, ['sGlobalMessage' =>"Eamil Error"]);
    }



    return $renderer->render($response, 'people-verify-view.php', $pageArgs);
}
