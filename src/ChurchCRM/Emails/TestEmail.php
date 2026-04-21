<?php

namespace ChurchCRM\Emails;

class TestEmail extends BaseEmail
{
    public function __construct(array $toAddresses)
    {
        parent::__construct($toAddresses);
        $this->mail->Subject = gettext('Test SMTP Email');
        $this->mail->Body = gettext('This is a ChurchCRM test email confirming that your SMTP configuration is working.');
        $this->mail->SMTPDebug = 3;
        $this->mail->Debugoutput = 'html';
    }

    public function getTokens(): array
    {
        return $this->getCommonTokens();
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
