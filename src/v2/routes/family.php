<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\PeopleCustomField;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\FamilyCustomQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\PropertyQuery;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->group('/family', function () {
    $this->get('','listFamilies');
    $this->get('/','listFamilies');
    $this->get('/not-found', 'viewFamilyNotFound');
    $this->get('/{id}', 'viewFamily');
});

function listFamilies(Request $request, Response $response, array $args)
{
  $renderer = new PhpRenderer('templates/people/');
  $sMode = 'Active';
  // Filter received user input as needed
  if (isset($_GET['mode'])) {
      $sMode = InputUtils::LegacyFilterInput($_GET['mode']);
  }
  if (strtolower($sMode) == 'inactive') {
      $families = FamilyQuery::create()
          ->filterByDateDeactivated(null, Criteria::ISNOTNULL)
              ->orderByName()
              ->find();
  } else {
      $sMode = 'Active';
      $families = FamilyQuery::create()
          ->filterByDateDeactivated(null)
              ->orderByName()
              ->find();
  }
  $pageArgs = [
      'sMode' => $sMode,
      'sRootPath' => SystemURLs::getRootPath(),
      'families' => $families
  ];
  return $renderer->render($response, 'family-list.php', $pageArgs);
}

function viewFamilyNotFound(Request $request, Response $response, array $args)
{
  $renderer = new PhpRenderer('templates/common/');

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

    $allFamilyCustomFields = FamilyCustomMasterQuery::create()->find();

    // get family with all the extra columns created
    $rawQry =  FamilyCustomQuery::create();
    foreach ($allFamilyCustomFields as $customfield ) {
        $rawQry->withColumn($customfield->getField());
    }
    $thisFamilyCustomFields = $rawQry->findOneByFamId($familyId);

    if ($thisFamilyCustomFields) {
        $familyCustom = [];
        foreach ($allFamilyCustomFields as $customfield ) {
            if (AuthenticationManager::GetCurrentUser()->isEnabledSecurity($customfield->getFieldSecurity())) {
                $value = $thisFamilyCustomFields->getVirtualColumn($customfield->getField());
                if (!empty($value)) {
                    $item = new PeopleCustomField($customfield, $value);
                    array_push($familyCustom, $item);
                }
            }
        }
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'family' => $family,
        'familyTimeline' => $timelineService->getForFamily($family->getId()),
        'allFamilyProperties' => $allFamilyProperties,
        'familyCustom' => $familyCustom
    ];

    return $renderer->render($response, 'family-view.php', $pageArgs);

}

