<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;

class GivingStatementEmail extends BaseEmail
{
    public function __construct(array $toAddresses, string $familyName, int $year, string $pdf, string $filename)
    {
        parent::__construct($toAddresses);

        $churchName = ChurchMetaData::getChurchName();
        $this->mail->Subject = sprintf(gettext('%d Giving Statement'), $year);
        $this->mail->isHTML(false);
        $this->mail->Body = sprintf(
            gettext("Dear %s Family,\n\nPlease find your %d giving statement from %s attached to this email.\n\nIf you have any questions, please contact the church office.\n\nThank you for your generosity."),
            $familyName,
            $year,
            $churchName
        );
        $this->addStringAttachment($pdf, $filename);
    }

    public function getTokens(): array
    {
        return [];
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
