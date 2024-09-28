<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/dashboard', 'viewDashboard');

function viewDashboard(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/root/');

    $dashboardCounts = [];

    $dashboardCounts['families'] = FamilyQuery::create()
        ->filterByDateDeactivated()
        ->count();

    $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');
    if ($sInactiveClassificationIds === '') {
        $sInactiveClassificationIds = '-1';
    }
    $aInactiveClassificationIds = explode(',', $sInactiveClassificationIds);
    $dashboardCounts['People'] = PersonQuery::create()
        ->filterByClsId($aInactiveClassificationIds, Criteria::NOT_IN)
        ->leftJoinWithFamily()
        ->where('Family.DateDeactivated is null')
        ->count();

    $dashboardCounts['SundaySchool'] = GroupQuery::create()
        ->filterByType(4)
        ->count();

    $dashboardCounts['Groups'] = GroupQuery::create()
        ->count();

    $dashboardCounts['events'] = EventAttendQuery::create()
        ->filterByCheckinDate(null, Criteria::NOT_EQUAL)
        ->filterByCheckoutDate(null, Criteria::EQUAL)
        ->find()
        ->count();

    $pageArgs = [
        'sRootPath'           => SystemURLs::getRootPath(),
        'sPageTitle'          => gettext('Welcome to') . ' ' . ChurchMetaData::getChurchName(),
        'dashboardCounts'     => $dashboardCounts,
        'sundaySchoolEnabled' => SystemConfig::getBooleanValue('bEnabledSundaySchool'),
        'depositEnabled'      => AuthenticationManager::getCurrentUser()->isFinanceEnabled(),
        'eventsEnabled'       => SystemConfig::getBooleanValue('bEnabledEvents'),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}
