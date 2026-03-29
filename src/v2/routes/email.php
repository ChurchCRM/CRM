<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/email', function (RouteCollectorProxy $group): void {
    $group->get('/dashboard', 'getEmailDashboardMVC');
    $group->get('/duplicate', 'getDuplicateEmailsMVC');
    $group->get('/missing', 'getFamiliesWithoutEmailsMVC');
    $group->get('', 'getEmailDashboardMVC');
    $group->get('/', 'getEmailDashboardMVC');
});

function getEmailDashboardMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/email/');

    $emailSettingTooltips = [];
    foreach (['sSMTPHost', 'iSMTPTimeout', 'sPHPMailerSMTPSecure', 'bPHPMailerAutoTLS', 'bSMTPAuth', 'sSMTPUser', 'sSMTPPass', 'sToEmailAddress'] as $key) {
        $item = SystemConfig::getConfigItem($key);
        $emailSettingTooltips[$key] = $item?->getTooltip() ?? '';
    }

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('eMail Dashboard'),
        'sPageSubtitle' => gettext('Manage email tools and SMTP configuration'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Email')],
        ]),
        'sSettingsCollapseId' => 'emailSettings',
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Debug'), 'url' => '/admin/system/debug/email', 'icon' => 'fa-stethoscope', 'adminOnly' => true],
            ['label' => gettext('Email Settings'), 'collapse' => '#emailSettings', 'icon' => 'fa-sliders', 'adminOnly' => true],
        ]),
        'emailSettingTooltips' => $emailSettingTooltips,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}

function getDuplicateEmailsMVC(Request $request, Response $response, array $args): Response
{
    return renderPage($response, 'templates/email/', 'duplicate.php', _('Duplicate Emails'));
}

function getFamiliesWithoutEmailsMVC(Request $request, Response $response, array $args): Response
{
    return renderPage($response, 'templates/email/', 'without.php', _('Families Without Emails'));
}
