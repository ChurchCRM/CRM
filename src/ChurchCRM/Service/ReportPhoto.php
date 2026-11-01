<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;

final class ReportPhoto
{
    /** @return array{photo: string, initials: string} */
    public static function forPerson(Person $person): array
    {
        $photo = (string) $person->getPhoto()->getPhotoURI();

        return [
            'photo' => is_file($photo) ? $photo : '',
            'initials' => $person->getInitial(SystemConfig::getIntValue('iPersonInitialStyle')),
        ];
    }
}
