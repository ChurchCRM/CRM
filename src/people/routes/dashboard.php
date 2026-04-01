<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\view\PageHeader;
use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Redirect /people root to /people/dashboard
$app->get('/', function (Request $request, Response $response): Response {
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/people/dashboard')
        ->withStatus(302);
});

// People Dashboard (replaces PeopleDashboard.php)
$app->get('/dashboard', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $dashboardService = new DashboardService();
    $familyCount      = $dashboardService->getFamilyCount();
    $groupStats       = $dashboardService->getGroupStats();
    $dashboardStats   = $dashboardService->getDashboardStats();

    $personCount         = $dashboardStats['personCount'];
    $classificationStats = $dashboardStats['classificationStats'];
    $genderStats         = $dashboardStats['genderStats'];
    $simpleGenderStats   = $dashboardStats['simpleGenderStats'];
    $ageGroupStats       = $dashboardStats['ageGroupStats'];
    $familyRoleStats     = $dashboardStats['familyRoleStats'];

    // Build email list grouped by role (active families, excluding "Do Not Email" property)
    $currentUser        = AuthenticationManager::getCurrentUser();
    $isAdmin            = $currentUser->isAdmin();
    // Email addresses joined with commas (RFC 5321 standard)
    $sEmailLink         = '';
    $roleEmails         = [];

    // Build exclusion set from configured "Do Not Email" property
    $doNotEmailSet = [];
    $doNotEmailPropId = (int) SystemConfig::getValue('iDoNotEmailPropertyId');
    if ($doNotEmailPropId > 0) {
        foreach (RecordPropertyQuery::create()->filterByPropertyId($doNotEmailPropId)->find() as $r) {
            $doNotEmailSet[(int) $r->getRecordId()] = true;
        }
    }

    // Role name map: classification ID → label
    $roleNameMap = [];
    foreach (ListOptionQuery::create()->filterById(1)->find() as $opt) {
        $roleNameMap[(int) $opt->getOptionId()] = $opt->getOptionName();
    }

    $persons = PersonQuery::create()
        ->leftJoinWithFamily()
        ->useQuery('Family')
            ->filterByDateDeactivated(null)
        ->endUse()
        ->find();

    $emailsSeen = [];
    foreach ($persons as $person) {
        $personId = (int) $person->getId();
        if (isset($doNotEmailSet[$personId])) {
            continue;
        }
        $email = (string) $person->getEmail();
        if (empty($email) || isset($emailsSeen[$email])) {
            continue;
        }
        $emailsSeen[$email] = true;
        $sEmailLink .= $email . ',';

        $roleName = $roleNameMap[(int) $person->getClsId()] ?? gettext('Member');
        if (!array_key_exists($roleName, $roleEmails)) {
            $roleEmails[$roleName] = '';
        }
        $roleEmails[$roleName] .= $email . ',';
    }

    // Append default "to" address if configured and not already included
    if ($sEmailLink && SystemConfig::getValue('sToEmailAddress') !== '') {
        $defaultEmail = SystemConfig::getValue('sToEmailAddress');
        if (!stristr($sEmailLink, $defaultEmail)) {
            $sEmailLink .= ',' . $defaultEmail;
        }
    }

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('People Dashboard'),
        'sPageSubtitle'      => gettext('Manage families, people, and demographic information'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('People')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons(array_filter([
            ['label' => gettext('Person Properties'), 'url' => '/PropertyList.php?Type=p', 'icon' => 'fa-list'],
            ['label' => gettext('Family Properties'), 'url' => '/PropertyList.php?Type=f', 'icon' => 'fa-house'],
            ['label' => gettext('Custom Fields'), 'url' => '/PersonCustomFieldsEditor.php', 'icon' => 'fa-pen-field'],
            $isAdmin ? ['label' => gettext('People Settings'), 'collapse' => '#peopleSettings', 'icon' => 'fa-sliders', 'adminOnly' => true] : null,
        ])),
        'sSettingsCollapseId' => $isAdmin ? 'peopleSettings' : null,
        'familyCount'        => $familyCount,
        'groupStats'         => $groupStats,
        'personCount'        => $personCount,
        'classificationStats' => $classificationStats,
        'simpleGenderStats'  => $simpleGenderStats,
        'ageGroupStats'      => $ageGroupStats,
        'familyRoleStats'    => $familyRoleStats,
        'sEmailLink'         => urlencode($sEmailLink),
        'roleEmails'         => $roleEmails,
        'sMailtoDelimiter'   => ',', // Legacy — will be removed in future cleanup
        'isAdmin'            => $isAdmin,
        'canEmail'           => $currentUser->isEmailEnabled(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
