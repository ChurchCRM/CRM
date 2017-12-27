<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\Base\SurveyDefinitionQuery;

$app->get('/dashboard', 'viewSurveyDashboard');


function viewSurveyDashboard(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');
    return $renderer->render($response, 'survey-dashboard.php');
}