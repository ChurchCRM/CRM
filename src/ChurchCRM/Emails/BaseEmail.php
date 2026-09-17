<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Service\SystemService;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use PHPMailer\PHPMailer\PHPMailer;

abstract class BaseEmail
{
    protected PHPMailer $mail;
    protected Environment $twig;

    /** Record the message is addressed to, when the sender knows it (see setLogContext). */
    protected ?int $logPersonId = null;
    protected ?int $logFamilyId = null;
    /** User who wrote the message (composer); NULL for automated sends. */
    protected ?int $logSentByUserId = null;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(array $toAddresses)
    {
        $this->setConnection();
        $this->mail->setFrom(ChurchMetaData::getChurchEmail(), ChurchMetaData::getChurchName());
        foreach ($toAddresses as $email) {
            $this->mail->addAddress($email);
        }

        $loader = new FilesystemLoader(__DIR__ . '/../../templates/email');
        $this->twig = new Environment($loader);
    }

    private function setConnection(): void
    {
        $this->mail = new PHPMailer();
        $this->mail->IsSMTP();
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Timeout = SystemConfig::getIntValue('iSMTPTimeout');
        $this->mail->Host = SystemConfig::getValue('sSMTPHost');
        $this->mail->SMTPAutoTLS = SystemConfig::getBooleanValue('bPHPMailerAutoTLS');
        $this->mail->SMTPSecure = SystemConfig::getValue('sPHPMailerSMTPSecure');
        if (SystemConfig::getBooleanValue('bSMTPAuth')) {
            $this->mail->SMTPAuth = true;
            $this->mail->Username = SystemConfig::getValue('sSMTPUser');
            $this->mail->Password = SystemConfig::getValue('sSMTPPass');
        }
        // Keep SMTP debug off by default to avoid verbose SMTP dumps in logs.
        // If deeper SMTP troubleshooting is needed enable PHPMailer debug explicitly.
        $this->mail->SMTPDebug = 0;
    }

    /**
     * Sends the message and writes one email-history row per recipient
     * (email_log_eml), whatever the outcome. Returns false, without trying,
     * when email is disabled or SMTP is misconfigured so callers never crash.
     */
    public function send(): bool
    {
        if (!SystemConfig::isEmailEnabled()) {
            $this->logSend(EmailLogService::STATUS_SKIPPED);

            return false;
        }

        $sent = false;
        try {
            $sent = $this->mail->send();
        } finally {
            $this->logSend($sent ? EmailLogService::STATUS_SENT : EmailLogService::STATUS_FAILED);
        }

        return $sent;
    }

    /**
     * Tells the history log which record this message is for and who sent it.
     * Without it the log falls back to matching the address against people and families.
     */
    public function setLogContext(?int $personId = null, ?int $familyId = null, ?int $sentByUserId = null): static
    {
        $this->logPersonId = $personId;
        $this->logFamilyId = $familyId;
        $this->logSentByUserId = $sentByUserId;

        return $this;
    }

    /**
     * Short kind stored in the history log (eml_Kind), e.g. composer, birthday,
     * account.reset. Defaults to the class name without the Email suffix.
     */
    protected function getLogKind(): string
    {
        $short = (new \ReflectionClass($this))->getShortName();

        return strtolower((string) preg_replace('/Email$/', '', $short));
    }

    /**
     * Whether the rendered body may be kept in the history log. Off by default: account
     * emails carry passwords and one-time tokens. Only the composer turns it on.
     */
    protected function logsBody(): bool
    {
        return false;
    }

    private function logSend(string $status): void
    {
        (new EmailLogService())->logSend(
            $this->mail,
            $status,
            $this->getLogKind(),
            $this->logsBody(),
            $this->logPersonId,
            $this->logFamilyId,
            $this->logSentByUserId,
        );
    }

    public function getError(): string
    {
        return $this->mail->ErrorInfo;
    }

    public function addStringAttachment(string $string, string $filename): void
    {
        $this->mail->addStringAttachment($string, $filename);
    }

    protected function buildMessage(): string
    {
        return $this->twig->render($this->getTemplateName(), $this->getTokens());
    }

    protected function getTemplateName(): string
    {
        return 'BaseEmail.html.twig';
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
            'churchLogo'           => ChurchMetaData::getChurchLogoURL(),
            // Footer: the same lines as the Display Preview on Admin -> Church Information.
            'churchStreet'         => ChurchMetaData::getChurchAddress(),
            'churchCityLine'       => self::getChurchCityLine(),
            'churchCountry'        => self::getChurchCountryName(),
            'churchWebSite'        => ChurchMetaData::getChurchWebSite(),
            'dear'                 => SystemConfig::getValue('sDear'),
            'confirmSincerely'     => SystemConfig::getValue('sConfirmSincerely'),
            'confirmSigner'        => SystemConfig::getValue('sConfirmSigner'),
            'copyrightDate'        => SystemService::getCopyrightDate(),
            'preheader'            => $this->getPreheader(),
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

    /** "City, State Zip" as the Church Information preview shows it; empty when unset. */
    private static function getChurchCityLine(): string
    {
        $cityState = implode(', ', array_filter([ChurchMetaData::getChurchCity(), ChurchMetaData::getChurchState()]));
        $zip = ChurchMetaData::getChurchZip();

        return trim($cityState . ($zip !== '' ? ' ' . $zip : ''));
    }

    /** Country display name for the configured code, or the raw value when unknown. */
    private static function getChurchCountryName(): string
    {
        $code = ChurchMetaData::getChurchCountry();
        if ($code === '') {
            return '';
        }

        return Countries::getNames()[$code] ?? $code;
    }

    /**
     * @return array<string, string>
     */
    abstract public function getTokens(): array;

    abstract protected function getFullURL(): string;

    abstract protected function getButtonText(): string;

    protected function getPreheader(): string
    {
        return SystemConfig::getValue('sEmailPreheader') ?: '';
    }
}
