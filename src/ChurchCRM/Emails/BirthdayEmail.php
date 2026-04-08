<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;

class BirthdayEmail extends BaseEmail
{
    private Person $person;

    public function __construct(Person $person)
    {
        $this->person = $person;
        parent::__construct([$person->getEmail()]);
        $this->mail->Subject = SystemConfig::getValue('sChurchName') . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject(): string
    {
        return gettext('Happy Birthday') . ', ' . $this->person->getFirstName() . '!';
    }

    public function getTokens(): array
    {
        $age = $this->person->getAge();
        $body = gettext('Wishing you a wonderful birthday and a blessed year ahead!');
        if (!empty($age)) {
            $body = sprintf(gettext('Wishing you a wonderful %s birthday and a blessed year ahead!'), $age);
        }

        $myTokens = [
            'toName' => $this->person->getFirstName(),
            'body'   => $body,
        ];

        return array_merge($this->getCommonTokens(), $myTokens);
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
