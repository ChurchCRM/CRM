<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\KioskAssignmentQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\Service\SundaySchoolService;
use Propel\Runtime\ActiveQuery\Criteria;
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

        // ---- Phone contact lists for SMS / copy-to-clipboard actions ---- //
        $teacherPhonesRaw = [];
        $parentPhonesRaw  = [];
        $phoneSeen        = [];
        foreach ($rsTeachers as $teacher) {
            $phone = (string) $teacher->getCellPhone();
            if (!empty($phone) && !isset($phoneSeen[$phone])) {
                $phoneSeen[$phone]  = true;
                $teacherPhonesRaw[] = $phone;
            }
        }
        foreach ($thisClassChildren as $child) {
            foreach (['dadCellPhone', 'momCellPhone'] as $field) {
                $phone = (string) ($child[$field] ?? '');
                if (!empty($phone) && !isset($phoneSeen[$phone])) {
                    $phoneSeen[$phone] = true;
                    $parentPhonesRaw[] = $phone;
                }
            }
        }
        $allPhonesRaw    = array_merge($teacherPhonesRaw, $parentPhonesRaw);
        $makeSmsLink     = static function (array $phones): string {
            $cleaned = array_filter(array_map(static fn($p) => preg_replace('/[^\d+]/', '', $p), $phones));
            return empty($cleaned) ? '' : 'sms:' . implode(',', $cleaned);
        };
        $sPhoneLink      = implode(', ', $allPhonesRaw);
        $sSmsLink        = $makeSmsLink($allPhonesRaw);
        $sTeacherSmsLink = $makeSmsLink($teacherPhonesRaw);
        $sParentSmsLink  = $makeSmsLink($parentPhonesRaw);

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
            'kioskEventSet'          => $kioskEventSet,
            'birthDayMonthChartJSON' => $birthDayMonthChartJSON,
            'rsTeachers'             => $rsTeachers,
            'thisClassChildren'      => $thisClassChildren,
            'TeachersEmails'         => $TeachersEmails,
            'KidsEmails'             => $KidsEmails,
            'ParentsEmails'          => $ParentsEmails,
            'sMailtoDelimiter'       => $sMailtoDelimiter,
            'roleEmails'             => $roleEmails,
            'sEmailLink'             => urlencode($sEmailLink),
            'canEmail'               => $currentUser->isEmailEnabled(),
            'sPhoneLink'             => $sPhoneLink,
            'sSmsLink'               => $sSmsLink,
            'sTeacherSmsLink'        => $sTeacherSmsLink,
            'sParentSmsLink'         => $sParentSmsLink,
        ];

        return $renderer->render($response, 'sundayschool/class-view.php', $pageArgs);
    });

})->add(new SundaySchoolEnabledMiddleware());
