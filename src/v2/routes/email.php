<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MailChimpService;
use Slim\Views\PhpRenderer;
use ChurchCRM\Slim\Middleware\AdminRoleAuthMiddleware;

$app->group('/email', function () {
    $this->get('/debug', 'testEmailConnection')->add(new AdminRoleAuthMiddleware());
    $this->get('/dashboard', 'getEmailDashboard');
});

function getEmailDashboard(Request $request, Response $response, array $args) {
    $renderer = new PhpRenderer('templates/email/');
    $mailchimp = new MailChimpService();

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('eMail Dashboard'),
        'isMailChimpActive' => $mailchimp->isActive(),
        'mailChimpLists' => $mailchimp->getLists()
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}

function testEmailConnection(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/email/');

    $mailer = new \PHPMailer();
    $message = "";

    if (!empty(SystemConfig::getValue("sSMTPHost")) && !empty(ChurchMetaData::getChurchEmail())) {
        $mailer->IsSMTP();
        $mailer->CharSet = 'UTF-8';
        $mailer->Timeout = intval(SystemConfig::getValue("iSMTPTimeout"));
        $mailer->Host = SystemConfig::getValue("sSMTPHost");
        if (SystemConfig::getBooleanValue("bSMTPAuth")) {
            $mailer->SMTPAuth = true;
            echo "SMTP Auth Used </br>";
            $mailer->Username = SystemConfig::getValue("sSMTPUser");
            $mailer->Password = SystemConfig::getValue("sSMTPPass");
        }

        $mailer->SMTPDebug = 3;
        $mailer->Subject = "Test SMTP Email";
        $mailer->setFrom(ChurchMetaData::getChurchEmail());
        $mailer->addAddress(ChurchMetaData::getChurchEmail());
        $mailer->Body = "test email";
        $mailer->Debugoutput = "html";
    } else {
        $message = gettext("SMTP Host is not setup, please visit the settings page");
    }

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext("Debug Email Connection"),
        'mailer' => $mailer,
        'message' => $message
    ];

    return $renderer->render($response, 'debug.php', $pageArgs);
}
