<?php

namespace ChurchCRM\Emails\notifications;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\Person;

class BirthdayEmail extends BaseEmail
{
    private Person $person;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(array $toAddresses, Person $person)
    {
        $this->person = $person;
        parent::__construct($toAddresses);
        $this->mail->Subject = SystemConfig::getValue('sChurchName') . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject(): string
    {
        return gettext('Happy Birthday') . ', ' . $this->person->getFullName() . '!';
    }

    public function getTokens(): array
    {
        $ageString = $this->person->getAge();
        $age = ($ageString !== null && ctype_digit($ageString)) ? (int) $ageString : null;

        $body = gettext('Happy Birthday') . ', ' . $this->person->getFullName() . '!';
        $body .= "\n\n";
        if ($age !== null) {
            $body .= sprintf(gettext('Wishing you a wonderful %d%s birthday!'), $age, $this->getOrdinalSuffix($age));
        } else {
            $body .= gettext('Wishing you a wonderful birthday!');
        }
        $body .= "\n\n" . SystemConfig::getValue('sChurchName') . ' ' . gettext('is thinking of you today.');

        $myTokens = [
            'toName' => $this->person->getFullName(),
            'body'   => $body,
        ];

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    private function getOrdinalSuffix(int $number): string
    {
        if ($number % 100 >= 11 && $number % 100 <= 13) {
            return 'th';
        }

        switch ($number % 10) {
            case 1:
                return 'st';
            case 2:
                return 'nd';
            case 3:
                return 'rd';
            default:
                return 'th';
        }
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
        return $this->getSubSubject();
    }
}