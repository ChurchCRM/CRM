<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;
use PHPMailer\PHPMailer\PHPMailer;

abstract class BaseEmail
{
    /** @var PHPMailer */
    protected $mail;
    protected \Mustache_Engine $mustache;

    public function __construct($toAddresses)
    {
        $this->setConnection();
        $this->mail->setFrom(ChurchMetaData::getChurchEmail(), ChurchMetaData::getChurchName());
        foreach ($toAddresses as $email) {
            $this->mail->addAddress($email);
        }

        // use .html instead of .mustache for default template extension
        $options = ['extension' => '.html'];

        $this->mustache = new Mustache_Engine(['loader' => new Mustache_Loader_FilesystemLoader(SystemURLs::getDocumentRoot() . '/views/email', $options)]);
    }

    private function setConnection(): void
    {
        $this->mail = new PHPMailer();
        $this->mail->IsSMTP();
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Timeout = intval(SystemConfig::getValue('iSMTPTimeout'));
        $this->mail->Host = SystemConfig::getValue('sSMTPHost');
        $this->mail->SMTPAutoTLS = SystemConfig::getBooleanValue('bPHPMailerAutoTLS');
        $this->mail->SMTPSecure = SystemConfig::getValue('sPHPMailerSMTPSecure');
        if (SystemConfig::getBooleanValue('bSMTPAuth')) {
            $this->mail->SMTPAuth = true;
            $this->mail->Username = SystemConfig::getValue('sSMTPUser');
            $this->mail->Password = SystemConfig::getValue('sSMTPPass');
        }
        if (SystemConfig::debugEnabled()) {
            $this->mail->SMTPDebug = 1;
            $this->mail->Debugoutput = 'error_log';
        }
    }

    public function send()
    {
        if (SystemConfig::hasValidMailServerSettings()) {
            return $this->mail->send();
        }

        return false; // we don't have a valid setting so let us make sure we don't crash.
    }

    public function getError()
    {
        return $this->mail->ErrorInfo;
    }

    public function addStringAttachment($string, $filename): void
    {
        $this->mail->addStringAttachment($string, $filename);
    }

    protected function buildMessage()
    {
        return $this->mustache->render($this->getMustacheTemplateName(), $this->getTokens());
    }

    protected function getMustacheTemplateName(): string
    {
        return 'BaseEmail';
    }

    /**
     * @return array<string, string>
     */
    protected function getCommonTokens(): array
    {
        $commonTokens = [
            'toEmails'             => $this->mail->getToAddresses(),
            'churchName'           => ChurchMetaData::getChurchName(),
            'churchAddress'        => ChurchMetaData::getChurchFullAddress(),
            'churchPhone'          => ChurchMetaData::getChurchPhone(),
            'churchEmail'          => ChurchMetaData::getChurchEmail(),
            'churchCRMURL'         => SystemURLs::getURL(),
            'dear'                 => SystemConfig::getValue('sDear'),
            'confirmSincerely'     => SystemConfig::getValue('sConfirmSincerely'),
            'confirmSigner'        => SystemConfig::getValue('sConfirmSigner'),
            'copyrightDate'        => SystemService::getCopyrightDate(),
            'buttonNotWorkingText' => gettext("If that doesn't work, copy and paste the following link in your browser"),
            'emailErrorText'       => gettext("You received this email because we received a request for activity on your account. If you didn't request this you can safely delete this email."),
            'stopEmailText'        => gettext('To stop receiving these emails, you can email'),
        ];

        if (!empty($this->getFullURL())) {
            $buttonTokens = [
                'fullURL'    => $this->getFullURL(),
                'buttonText' => $this->getButtonText(),
            ];
            $commonTokens = array_merge($commonTokens, $buttonTokens);
        }

        return $commonTokens;
    }

    abstract public function getTokens();

    abstract protected function getFullURL();

    abstract protected function getButtonText();
}
