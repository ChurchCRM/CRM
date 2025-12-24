<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// entity can be a person, family, or business
$app->group('/people', function (RouteCollectorProxy $group): void {
    $group->get('/verify', 'viewPeopleVerify');
    $group->get('/', 'listPeople');
    $group->get('', 'listPeople');
});

function viewPeopleVerify(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
    ];

    if ($request->getQueryParams()['EmailsError']) {
        $errorArgs = [
            'sGlobalMessage' => gettext('Error sending email(s)') . ' - ' . gettext('Please check logs for more information'),
            'sGlobalMessageClass' => 'danger'
        ];
        $pageArgs = array_merge($pageArgs, $errorArgs);
    }

    $queryParam = $request->getQueryParams()['AllPDFsEmailed'];
    if ($queryParam) {
        $headerArgs = ['sGlobalMessage' => sprintf(gettext('PDFs successfully emailed to %s families.'), $queryParam),
            'sGlobalMessageClass'       => 'success'];
        $pageArgs = array_merge($pageArgs, $headerArgs);
    }

    return $renderer->render($response, 'people-verify-view.php', $pageArgs);
}

function listPeople(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');
    // Filter received user input as needed
    // Classification
    // Gender
    // FamilyRole

    $members = PersonQuery::create();
    // set default sMode
    $sMode = 'Person';
    // by default show only active families
    $familyActiveStatus = 'active';
    if ($_GET['familyActiveStatus'] === 'inactive') {
        $familyActiveStatus = 'inactive';
    } elseif ($_GET['familyActiveStatus'] === 'all') {
        $familyActiveStatus = 'all';
    }

    $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');

    if ($sInactiveClassificationIds === '') {
        //works the same if group doesn't exist and keeps queries tidier
        $sInactiveClassificationIds = '-1';
    }

    //parsing the string and reconstruct it back should be enough to mitigate the sql injection vector in here.
    $aInactiveClassificationIds = explode(',', $sInactiveClassificationIds);
    $aInactiveClasses = array_filter($aInactiveClassificationIds, fn ($k): bool => is_numeric($k));

    if (count($aInactiveClassificationIds) !== count($aInactiveClasses)) {
        LoggerUtils::getAppLogger()->warning('Encountered invalid configuration(s) for sInactiveClassification, please fix this');
    }

    $sInactiveClasses = implode(',', $aInactiveClasses);

    // Always retrieve all families - filtering will be done client-side by the Family Status filter
    // Family Status column will show Active or Inactive, allowing client-side filtering like other filters
    $members->leftJoinFamily();

    $members->find();

    $filterByClsId = '';
    if (isset($_GET['Classification'])) {
        $id = InputUtils::filterInt($_GET['Classification']);
        $option = ListOptionQuery::create()->filterById(1)->filterByOptionId($id)->findOne();
        if ($id === 0) {
            $filterByClsId = gettext('Unassigned');
            $sMode = $filterByClsId;
        } else {
            $filterByClsId = $option->getOptionName();
            $sMode = $filterByClsId;
        }
    }

    $filterByFmrId = '';
    if (isset($_GET['FamilyRole'])) {
        $id = InputUtils::filterInt($_GET['FamilyRole']);
        $option = ListOptionQuery::create()->filterById(2)->filterByOptionId($id)->findOne();

        if ($id === 0) {
            $filterByFmrId = gettext('Unassigned');
            $sMode = $filterByFmrId;
        } else {
            $filterByFmrId = $option->getOptionName();
            $sMode = $filterByFmrId;
        }
    }

    $filterByGender = '';
    if (isset($_GET['Gender'])) {
        $id = InputUtils::filterInt($_GET['Gender']);

        switch ($id) {
            case 0:
                $filterByGender = gettext('Unassigned');
                $sMode = $sMode . ' - ' . $filterByGender;
                break;
            case 1:
                $filterByGender = gettext('Male');
                $sMode = $sMode . ' - ' . $filterByGender;
                break;
            case 2:
                $filterByGender = gettext('Female');
                $sMode = $sMode . ' - ' . $filterByGender;
                break;
        }
    }

    // Family status is computed on-demand in templates via Family::isActive()

    $pageArgs = [
        'sMode'                           => $sMode,
        'sRootPath'                       => SystemURLs::getRootPath(),
        'members'                         => $members,
        'filterByClsId'                   => $filterByClsId,
        'filterByFmrId'                   => $filterByFmrId,
        'filterByGender'                  => $filterByGender,
        'familyActiveStatus'              => $familyActiveStatus,
        // no precomputed familyStatusMap: templates will call Family::isActive()
    ];

    return $renderer->render($response, 'person-list.php', $pageArgs);
}
