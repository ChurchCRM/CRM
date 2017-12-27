<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\Base\SurveyDefinitionQuery;


$app->group("/responses", function() {
  $this->get('', 'viewSurveyResponses');
  $this->get('/', 'viewSurveyResponses');
});



function viewSurveyResponses(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'id' => $request->getParam("id")
    ];

    return $renderer->render($response, 'survey-responses-list.php', $pageArgs);
}