<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use PHPMailer\PHPMailer\PHPMailer;

abstract class BaseEmail
{
    protected PHPMailer $mail;
    protected Environment $twig;

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

    public function send(): bool
    {
        if (SystemConfig::isEmailEnabled()) {
            return $this->mail->send();
        }

        return false; // email disabled or SMTP misconfigured — skip so we don't crash.
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
            // The link is rebuilt in the template from a literal scheme plus the validated
            // host/path, so a configured value can never inject another URL scheme.
            'churchWebSiteHost'    => self::getChurchWebSiteParts()['host'],
            'churchWebSiteSecure'  => self::getChurchWebSiteParts()['secure'],
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

    /**
     * The configured website split into a literal-scheme flag and the rest of the URL.
     * Only http(s) URLs with a host qualify; anything else yields an empty host and no link.
     *
     * @return array{host: string, secure: bool}
     */
    private static function getChurchWebSiteParts(): array
    {
        $site = trim(ChurchMetaData::getChurchWebSite());
        if ($site !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $site)) {
            $site = 'https://' . $site;
        }
        $parts = $site !== '' ? parse_url($site) : false;
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($parts === false || !in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            return ['host' => '', 'secure' => true];
        }
        $host = $parts['host']
            . (isset($parts['port']) ? ':' . (int) $parts['port'] : '')
            . ($parts['path'] ?? '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '');

        return ['host' => $host, 'secure' => $scheme === 'https'];
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
