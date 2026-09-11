<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\LoggerUtils;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use PHPMailer\PHPMailer\PHPMailer;

abstract class BaseEmail
{
    protected PHPMailer $mail;
    protected Environment $twig;

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

    public function send(): bool
    {
        if (SystemConfig::isEmailEnabled()) {
            $this->applyReplyTo();

            return $this->mail->send();
        }

        return false; // email disabled or SMTP misconfigured — skip so we don't crash.
    }

    /**
     * Hand the stored Reply-To to PHPMailer, exactly once per instance.
     *
     * Applied at send time rather than in setReplyTo() so a caller can change
     * its mind before sending, and so resending the same instance does not
     * accumulate duplicate Reply-To headers.
     */
    private function applyReplyTo(): void
    {
        if ($this->replyTo === null) {
            return;
        }

        $this->mail->clearReplyTos();
        $this->mail->addReplyTo($this->replyTo['address'], $this->replyTo['name']);
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
