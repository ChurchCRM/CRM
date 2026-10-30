<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\KioskAssignmentQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\ClassAttendanceService;
use ChurchCRM\Service\ClassListService;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\GivingStatementService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\PhotoBookService;
use ChurchCRM\Service\SundaySchoolService;
use ChurchCRM\Slim\Middleware\Request\Setting\SundaySchoolEnabledMiddleware;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;
use ChurchCRM\Volunteer\Service\VolunteerClassLinkService;
use Propel\Runtime\ActiveQuery\Criteria;
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

    // Class ids named by ?GroupID=1,2 or, when none is named, every Sunday School class.
    $classIds = function (Request $request): array {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($request->getQueryParams()['GroupID'] ?? '')))));
        if ($ids !== []) {
            return $ids;
        }

        return array_map('intval', GroupQuery::create()->filterByType(4)->orderByName()->find()->getPrimaryKeys(false));
    };

    // Class list: teachers, then students with family contacts, for each class. One click, no form.
    $group->get('/reports/class-list', function (Request $request, Response $response) use ($classIds) {
        $pdf = (new MpdfRenderer())->renderToString('class-list', [
            'classes' => ClassListService::classes($classIds($request), true),
            'fiscal_year' => FinancialService::formatFiscalYear(FiscalYearUtils::getCurrentFiscalYearId()),
            'with_pictures' => true,
        ]);
        $fileLabel = trim(ChurchMetaData::getChurchName() . ' ClassList ' . date(SystemConfig::getValue('sDateFilenameFormat')));
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', GivingStatementService::contentDisposition($fileLabel));
    });

    // Class attendance sheet: students and teachers of each chosen class against the Sundays of the school year.
    // Needs the group-management role of this app.
    $group->get('/reports/class-attendance', function (Request $request, Response $response) {
        $query = $request->getQueryParams();
        $groupIds = array_slice(array_values(array_filter(array_map('intval', explode(',', (string) ($query['GroupID'] ?? ''))))), 0, 50);
        $first = InputUtils::filterDate($query['FirstSunday'] ?? '');
        $last = InputUtils::filterDate($query['LastSunday'] ?? '');
        if ($first === '' || $last === '' || $first === '1970-01-01' || $last === '1970-01-01' || $last < $first) {
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/groups/sundayschool/reports?error=dates')
                ->withStatus(302);
        }

        $noSchool = [];
        for ($i = 1; $i <= 8; $i++) {
            $date = InputUtils::filterDate($query['NoSchool' . $i] ?? '');
            if ($date !== '' && $date !== '1970-01-01') {
                $noSchool[] = $date;
            }
        }

        $withPictures = InputUtils::filterInt($query['pictures'] ?? '') === 1;
        $sundays = ClassAttendanceService::sundays($first, $last, $noSchool);
        $fiscalYearId = InputUtils::filterInt($query['FYID'] ?? '') ?: FiscalYearUtils::getCurrentFiscalYearId();

        $pdf = (new MpdfRenderer())->renderToString('class-attendance', [
            'classes' => ClassAttendanceService::classes(
                $groupIds,
                InputUtils::filterInt($query['AllRoles'] ?? '') === 1,
                $withPictures,
                InputUtils::filterInt($query['ExtraStudents'] ?? ''),
                InputUtils::filterInt($query['ExtraTeachers'] ?? '')
            ),
            'sundays' => $sundays,
            'months' => ClassAttendanceService::months($sundays),
            'widths' => ClassAttendanceService::columnWidths(count($sundays), $withPictures),
            'with_pictures' => $withPictures,
            'fiscal_year' => FinancialService::formatFiscalYear($fiscalYearId),
        ], true);

        $fileLabel = 'ClassAttendance ' . date(SystemConfig::getValue('sDateFilenameFormat'));
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', GivingStatementService::contentDisposition($fileLabel));
    });

    // Photo book: teachers then students of each class. Needs the group-management role of this app.
    $group->get('/reports/photo-book', function (Request $request, Response $response) use ($classIds) {
        $groupIds = $classIds($request);

        $pdf = (new MpdfRenderer())->renderToString('photo-book', [
            'sections' => PhotoBookService::sections($groupIds),
        ]);
        $className = count($groupIds) === 1 ? (string) GroupQuery::create()->findOneById($groupIds[0])?->getName() : '';
        $fileLabel = trim(ChurchMetaData::getChurchName() . ' ' . $className . ' ' . date(SystemConfig::getValue('sDateFilenameFormat')));
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', GivingStatementService::contentDisposition($fileLabel));
    });

    // Sunday School Dashboard
    $group->get('/dashboard', function (Request $request, Response $response) {
        $dashboardService    = new DashboardService();
        $sundaySchoolService = new SundaySchoolService();

        try {
            $groupStats         = $dashboardService->getGroupStats();
            $kidsWithoutClasses = $sundaySchoolService->getKidsWithoutClasses();
            $classStats         = $sundaySchoolService->getClassStats();
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error('SundaySchool Dashboard: Error loading data', ['exception' => $e->getMessage()]);
            $groupStats         = ['sundaySchoolClasses' => 0];
            $kidsWithoutClasses = [];
            $classStats         = [];
        }

        $classes  = $groupStats['sundaySchoolClasses'];
        $teachers = 0;
        $kids     = 0;

        foreach ($classStats as $class) {
            $kids     += $class['kids'];
            $teachers += $class['teachers'];
        }

        // Batch-fetch gender/family stats in a single query instead of N+1
        try {
            $studentStats = $sundaySchoolService->getDashboardStudentStats();
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error('SundaySchool Dashboard: Error loading student stats', ['exception' => $e->getMessage()]);
            $studentStats = ['maleKids' => 0, 'femaleKids' => 0, 'familyCount' => 0];
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
            'families'           => $studentStats['familyCount'],
            'maleKids'           => $studentStats['maleKids'],
            'femaleKids'         => $studentStats['femaleKids'],
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

        // ---- Group metadata (properties, status, description) ---- //
        $currentUser      = AuthenticationManager::getCurrentUser();
        $bCanManageGroups = $currentUser->isManageGroupsEnabled();

        // Assigned properties (record2property_r2p, class = 'g')
        $rsAssignedRows        = [];
        $rsAssignedPropertyIds = [];
        foreach (RecordPropertyQuery::create()->filterByRecordId($iGroupId)->find() as $rec) {
            $propDef = $rec->getProperty();
            if ($propDef === null || $propDef->getProClass() !== 'g') {
                continue;
            }
            $propType = $propDef->getPropertyType();
            $rsAssignedRows[] = [
                'pro_ID'     => $rec->getPropertyId(),
                'pro_Name'   => $propDef->getProName(),
                'r2p_Value'  => $rec->getPropertyValue(),
                'pro_Prompt' => (string) $propDef->getProPrompt(),
                'prt_Name'   => $propType ? $propType->getPrtName() : '',
                'pro_prt_ID' => $propDef->getProPrtId(),
            ];
            $rsAssignedPropertyIds[] = $rec->getPropertyId();
        }
        usort($rsAssignedRows, fn ($a, $b) => [$a['prt_Name'], $a['pro_Name']] <=> [$b['prt_Name'], $b['pro_Name']]);

        $allGroupPropertyDefs = PropertyQuery::create()->filterByProClass('g')->orderByProName()->find();

        // Group-specific custom properties (groupprop_master)
        $groupSpecificProps = [];
        $aPropTypes = [
            1 => gettext('True / False'), 2 => gettext('Date'), 3 => gettext('Text Field (50 char)'),
            4 => gettext('Text Field (100 char)'), 5 => gettext('Text Field (long)'), 6 => gettext('Year'),
            7 => gettext('Season'), 8 => gettext('Number'), 9 => gettext('Person from Group'),
            10 => gettext('Money'), 11 => gettext('Phone Number'), 12 => gettext('Custom Drop-Down List'),
        ];
        if ($thisGroup !== null && $thisGroup->getHasSpecialProps()) {
            $groupSpecificProps = GroupPropMasterQuery::create()
                ->filterByGrpId($iGroupId)
                ->orderByPropId()
                ->find();
        }

        // ---- Events linked to this group (via event_audience) ---- //
        $groupEvents = EventQuery::create()
            ->useEventAudienceQuery()
                ->filterByGroupId($iGroupId)
            ->endUse()
            ->orderByStart(Criteria::DESC)
            ->find();

        // Find today's active event for this group (for smart "Take Attendance" button)
        $todayStr = date('Y-m-d');
        $todayEvent = null;
        $todayEventAttendance = 0;
        foreach ($groupEvents as $evt) {
            if ((int) $evt->getInActive() === 1) {
                continue;
            }
            $evtDate = $evt->getStart() ? $evt->getStart()->format('Y-m-d') : '';
            if ($evtDate === $todayStr) {
                $todayEvent = $evt;
                $todayEventAttendance = EventAttendQuery::create()
                    ->filterByEventId($evt->getId())
                    ->filterByCheckinDate(null, Criteria::NOT_EQUAL)
                    ->count();
                break;
            }
        }

        // Pre-fetch kiosk assignments for all event IDs in one query
        $kioskEventSet = [];
        $eventIds = [];
        foreach ($groupEvents as $evt) {
            $eventIds[] = $evt->getId();
        }
        if (!empty($eventIds)) {
            foreach (KioskAssignmentQuery::create()->filterByEventId($eventIds)->find() as $ka) {
                $kioskEventSet[(int) $ka->getEventId()] = true;
            }
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

        $birthDayMonthChartJSON = json_encode($birthDayMonthChartArray);

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

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'              => SystemURLs::getRootPath(),
            'sPageTitle'             => gettext('Sunday School') . ': ' . $iGroupName,
            'sPageSubtitle'          => gettext('View class roster, teacher assignments, and contact information'),
            'aBreadcrumbs'           => PageHeader::breadcrumbs([
                [gettext('Groups'), '/groups/dashboard'],
                [gettext('Sunday School'), '/groups/sundayschool/dashboard'],
                [$iGroupName, '/groups/view/' . $iGroupId],
            ]),
            'sPageHeaderButtons'     => PageHeader::buttons([
                ['label' => gettext('Group View'), 'url' => '/groups/view/' . $iGroupId, 'icon' => 'fa-users', 'adminOnly' => false],
                ['label' => gettext('Sunday School'), 'url' => '/groups/sundayschool/dashboard', 'icon' => 'fa-chalkboard-teacher', 'adminOnly' => false],
            ]),
            'iGroupId'               => $iGroupId,
            'iGroupName'             => $iGroupName,
            'thisGroup'              => $thisGroup,
            'bCanManageGroups'       => $bCanManageGroups,
            'rsAssignedRows'         => $rsAssignedRows,
            'rsAssignedPropertyIds'  => $rsAssignedPropertyIds,
            'allGroupPropertyDefs'   => $allGroupPropertyDefs,
            'groupSpecificProps'     => $groupSpecificProps,
            'aPropTypes'             => $aPropTypes,
            'groupEvents'            => $groupEvents,
            'todayEvent'             => $todayEvent,
            'todayEventAttendance'   => $todayEventAttendance,
            'kioskEventSet'          => $kioskEventSet,
            'birthDayMonthChartJSON' => $birthDayMonthChartJSON,
            'rsTeachers'             => $rsTeachers,
            'thisClassChildren'      => $thisClassChildren,
            'canEmail'               => $currentUser->isEmailEnabled(),
            // Volunteer v2 (D23): null unless a volunteer team writes this class's teachers.
            'aTeacherLink'           => VolunteerClassLinkService::describeLink($iGroupId, $currentUser),
        ];

        return $renderer->render($response, 'sundayschool/class-view.php', $pageArgs);
    });

    // Sunday School Reports
    $group->get('/reports', function (Request $request, Response $response) {
        $currentUser = AuthenticationManager::getCurrentUser();
        $userRecord = UserQuery::create()->findPk($currentUser->getId());

        $groups = GroupQuery::create()
            ->orderByName(Criteria::ASC)
            ->filterByType(4)
            ->find();

        $calDates = [
            'firstSunday' => $userRecord->getCalStart()?->format(SystemConfig::getValue('sDatePickerFormat')) ?? '',
            'lastSunday'  => $userRecord->getCalEnd()?->format(SystemConfig::getValue('sDatePickerFormat')) ?? '',
        ];
        for ($i = 1; $i <= 8; $i++) {
            $getter = 'getCalNoSchool' . $i;
            $calDates['noSchool' . $i] = $userRecord->$getter()?->format(SystemConfig::getValue('sDatePickerFormat')) ?? '';
        }

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'          => SystemURLs::getRootPath(),
            'sPageTitle'         => gettext('Attendance Sheet'),
            'sPageSubtitle'      => gettext('A weekly sign-in grid for the school year, one column for each Sunday'),
            'aBreadcrumbs'       => PageHeader::breadcrumbs([
                [gettext('Group Reports'), '/groups/reports'],
                [gettext('Attendance Sheet')],
            ]),
            'groups'             => $groups,
            'selectedGroupId'    => (int) ($request->getQueryParams()['GroupID'] ?? 0),
            'calDates'           => $calDates,
            'sDatePickerFormat'  => SystemConfig::getValue('sDatePickerFormat'),
        ];

        return $renderer->render($response, 'sundayschool/reports.php', $pageArgs);
    });

    $group->post('/reports', function (Request $request, Response $response) {
        $post = $request->getParsedBody();
        $currentUser = AuthenticationManager::getCurrentUser();
        $userRecord = UserQuery::create()->findPk($currentUser->getId());

        $iFYID = FiscalYearUtils::getCurrentFiscalYearId();

        $dFirstSunday = InputUtils::filterDate($post['FirstSunday'] ?? '');
        $dLastSunday = InputUtils::filterDate($post['LastSunday'] ?? '');

        // Save calendar settings to user record
        $userRecord->setCalStart($dFirstSunday ?: null);
        $userRecord->setCalEnd($dLastSunday ?: null);
        for ($i = 1; $i <= 8; $i++) {
            $val = InputUtils::filterDate($post['NoSchool' . $i] ?? '');
            $setter = 'setCalNoSchool' . $i;
            $userRecord->$setter($val ?: null);
        }
        $userRecord->save();

        // Build group ID list
        $groupIds = [];
        if (!empty($post['GroupID'])) {
            foreach ($post['GroupID'] as $grp) {
                $groupIds[] = InputUtils::filterInt($grp);
            }
        }
        $aGrpID = implode(',', $groupIds);

        if (empty($groupIds) || $aGrpID === '0') {
            // Re-render form with error - redirect back to GET
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/groups/sundayschool/reports?error=nogroup')
                ->withStatus(302);
        }

        $iExtraStudents = InputUtils::filterInt($post['ExtraStudents'] ?? '0');
        $iExtraTeachers = InputUtils::filterInt($post['ExtraTeachers'] ?? '0');

        $url = 'groups/sundayschool/reports/class-attendance?GroupID=' . $aGrpID
            . '&FYID=' . $iFYID
            . '&FirstSunday=' . $dFirstSunday
            . '&LastSunday=' . $dLastSunday;
        for ($i = 1; $i <= 8; $i++) {
            $val = InputUtils::filterDate($post['NoSchool' . $i] ?? '');
            if ($val) {
                $url .= '&NoSchool' . $i . '=' . $val;
            }
        }
        if ($iExtraStudents) {
            $url .= '&ExtraStudents=' . $iExtraStudents;
        }
        if ($iExtraTeachers) {
            $url .= '&ExtraTeachers=' . $iExtraTeachers;
        }
        RedirectUtils::redirect($url);

        return $response;
    });

})->add(new SundaySchoolEnabledMiddleware());
