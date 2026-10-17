<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\ChurchMetaData;

/**
 * A family's giving statement for one year: a plain-text message with the PDF attached,
 * sent from the Finance menu's Email Tax Doc action (Reports/FamilyTaxReportEmail.php).
 * It does not use the email template, so it has no tokens and no button.
 */
class GivingStatementEmail extends BaseEmail
{
    public function __construct(array $toAddresses, string $familyName, int $year, string $pdf, string $filename)
    {
        parent::__construct($toAddresses);

        $churchName = ChurchMetaData::getChurchName();
        $this->mail->Subject = sprintf(gettext('%s %d Giving Statement'), $churchName, $year);
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
