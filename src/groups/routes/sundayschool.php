<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\Service\SundaySchoolService;
use ChurchCRM\Slim\Middleware\Request\Setting\SundaySchoolEnabledMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/sundayschool', function (RouteCollectorProxy $group) {

    // Redirect /groups/sundayschool to dashboard
    $group->get('', function (Request $request, Response $response) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/groups/sundayschool/dashboard')
            ->withStatus(302);
    });

    // Sunday School Dashboard
    $group->get('/dashboard', function (Request $request, Response $response) {
        $dashboardService    = new DashboardService();
        $sundaySchoolService = new SundaySchoolService();

        $groupStats         = $dashboardService->getGroupStats();
        $kidsWithoutClasses = $sundaySchoolService->getKidsWithoutClasses();
        $classStats         = $sundaySchoolService->getClassStats();

        $classes    = $groupStats['sundaySchoolClasses'];
        $teachers   = 0;
        $kids       = 0;
        $maleKids   = 0;
        $femaleKids = 0;
        $familyIds  = [];

        foreach ($classStats as $class) {
            $kids     += $class['kids'];
            $teachers += $class['teachers'];
            $classKids = $sundaySchoolService->getKidsFullDetails($class['id']);
            foreach ($classKids as $kid) {
                $familyIds[] = $kid['fam_id'];
                if ($kid['kidGender'] == '1') {
                    $maleKids++;
                } elseif ($kid['kidGender'] == '2') {
                    $femaleKids++;
                }
            }
        }

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'          => SystemURLs::getRootPath(),
            'sPageTitle'         => gettext('Sunday School Dashboard'),
            'sPageSubtitle'      => gettext('Manage Sunday school classes, teachers, and student attendance'),
            'aBreadcrumbs'       => PageHeader::breadcrumbs([
                [gettext('Groups'), '/groups/dashboard'],
                [gettext('Sunday School')],
            ]),
            'sPageHeaderButtons' => PageHeader::buttons([
                ['label' => gettext('Kiosk Manager'), 'url' => '/kiosk/admin', 'icon' => 'fa-tablet-screen-button'],
            ]),
            'classStats'         => $classStats,
            'kidsWithoutClasses' => $kidsWithoutClasses,
            'classes'            => $classes,
            'teachers'           => $teachers,
            'kids'               => $kids,
            'families'           => count(array_unique($familyIds)),
            'maleKids'           => $maleKids,
            'femaleKids'         => $femaleKids,
            'canManageGroups'    => AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(),
        ];

        return $renderer->render($response, 'sundayschool/dashboard.php', $pageArgs);
    });

    // Sunday School Class View
    $group->get('/class/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $sundaySchoolService = new SundaySchoolService();

        $iGroupId   = (int) $args['id'];
        $iGroupName = 'Unknown';

        $thisGroup = GroupQuery::create()->findPk($iGroupId);
        if ($thisGroup !== null) {
            $iGroupName = $thisGroup->getName();
        }

        $birthDayMonthChartArray = [];
        $rsTeachers              = [];
        $thisClassChildren       = [];

        try {
            foreach ($sundaySchoolService->getKidsBirthdayMonth($iGroupId) as $birthDayMonth => $kidsCount) {
                $birthDayMonthChartArray[] = [gettext($birthDayMonth), $kidsCount];
            }
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->error('SundaySchool ClassView: Error getting birthday months', ['exception' => $e->getMessage()]);
        }

        $birthDayMonthChartJSON = json_encode($birthDayMonthChartArray, JSON_THROW_ON_ERROR);

        try {
            $rsTeachers = $sundaySchoolService->getClassByRole($iGroupId, 'Teacher');
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->error('SundaySchool ClassView: Error getting teachers', ['exception' => $e->getMessage()]);
        }

        try {
            $thisClassChildren = $sundaySchoolService->getKidsFullDetails($iGroupId);
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->error('SundaySchool ClassView: Error getting kids full details', ['exception' => $e->getMessage()]);
        }

        $TeachersEmails = [];
        $KidsEmails     = [];
        $ParentsEmails  = [];

        foreach ($thisClassChildren as $child) {
            if (!empty($child['dadEmail'])) {
                $ParentsEmails[] = $child['dadEmail'];
            }
            if (!empty($child['momEmail'])) {
                $ParentsEmails[] = $child['momEmail'];
            }
            if (!empty($child['kidEmail'])) {
                $KidsEmails[] = $child['kidEmail'];
            }
        }

        foreach ($rsTeachers as $teacher) {
            $TeachersEmails[] = $teacher->getEmail();
        }

        $sMailtoDelimiter = AuthenticationManager::getCurrentUser()->getUserConfigString('sMailtoDelimiter');
        $allEmails        = array_unique([...$ParentsEmails, ...$KidsEmails, ...$TeachersEmails]);
        $roleEmails       = [
            'Parents'  => implode($sMailtoDelimiter, $ParentsEmails) . ',',
            'Teachers' => implode($sMailtoDelimiter, $TeachersEmails) . ',',
            'Kids'     => implode($sMailtoDelimiter, $KidsEmails) . ',',
        ];
        $sEmailLink = implode($sMailtoDelimiter, $allEmails) . ',';

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'              => SystemURLs::getRootPath(),
            'sPageTitle'             => gettext('Sunday School') . ': ' . $iGroupName,
            'sPageSubtitle'          => gettext('View class roster, teacher assignments, and contact information'),
            'aBreadcrumbs'           => PageHeader::breadcrumbs([
                [gettext('Groups'), '/groups/dashboard'],
                [gettext('Sunday School'), '/groups/sundayschool/dashboard'],
                [$iGroupName],
            ]),
            'iGroupId'               => $iGroupId,
            'iGroupName'             => $iGroupName,
            'birthDayMonthChartJSON' => $birthDayMonthChartJSON,
            'rsTeachers'             => $rsTeachers,
            'thisClassChildren'      => $thisClassChildren,
            'TeachersEmails'         => $TeachersEmails,
            'KidsEmails'             => $KidsEmails,
            'ParentsEmails'          => $ParentsEmails,
            'sMailtoDelimiter'       => $sMailtoDelimiter,
            'roleEmails'             => $roleEmails,
            'sEmailLink'             => urlencode($sEmailLink),
            'canEmail'               => AuthenticationManager::getCurrentUser()->isEmailEnabled(),
        ];

        return $renderer->render($response, 'sundayschool/class-view.php', $pageArgs);
    });

})->add(new SundaySchoolEnabledMiddleware());
