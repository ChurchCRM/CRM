<?php

namespace ChurchCRM\Emails\notifications;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\Utils\CurrencyFormatter;

class DepositPostedEmail extends NotificationEmail
{
    private Deposit $deposit;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(array $toAddresses, Deposit $deposit)
    {
        $this->deposit = $deposit;
        parent::__construct($toAddresses, 'Deposit Posted');
        $this->mail->Subject = $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject(): string
    {
        return gettext('New Deposit Posted');
    }

    public function getTokens(): array
    {
        $date = $this->deposit->getDate('Y-m-d');
        $total = $this->deposit->getTotalAmount();
        $formattedTotal = CurrencyFormatter::format($total);
        $body = gettext('A new deposit has been posted.') . "\n\n" .
            gettext('Date') . ': ' . $date . "\n" .
            gettext('Total') . ': ' . $formattedTotal . "\n\n" .
            gettext('See the deposit at') . ' ' . $this->getFullURL();

        $myTokens = [
            'toName' => gettext('Notification Recipients'),
            'body' => $body,
        ];

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    protected function getFullURL(): string
    {
        return SystemURLs::getRootPath() . '/finance/deposit/' . $this->deposit->getId();
    }

    protected function getButtonText(): string
    {
        return gettext('View Deposit');
    }

    protected function getPreheader(): string
    {
        return $this->getSubSubject();
    }

    protected function getLogKind(): string
    {
        return 'deposit-posted';
    }
}
