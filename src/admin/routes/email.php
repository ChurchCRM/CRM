<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\TestEmail;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

function adminEmailSettingsFromConfig(): array
{
    return [
        'bEnabledEmail'         => SystemConfig::getBooleanValue('bEnabledEmail'),
        'sSMTPHost'             => SystemConfig::getValue('sSMTPHost'),
        'iSMTPTimeout'          => SystemConfig::getValue('iSMTPTimeout'),
        'sPHPMailerSMTPSecure'  => trim((string) SystemConfig::getValue('sPHPMailerSMTPSecure')),
        'bPHPMailerAutoTLS'     => SystemConfig::getBooleanValue('bPHPMailerAutoTLS'),
        'bSMTPAuth'             => SystemConfig::getBooleanValue('bSMTPAuth'),
        'sSMTPUser'             => SystemConfig::getValue('sSMTPUser'),
        'sToEmailAddress'       => SystemConfig::getValue('sToEmailAddress'),
        'iDoNotEmailPropertyId' => (string) SystemConfig::getValue('iDoNotEmailPropertyId'),
        'sEmailPreheader'       => SystemConfig::getValue('sEmailPreheader'),
    ];
}

function adminEmailSettingsPageArgs(array $settings, string $message, string $messageClass, string $validationError): array
{
    return [
        'sRootPath'            => SystemURLs::getRootPath(),
        'sPageTitle'           => gettext('Email Settings'),
        'sPageSubtitle'        => gettext('SMTP and delivery settings'),
        'aBreadcrumbs'         => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Email Settings')],
        ]),
        'sPageHeaderButtons'   => PageHeader::buttons([
            ['label' => gettext('Email Log'), 'url' => '/admin/email/log', 'icon' => 'fa-envelope-open-text'],
            ['label' => gettext('Email Debug'), 'url' => '/admin/email/debug', 'icon' => 'fa-stethoscope'],
        ]),
        'bEmailEnabled'        => SystemConfig::getBooleanValue('bEnabledEmail'),
        'bSmtpConfigured'      => SystemConfig::hasValidMailServerSettings(),
        'bMailchimpConfigured' => PluginManager::getPlugin('mailchimp')?->isConfigured() ?? false,
        'settings'             => $settings,
        'hasSmtpPassword'      => SystemConfig::getValue('sSMTPPass') !== '',
        'encryptionChoices'    => ['' => gettext('None'), 'tls' => 'TLS', 'ssl' => 'SSL'],
        'personProperties'     => PropertyQuery::create()->filterByProClass('p')->orderByProName()->find(),
        'sGlobalMessage'       => $message,
        'sGlobalMessageClass'  => $messageClass,
        'validationError'      => $validationError,
    ];
}

$app->get('/email', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $message      = '';
    $messageClass = 'success';
    if (isset($_SESSION['sGlobalMessage'])) {
        $message      = $_SESSION['sGlobalMessage'];
        $messageClass = $_SESSION['sGlobalMessageClass'] ?? 'success';
        unset($_SESSION['sGlobalMessage'], $_SESSION['sGlobalMessageClass']);
    }

    return $renderer->render($response, 'email.php', adminEmailSettingsPageArgs(adminEmailSettingsFromConfig(), $message, $messageClass, ''));
});

