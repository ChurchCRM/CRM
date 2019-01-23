<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use PHPMailer\PHPMailer\PHPMailer;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;
use Propel\Runtime\ActiveQuery\Criteria;

$app->group('/email', function () {
    $this->get('/debug', 'testEmailConnectionMVC')->add(new AdminRoleAuthMiddleware());
    $this->get('', 'getEmailDashboardMVC');
    $this->get('/', 'getEmailDashboardMVC');
    $this->get('/dashboard', 'getEmailDashboardMVC');
    $this->get('/duplicate', 'getDuplicateEmailsMVC');
    $this->get('/missing', 'getFamiliesWithoutEmailsMVC');
    $this->get('/missingfrommailchimp', 'getEmailsNotInMailChimp');
});

function getEmailDashboardMVC(Request $request, Response $response, array $args)
{
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

function testEmailConnectionMVC(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/email/');

    $mailer = new PHPMailer();
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

function getDuplicateEmailsMVC(Request $request, Response $response, array $args)
{
    return renderPage($response,'templates/email/',  'duplicate.php', _("Duplicate Emails"));
}

function getFamiliesWithoutEmailsMVC(Request $request, Response $response, array $args)
{
    return renderPage($response,'templates/email/',  'without.php', _("Families Without Emails"));
}

function getEmailsNotInMailChimp(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/email/');
    
    $mailchimp = new MailChimpService();
    if (!$mailchimp->isActive())
    {
      return $response->withRedirect(SystemURLs::getRootPath() . "/v2/email");
    }
    $People = \ChurchCRM\PersonQuery::create()
            ->filterByEmail(null, Criteria::NOT_EQUAL)
            ->orderByDateLastEdited(Criteria::DESC)
            ->find();
    
    $missingEmailInMailChimp = array();
    foreach($People as $Person)
    {
        $mailchimpList = $mailchimp->isEmailInMailChimp($Person->getEmail());
        if ($mailchimpList == '') {
           array_push($missingEmailInMailChimp, $Person);
        }
    }
          

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('People not in Mailchimp'),
        'missingEmailInMailChimp' => $missingEmailInMailChimp
    ];

    return $renderer->render($response, 'not-in-mailchimp.php', $pageArgs);
}