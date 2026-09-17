<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\LoggerUtils;
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
     * Optional Reply-To, as ['address' => string, 'name' => string].
     *
     * Held here rather than pushed straight into PHPMailer so that the last
     * setReplyTo() call wins and send() adds exactly one Reply-To header.
     * Null — the default — means no Reply-To header at all, which is the
     * historical behaviour of every email this class sends.
     *
     * @var array{address: string, name: string}|null
     */
    private ?array $replyTo = null;

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
     * Set the address a recipient's reply should go to.
     *
     * From stays the church address (SPF/DKIM alignment); this only adds a
     * Reply-To header, so mail about a specific group, event or assignment can
     * be answered by the coordinator responsible for it instead of the church's
     * general inbox.
     *
     * Opt-in: an email that never calls this sends exactly the message it sent
     * before, with no Reply-To header. Calling it more than once replaces the
     * previous value rather than adding a second address.
     *
     * An invalid address is ignored with a logged warning rather than throwing —
     * a bad coordinator address must not stop the mail from going out.
     */
    public function setReplyTo(string $address, string $name = ''): void
    {
        $address = trim($address);

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            LoggerUtils::getAppLogger()->warning('Ignoring invalid Reply-To address', [
                'address'    => $address,
                'emailClass' => static::class,
            ]);

            return;
        }

        $this->replyTo = ['address' => $address, 'name' => trim($name)];
    }

    /**
     * The configured Reply-To address, or null when none is set.
     */
    public function getReplyTo(): ?string
    {
        return $this->replyTo['address'] ?? null;
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

        $this->applyReplyTo();

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

    /**
     * Hand the stored Reply-To to PHPMailer, exactly once per instance.
     *
     * Applied at send time rather than in setReplyTo() so a caller can change
     * its mind before sending, and so resending the same instance does not
     * accumulate duplicate Reply-To headers.
     *
     * PHPMailer is constructed without exceptions, so addReplyTo() reports
     * failure by returning false and setting ErrorInfo. The filter_var() guard
     * in setReplyTo() does not make that unreachable: PHPMailer applies its own
     * validateAddress(), and an internationalised domain is rejected outright
     * when the intl/mbstring extensions needed to punycode it are missing.
     * Unchecked, the mail would then go out with no Reply-To header, send()
     * would still return true, and getReplyTo() would keep reporting an address
     * that was never applied. Log it and forget the address instead, so the
     * getter stays honest.
     */
    private function applyReplyTo(): void
    {
        if ($this->replyTo === null) {
            return;
        }

        $this->mail->clearReplyTos();
        if (!$this->mail->addReplyTo($this->replyTo['address'], $this->replyTo['name'])) {
            LoggerUtils::getAppLogger()->warning('PHPMailer rejected Reply-To address', [
                'address'    => $this->replyTo['address'],
                'emailClass' => static::class,
                'error'      => $this->mail->ErrorInfo,
            ]);
            $this->replyTo = null;
        }
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
