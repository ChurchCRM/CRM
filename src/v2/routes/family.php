<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\FamilyQuery;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\PropertyQuery;

$app->group('/family', function () {
    $this->get('/not-found', 'viewFamilyNotFound');
    $this->get('/{id}/view', 'viewFamily');
    $this->get('/{id}/view/', 'viewFamily');
});


function viewFamilyNotFound(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/people/');

  $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'memberType' => "Family",
        'id' => $request->getParam("id")
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}

function viewFamily(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/people/');

    $familyId = $args["id"];
    $family = FamilyQuery::create()->findPk($familyId);

    if (empty($family)) {
        return $response->withRedirect(SystemURLs::getRootPath() . "/v2/family/not-found?id=".$args["id"]);
    }

    $timelineService = new TimelineService();

    $allFamilyProperties = PropertyQuery::create()->findByProClass("f");

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'family' => $family,
        'familyTimeline' => $timelineService->getForFamily($family->getId()),
        'allFamilyProperties' => $allFamilyProperties
    ];

    return $renderer->render($response, 'family-view.php', $pageArgs);

}