$app->post('/email', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $body = $request->getParsedBody();

    $settings = [
        'bEnabledEmail'         => isset($body['bEnabledEmail']),
        'sSMTPHost'             => trim((string) ($body['sSMTPHost'] ?? '')),
        'iSMTPTimeout'          => trim((string) ($body['iSMTPTimeout'] ?? '')),
        'sPHPMailerSMTPSecure'  => trim((string) ($body['sPHPMailerSMTPSecure'] ?? '')),
        'bPHPMailerAutoTLS'     => isset($body['bPHPMailerAutoTLS']),
        'bSMTPAuth'             => isset($body['bSMTPAuth']),
        'sSMTPUser'             => trim((string) ($body['sSMTPUser'] ?? '')),
        'sToEmailAddress'       => trim((string) ($body['sToEmailAddress'] ?? '')),
        'iDoNotEmailPropertyId' => trim((string) ($body['iDoNotEmailPropertyId'] ?? '')),
        'sEmailPreheader'       => trim((string) ($body['sEmailPreheader'] ?? '')),
    ];
    $password = (string) ($body['sSMTPPass'] ?? '');

    // Without authentication the credentials have no purpose; drop them.
    if (!$settings['bSMTPAuth']) {
        $settings['sSMTPUser'] = '';
        $password = '';
    }

    $validationError = '';
    $timeout = $settings['iSMTPTimeout'];
    if (!ctype_digit($timeout) || (int) $timeout < 1 || (int) $timeout > 600) {
        $validationError = gettext('SMTP timeout must be a whole number of seconds between 1 and 600.');
    } elseif (!in_array($settings['sPHPMailerSMTPSecure'], ['', 'tls', 'ssl'], true)) {
        $validationError = gettext('Choose None, TLS or SSL for encryption.');
    } elseif ($settings['bSMTPAuth'] && $settings['sSMTPUser'] === '') {
        $validationError = gettext('SMTP username is required when authentication is on.');
    } elseif ($settings['sToEmailAddress'] !== '' && !filter_var($settings['sToEmailAddress'], FILTER_VALIDATE_EMAIL)) {
        $validationError = gettext('Copy Church Email must be a valid email address.');
    } elseif ($settings['iDoNotEmailPropertyId'] !== '' && PropertyQuery::create()->filterByProClass('p')->findPk((int) $settings['iDoNotEmailPropertyId']) === null) {
        $validationError = gettext('Choose an existing person property for Do Not Email.');
    }

    if ($validationError !== '') {
        return $renderer->render(
            $response->withStatus(422),
            'email.php',
            adminEmailSettingsPageArgs($settings, $validationError, 'danger', $validationError)
        );
    }

    foreach (['bEnabledEmail', 'bPHPMailerAutoTLS', 'bSMTPAuth'] as $name) {
        SystemConfig::setValue($name, $settings[$name] ? '1' : '0');
    }
    foreach (['sSMTPHost', 'iSMTPTimeout', 'sPHPMailerSMTPSecure', 'sSMTPUser', 'sToEmailAddress', 'iDoNotEmailPropertyId', 'sEmailPreheader'] as $name) {
        SystemConfig::setValue($name, $settings[$name]);
    }
    if (!$settings['bSMTPAuth']) {
        SystemConfig::setValue('sSMTPPass', '');
    } elseif ($password !== '') {
        SystemConfig::setValue('sSMTPPass', $password);
    }

    $_SESSION['sGlobalMessage']      = gettext('Email settings saved successfully');
    $_SESSION['sGlobalMessageClass'] = 'success';

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/admin/email')
        ->withStatus(303);
})->add(new InputSanitizationMiddleware([
    'sSMTPHost'             => 'text',
    'iSMTPTimeout'          => 'text',
    'sPHPMailerSMTPSecure'  => 'text',
    'sSMTPUser'             => 'text',
    'sToEmailAddress'       => 'text',
    'iDoNotEmailPropertyId' => 'text',
    'sEmailPreheader'       => 'text',
]));

$app->get('/email/log', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $log = new EmailLogService();

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('Email Log'),
        'sPageSubtitle'      => gettext('Every email ChurchCRM sent, newest first'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Email Log')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Email Settings'), 'url' => '/admin/email', 'icon' => 'fa-sliders'],
            ['label' => gettext('Email Debug'), 'url' => '/admin/email/debug', 'icon' => 'fa-stethoscope'],
        ]),
        'recentEmails'       => $log->getRecent(null, 1, 20),
        'failedEmailCount'   => $log->getRecent(EmailLogService::STATUS_FAILED, 1, 1)['total'],
        'queuedEmailCount'   => $log->getRecent(EmailLogService::STATUS_QUEUED, 1, 1)['total'],
    ];

    return $renderer->render($response, 'email-log.php', $pageArgs);
});

