<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\Base\SurveyDefinitionQuery;



$app->get('/not-found', 'viewSurveyNotFound');
$app->get('/{id}/view', 'viewSurvey');
$app->get('/{id}/view/', 'viewSurvey');
$app->get('/{id}/edit', 'editSurvey');
$app->get('/{id}/edit/', 'editSurvey');
$app->get('/{id}/results', 'viewSurveyResults');
$app->get('/{id}/results/', 'viewSurveyResults');
$app->get('/dashboard', 'viewSurveyDashboard');



function viewSurveyDashboard(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');
    return $renderer->render($response, 'survey-dashboard.php');
}


function viewSurveyNotFound(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'id' => $request->getParam("id")
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}

function viewSurvey(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');

    $surveyId = $args["id"];
    $survey = SurveyDefinitionQuery::create()->findPk($surveyId);

    if (empty($survey)) {
        return $response->withRedirect(SystemURLs::getRootPath() . "/surveys/not-found?id=".$args["id"]);
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'survey' => $survey
    ];

    return $renderer->render($response, 'survey-view.php', $pageArgs);

}

function editSurvey(Request $request, Response $response, array $args)
{

    $renderer = new PhpRenderer('templates/');

    $surveyId = $args["id"];
    $survey = SurveyDefinitionQuery::create()->findPk($surveyId);

    if (empty($survey)) {
        return $response->withRedirect(SystemURLs::getRootPath() . "/surveys/not-found?id=".$args["id"]);
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'survey' => $survey
    ];

    return $renderer->render($response, 'survey-edit.php', $pageArgs);

}