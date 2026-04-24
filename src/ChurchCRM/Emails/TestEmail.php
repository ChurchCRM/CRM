<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;

/**
 * Diagnostic email fired from /admin/system/debug/email to verify SMTP
 * settings end-to-end. Uses the shared BaseEmail.html.twig chrome so the
 * test message looks identical to every other transactional email — same
 * church logo, same header, same footer — rather than being an orphan
 * plaintext body that could render fine while every real email breaks.
 */
class TestEmail extends BaseEmail
{
    public function __construct(array $toAddresses)
    {
        parent::__construct($toAddresses);
        $this->mail->Subject = $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());

        // Verbose SMTP debug output so the debug page can capture and
        // display the full handshake. Kept here (not in BaseEmail) so
        // only the test email is noisy; real emails stay quiet.
        $this->mail->SMTPDebug = 3;
        $this->mail->Debugoutput = 'html';
    }

    public function getTokens(): array
    {
        $body = gettext('This is a ChurchCRM test email.') . "\r\n\r\n"
            . gettext('If you can read this message in your inbox, your SMTP configuration is working correctly.');

        return array_merge($this->getCommonTokens(), [
            'toName' => ChurchMetaData::getChurchName() ?: gettext('Administrator'),
            'body'   => $body,
        ]);
    }

    protected function getSubSubject(): string
    {
        return gettext('ChurchCRM Test Email');
    }

    protected function getPreheader(): string
    {
        return gettext('SMTP configuration test — if this arrived, your setup works.');
    }

    protected function getFullURL(): string
    {
        return '';
    }

    protected function getButtonText(): string
    {
        return '';
    }
}
