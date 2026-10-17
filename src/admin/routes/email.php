<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/email', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $log = new EmailLogService();

    $pageArgs = [
        'sRootPath'           => SystemURLs::getRootPath(),
        'sPageTitle'          => gettext('Email Settings'),
        'sPageSubtitle'       => gettext('SMTP, delivery, and every email ChurchCRM sent'),
        'aBreadcrumbs'        => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Email Settings')],
        ]),
        'sSettingsCollapseId' => 'emailSettings',
        'sPageHeaderButtons'  => PageHeader::buttons([
            ['label' => gettext('Debug'), 'url' => '/admin/system/debug/email', 'icon' => 'fa-stethoscope'],
            ['label' => gettext('Email Settings'), 'collapse' => '#emailSettings', 'icon' => 'fa-sliders'],
        ]),
        'bEmailEnabled'       => SystemConfig::getBooleanValue('bEnabledEmail'),
        'bSmtpConfigured'     => SystemConfig::hasValidMailServerSettings(),
        'bMailchimpConfigured' => PluginManager::getPlugin('mailchimp')?->isConfigured() ?? false,
        'recentEmails'        => $log->getRecent(null, 1, 20),
        'failedEmailCount'    => $log->getRecent(EmailLogService::STATUS_FAILED, 1, 1)['total'],
        'queuedEmailCount'    => $log->getRecent(EmailLogService::STATUS_QUEUED, 1, 1)['total'],
        'openSettings'        => ($request->getQueryParams()['settings'] ?? '') === 'open',
    ];

    return $renderer->render($response, 'email.php', $pageArgs);
});
