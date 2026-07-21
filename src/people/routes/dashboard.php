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
    // Recipient lists collected as arrays; joined into RFC 6068 comma strings below.
    $sEmailList         = [];
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
        $sEmailList[] = $email;

        $roleName = $roleNameMap[(int) $person->getClsId()] ?? gettext('Member');
        $roleEmails[$roleName][] = $email;
    }

    // Join addresses into an RFC 6068 comma string, adding the default "to"
    // once when configured. array_unique() drops any duplicate.
    $defaultTo = SystemConfig::getValue('sToEmailAddress');
    // Only include $defaultTo when there are already person emails to send to;
    // an empty $emails list means no one is reachable, so the button should
    // not appear at all (matching pre-refactor behaviour).
    $joinEmails = static fn(array $emails): string => implode(
        ',',
        array_unique(($defaultTo === '' || $emails === []) ? $emails : [...$emails, $defaultTo]),
    );

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
        'sEmailLink'         => $joinEmails($sEmailList),
        'roleEmails'         => array_map($joinEmails, $roleEmails),
        'isAdmin'            => $isAdmin,
        'canEmail'           => $currentUser->isEmailEnabled(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