// Email Debug page — sends a test message to the church's own email
// address so an admin can confirm the SMTP settings work end-to-end.
$app->get('/email/debug', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $configError = null;
    $configErrorFixUrl = null; // page where the setting actually lives
    $churchEmail = ChurchMetaData::getChurchEmail();
    if (!SystemConfig::getBooleanValue('bEnabledEmail')) {
        $configError = gettext('Email is disabled. Enable it in Email Settings before testing.');
        $configErrorFixUrl = SystemURLs::getRootPath() . '/admin/email';
    } elseif (empty(SystemConfig::getValue('sSMTPHost'))) {
        $configError = gettext('SMTP Host is not configured.');
        $configErrorFixUrl = SystemURLs::getRootPath() . '/admin/email';
    } elseif (empty($churchEmail)) {
        // Church email is configured on the Church Information page, not
        // Church Information, where the church email is set.
        $configError = gettext('Church Email address is not set — the test email has nowhere to go.');
        $configErrorFixUrl = SystemURLs::getRootPath() . '/admin/system/church-info';
    }

    // Gather SMTP settings up-front so the page can render them even when
    // we abort before the send (config errors) — admins still get the
    // summary of what IS configured.
    $smtpSettings = [
        'host'     => SystemConfig::getValue('sSMTPHost') ?: '(not set)',
        'secure'   => SystemConfig::getValue('sPHPMailerSMTPSecure') ?: gettext('(none)'),
        'auth'     => SystemConfig::getBooleanValue('bSMTPAuth') ? gettext('Yes') : gettext('No'),
        'username' => SystemConfig::getBooleanValue('bSMTPAuth') ? SystemConfig::getValue('sSMTPUser') : null,
        'autoTLS'  => SystemConfig::getBooleanValue('bPHPMailerAutoTLS') ? gettext('Yes') : gettext('No'),
        'timeout'  => SystemConfig::getIntValue('iSMTPTimeout') . 's',
    ];

    $sendResult = [
        'attempted' => false,
        'success'   => false,
        'error'     => null,
        'debugLog'  => '',
        'from'      => null,
        'fromName'  => null,
        'to'        => null,
    ];

    if ($configError === null) {
        $email = new TestEmail([$churchEmail]);
        $sendResult['attempted'] = true;
        $sendResult['from']     = $churchEmail;
        $sendResult['fromName'] = ChurchMetaData::getChurchName();
        $sendResult['to']       = $churchEmail;

        // Capture PHPMailer's HTML debug output (Debugoutput='html' echoes
        // directly during send) so we can show it inline instead of bleeding
        // into the rendered page.
        ob_start();
        try {
            $sendResult['success'] = $email->send();
        } catch (\Throwable $e) {
            $sendResult['success'] = false;
            $sendResult['error']   = $e->getMessage();
        }
        $sendResult['debugLog'] = ob_get_clean() ?: '';

        if (!$sendResult['success'] && empty($sendResult['error'])) {
            $sendResult['error'] = $email->getError();
        }
    }

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Email Debug'),
        'sPageSubtitle' => gettext('Send a test message to verify your SMTP configuration'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Email Settings'), '/admin/email'],
            [gettext('Debug')],
        ]),
        'configError'  => $configError,
        'configErrorFixUrl' => $configErrorFixUrl,
        'smtpSettings' => $smtpSettings,
        'sendResult'   => $sendResult,
        'emailDashboardUrl' => SystemURLs::getRootPath() . '/admin/email',
    ];

    return $renderer->render($response, 'email-debug.php', $pageArgs);
});
