<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Utils\FiscalYearUtils;

class GivingStatementEmail extends BaseEmail
{
    public function __construct(array $toAddresses, string $familyName, int $year, string $pdf, string $filename)
    {
        parent::__construct($toAddresses);

        $churchName = ChurchMetaData::getChurchName();
        $yearLabel = FiscalYearUtils::getFiscalYearDatesForCalendarYear($year)['label'];
        $this->mail->Subject = sprintf(gettext('%s Giving Statement'), $yearLabel);
        $this->mail->isHTML(false);
        $this->mail->Body = sprintf(
            gettext("Dear %s Family,\n\nPlease find your %s giving statement from %s attached to this email.\n\nIf you have any questions, please contact the church office.\n\nThank you for your generosity."),
            $familyName,
            $yearLabel,
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
