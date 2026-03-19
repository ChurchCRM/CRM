<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Plugin\PluginManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Get Started — onboarding wizard page
$app->get('/get-started', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Get Started'),
    ];

    return $renderer->render($response, 'get-started.php', $pageArgs);
});

// Match /admin root path
$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    // Setup progress checklist
    $churchName    = SystemConfig::getValue('sChurchName');
    $churchAddress = SystemConfig::getValue('sChurchAddress');
    $churchEmail   = SystemConfig::getValue('sChurchEmail');

    $hasChurchInfo  = $churchName !== 'Some Church' && $churchName !== '' && $churchAddress !== '' && $churchEmail !== '';
    $hasData        = FamilyQuery::create()->select('Id')->findOne() !== null
        || PersonQuery::create()->select('Id')->findOne() !== null;
    $hasEmail       = SystemConfig::hasValidMailServerSettings();
    $userCount      = UserQuery::create()->count();
    $hasMultiUser   = $userCount > 1;
    // PluginManager::init() is normally called by Header.php (during view render),
    // but we need the plugin state before rendering, so initialize it here.
    // init() is idempotent — calling it twice is safe.
    PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');
    $hasPlugins = PluginManager::hasAnyActivePlugin();

    $completedSteps = (int)$hasChurchInfo + (int)$hasData + (int)$hasEmail + (int)$hasMultiUser + (int)$hasPlugins;
    $totalSteps     = 5;

    $setupChecklist = [
        [
            'done'  => $hasChurchInfo,
            'label' => gettext('Church Information'),
            'desc'  => gettext('Set your church name, address, and contact email'),
            'link'  => SystemURLs::getRootPath() . '/admin/system/church-info',
            'icon'  => 'fa-church',
        ],
        [
            'done'  => $hasData,
            'label' => gettext('Add Your Data'),
            'desc'  => gettext('Import, restore, or manually enter your congregation'),
            'link'  => SystemURLs::getRootPath() . '/admin/get-started',
            'icon'  => 'fa-users',
        ],
        [
            'done'  => $hasEmail,
            'label' => gettext('Configure Email'),
            'desc'  => gettext('Connect an SMTP server so ChurchCRM can send emails'),
            'link'  => SystemURLs::getRootPath() . '/SystemSettings.php',
            'icon'  => 'fa-envelope',
        ],
        [
            'done'    => $hasMultiUser,
            'label'   => gettext('Invite Your Team'),
            'desc'    => gettext('Add staff or volunteers as system users'),
            'link'    => SystemURLs::getRootPath() . '/admin/system/users',
            'icon'    => 'fa-user-plus',
        ],
        [
            'done'  => $hasPlugins,
            'label' => gettext('Enable Plugins'),
            'desc'  => gettext('Extend ChurchCRM with MailChimp, backups, and more'),
            'link'  => SystemURLs::getRootPath() . '/plugins/management',
            'icon'  => 'fa-plug',
        ],
    ];

    $pageArgs = [
        'sRootPath'        => SystemURLs::getRootPath(),
        'sPageTitle'       => gettext('Admin Dashboard'),
        'setupChecklist'   => $setupChecklist,
        'completedSteps'   => $completedSteps,
        'totalSteps'       => $totalSteps,
        'allDone'          => $completedSteps === $totalSteps,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
