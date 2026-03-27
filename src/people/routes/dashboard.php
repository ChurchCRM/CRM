<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\Propel;
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
    $sMailtoDelimiter   = $currentUser->getUserConfigString('sMailtoDelimiter');
    $sEmailLink         = '';
    $roleEmails         = [];

    $con  = Propel::getConnection();
    $stmt = $con->prepare(
        "SELECT per_Email, fam_Email, lst_OptionName AS virt_RoleName
         FROM person_per
         LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
         INNER JOIN list_lst ON lst_ID = 1 AND per_cls_ID = lst_OptionID
         WHERE fam_DateDeactivated IS NULL
           AND per_ID NOT IN (
               SELECT per_ID FROM person_per
               INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
               INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email'
           )"
    );
    $stmt->execute();

    while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
        [$perEmail, , $roleName] = $row;
        if ($perEmail && !stristr($sEmailLink, $perEmail)) {
            $sEmailLink .= $perEmail . $sMailtoDelimiter;
            if (!array_key_exists($roleName, $roleEmails)) {
                $roleEmails[$roleName] = '';
            }
            $roleEmails[$roleName] .= $perEmail;
        }
    }

    // Append default "to" address if configured and not already included
    if ($sEmailLink && SystemConfig::getValue('sToEmailAddress') !== '') {
        $defaultEmail = SystemConfig::getValue('sToEmailAddress');
        if (!stristr($sEmailLink, $defaultEmail)) {
            $sEmailLink .= $sMailtoDelimiter . $defaultEmail;
        }
    }

    $selfRegColor = 'bg-danger';
    $selfRegText  = gettext('Disabled');
    if (SystemConfig::getBooleanValue('bEnableSelfRegistration')) {
        $selfRegColor = 'bg-success';
        $selfRegText  = gettext('Enabled');
    }

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('People Dashboard'),
        'sPageSubtitle'      => gettext('Manage families, people, and demographic information'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('People')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Person Properties'), 'url' => '/PropertyList.php?Type=p', 'icon' => 'fa-list'],
            ['label' => gettext('Family Properties'), 'url' => '/PropertyList.php?Type=f', 'icon' => 'fa-house'],
            ['label' => gettext('Custom Fields'), 'url' => '/PersonCustomFieldsEditor.php', 'icon' => 'fa-pen-field'],
        ]),
        'familyCount'        => $familyCount,
        'groupStats'         => $groupStats,
        'personCount'        => $personCount,
        'classificationStats' => $classificationStats,
        'simpleGenderStats'  => $simpleGenderStats,
        'ageGroupStats'      => $ageGroupStats,
        'familyRoleStats'    => $familyRoleStats,
        'sEmailLink'         => urlencode($sEmailLink),
        'roleEmails'         => $roleEmails,
        'sMailtoDelimiter'   => $sMailtoDelimiter,
        'selfRegColor'       => $selfRegColor,
        'selfRegText'        => $selfRegText,
        'canEmail'           => $currentUser->isEmailEnabled(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
