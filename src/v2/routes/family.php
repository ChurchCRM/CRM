<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\PeopleCustomField;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\FamilyCustomQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/family', function (RouteCollectorProxy $group): void {
    $group->get('/not-found', 'viewFamilyNotFound');
    $group->get('/{id}', 'viewFamily');
    $group->get('/', 'listFamilies');
    $group->get('', 'listFamilies');
});

function listFamilies(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');
    $sMode = 'Active';
    // Filter received user input as needed
    if (isset($_GET['mode'])) {
        $sMode = InputUtils::legacyFilterInput($_GET['mode']);
    }
    if (strtolower($sMode) === 'inactive') {
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
        'families' => $families,
    ];

    return $renderer->render($response, 'family-list.php', $pageArgs);
}

function viewFamilyNotFound(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/common/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'memberType' => 'Family',
        'id' => SlimUtils::getURIParamInt($request, 'id'),
    ];

    return $renderer->render($response, 'not-found-view.php', $pageArgs);
}

function viewFamily(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');

    $familyId = $args['id'];
    $family = FamilyQuery::create()->findPk($familyId);

    if (empty($family)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/family/not-found?id=' . $args['id']);
    }

    $timelineService = new TimelineService();

    $allFamilyProperties = PropertyQuery::create()->findByProClass('f');

    $allFamilyCustomFields = FamilyCustomMasterQuery::create()->find();

    // get family with all the extra columns created
    $rawQry = FamilyCustomQuery::create();
    foreach ($allFamilyCustomFields as $customfield) {
        $rawQry->withColumn($customfield->getField());
    }
    $appFamilyCustomFields = $rawQry->findOneByFamId($familyId);

    $familyCustom = [];
    if ($appFamilyCustomFields) {
        foreach ($allFamilyCustomFields as $customfield) {
            if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($customfield->getFieldSecurity())) {
                $value = $appFamilyCustomFields->getVirtualColumn($customfield->getField());
                if (!empty($value)) {
                    $item = new PeopleCustomField($customfield, $value);
                    $familyCustom[] = $item;
                }
            }
        }
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'family' => $family,
        'familyTimeline' => $timelineService->getForFamily($family->getId()),
        'allFamilyProperties' => $allFamilyProperties,
        'familyCustom' => $familyCustom,
    ];

    return $renderer->render($response, 'family-view.php', $pageArgs);
}
