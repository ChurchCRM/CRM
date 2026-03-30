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
    $group->get('/missing', 'getPeopleWithoutEmailsMVC');
    $group->get('', 'getEmailDashboardMVC');
    $group->get('/', 'getEmailDashboardMVC');
});

function getEmailDashboardMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/email/');

    $emailSettingTooltips = [];
    foreach (['bEnabledEmail', 'sSMTPHost', 'iSMTPTimeout', 'sPHPMailerSMTPSecure', 'bPHPMailerAutoTLS', 'bSMTPAuth', 'sSMTPUser', 'sSMTPPass', 'sToEmailAddress'] as $key) {
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

function getPeopleWithoutEmailsMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/email/');

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('People Without Emails'),
        'sPageSubtitle' => gettext('People with no personal or work email on record'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Email'), '/v2/email/dashboard'],
            [gettext('People Without Emails')],
        ]),
    ];

    return $renderer->render($response, 'without.php', $pageArgs);
}
