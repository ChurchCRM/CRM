<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\EventAttendQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\GroupQuery;
use ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->group('', function () {
    $this->get('/dashboard', 'viewDashboard');
});


function viewDashboard(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/root/');


    $dashboardCounts = [];

    $dashboardCounts["families"] = FamilyQuery::Create()
        ->filterByDateDeactivated()
        ->count();

    $dashboardCounts["People"] =  PersonQuery::create()
        ->leftJoinWithFamily()
        ->where('Family.DateDeactivated is null')
        ->count();

    $dashboardCounts["SundaySchool"] =  GroupQuery::create()
        ->filterByType(4)
        ->count();

    $dashboardCounts["Groups"] =  GroupQuery::create()
        ->filterByType(4, Criteria::NOT_EQUAL)
        ->count();

    $dashboardCounts["events"] = EventAttendQuery::create()
        ->filterByCheckinDate(null, Criteria::NOT_EQUAL)
        ->filterByCheckoutDate(null, Criteria::EQUAL)
        ->find()
        ->count();


    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Welcome to').' '. ChurchMetaData::getChurchName(),
        'dashboardCounts' => $dashboardCounts,
        'sundaySchoolEnabled' => SystemConfig::getBooleanValue("bEnabledSundaySchool"),
        'depositEnabled' => AuthenticationManager::GetCurrentUser()->isFinanceEnabled()
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}
