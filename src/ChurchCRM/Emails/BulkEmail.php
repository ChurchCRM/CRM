<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;

/**
 * Bulk / composer email — used by the in-app email composer modal when SMTP
 * is configured.  Accepts a free-form subject and plain-text body written by
 * the user, wraps it in the standard ChurchCRM transactional-email chrome
 * (BaseEmail.html.twig), and delivers it via PHPMailer just like every other
 * system email.
 *
 * Recipients are passed as an array of email addresses:
 *   - In TO mode  all addresses are added with addAddress().
 *   - In BCC mode all addresses are added with addBCC(); the To: header is set
 *     to the church address (sToEmailAddress / From address) so the message
 *     has a visible To: header rather than an empty one.
 */
class BulkEmail extends BaseEmail
{
    private string $subject;
    private string $body;

    /**
     * @param string[] $toAddresses   Recipient email addresses.
     * @param string   $subject       Email subject line.
     * @param string   $body          Plain-text body (nl2br applied in template).
     * @param bool     $bcc           When true recipients are placed in BCC.
     */
    public function __construct(array $toAddresses, string $subject, string $body, bool $bcc = false)
    {
        if ($bcc) {
            // BaseEmail adds all addresses as To: — bypass that for BCC mode by
            // calling the parent with an empty array and then handling recipients here.
            parent::__construct([]);

            // Give the message a visible To: header (required by RFC 5322).
            $fromAddress = ChurchMetaData::getChurchEmail();
            if ($fromAddress) {
                $this->mail->addAddress($fromAddress, ChurchMetaData::getChurchName());
            }

            foreach ($toAddresses as $email) {
                $this->mail->addBCC($email);
            }
        } else {
            parent::__construct($toAddresses);
        }

        $this->subject = $subject;
        $this->body    = $body;

        $this->mail->Subject = $subject;
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    // ── BaseEmail abstract contract ──────────────────────────────────── //

    public function getTokens(): array
    {
        return array_merge($this->getCommonTokens(), [
            'toName' => '',   // Not personalised for bulk sends.
            'body'   => $this->body,
        ]);
    }

    protected function getFullURL(): string
    {
        return '';
    }

    protected function getButtonText(): string
    {
        return '';
    }

    protected function getPreheader(): string
    {
        // Use the first sentence of the body as the preheader (truncated to 100 chars).
        $firstLine = trim(strtok($this->body, "\n"));
        return mb_strlen($firstLine) > 100 ? mb_substr($firstLine, 0, 97) . '…' : $firstLine;
    }
}
