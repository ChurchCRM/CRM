<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/email', function (RouteCollectorProxy $group): void {
    $group->get('/debug', 'testEmailConnectionMVC')->add(AdminRoleAuthMiddleware::class);
    $group->get('/dashboard', 'getEmailDashboardMVC');
    $group->get('/duplicate', 'getDuplicateEmailsMVC');
    $group->get('/missing', 'getFamiliesWithoutEmailsMVC');
    $group->get('/mailchimp/{listId}/unsubscribed', 'getMailListUnSubscribersMVC');
    $group->get('/mailchimp/{listId}/missing', 'getMailListMissingMVC');
    $group->get('', 'getEmailDashboardMVC');
    $group->get('/', 'getEmailDashboardMVC');
});

function getEmailDashboardMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/email/');
    $mailchimp = new MailChimpService();

    $pageArgs = [
        'sRootPath'         => SystemURLs::getRootPath(),
        'sPageTitle'        => gettext('eMail Dashboard'),
        'isMailChimpActive' => $mailchimp->isActive(),
        'mailChimpLists'    => $mailchimp->getLists(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}

function testEmailConnectionMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/email/');

    $mailer = new PHPMailer();
    $message = '';

    if (!empty(SystemConfig::getValue('sSMTPHost')) && !empty(ChurchMetaData::getChurchEmail())) {
        $mailer->IsSMTP();
        $mailer->CharSet = 'UTF-8';
        $mailer->Timeout = intval(SystemConfig::getValue('iSMTPTimeout'));
        $mailer->Host = SystemConfig::getValue('sSMTPHost');
        if (SystemConfig::getBooleanValue('bSMTPAuth')) {
            $mailer->SMTPAuth = true;
            echo 'SMTP Auth Used </br>';
            $mailer->Username = SystemConfig::getValue('sSMTPUser');
            $mailer->Password = SystemConfig::getValue('sSMTPPass');
        }

        $mailer->SMTPDebug = 3;
        $mailer->Subject = 'Test SMTP Email';
        $mailer->setFrom(ChurchMetaData::getChurchEmail());
        $mailer->addAddress(ChurchMetaData::getChurchEmail());
        $mailer->Body = 'test email';
        $mailer->Debugoutput = 'html';
    } else {
        $message = gettext('SMTP Host is not setup, please visit the settings page');
    }

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Debug Email Connection'),
        'mailer'     => $mailer,
        'message'    => $message,
    ];

    return $renderer->render($response, 'debug.php', $pageArgs);
}

function getDuplicateEmailsMVC(Request $request, Response $response, array $args): Response
{
    return renderPage($response, 'templates/email/', 'duplicate.php', _('Duplicate Emails'));
}

function getFamiliesWithoutEmailsMVC(Request $request, Response $response, array $args): Response
{
    return renderPage($response, 'templates/email/', 'without.php', _('Families Without Emails'));
}

function getMailListUnSubscribersMVC(Request $request, Response $response, array $args): Response
{
    $mailchimpService = new MailChimpService();
    $list = $mailchimpService->getList($args['listId']);
    if (!$list) {
        throw new HttpNotFoundException(gettext('Invalid List id') . ': ' . $args['listId']);
    }

    $renderer = new PhpRenderer('templates/email/');
    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => _('People not in') . ' ' . $list['name'],
        'listId'     => $list['id'],
    ];

    return $renderer->render($response, 'mailchimp-unsubscribers.php', $pageArgs);
}

function getMailListMissingMVC(Request $request, Response $response, array $args): Response
{
    $mailchimpService = new MailChimpService();
    $list = $mailchimpService->getList($args['listId']);
    if (!$list) {
        throw new HttpNotFoundException(gettext('Invalid List id') . ': ' . $args['listId']);
    }

    $renderer = new PhpRenderer('templates/email/');
    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => $list['name'] . ' ' . _('Audience not in the ChurchCRM'),
        'listId'     => $list['id'],
    ];

    return $renderer->render($response, 'mailchimp-missing.php', $pageArgs);
}
