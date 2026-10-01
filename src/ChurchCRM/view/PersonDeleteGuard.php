<?php

namespace ChurchCRM\view;

use ChurchCRM\Service\PersonService;
use ChurchCRM\Utils\InputUtils;

class PersonDeleteGuard
{
    /**
     * Attributes that grey out a `.delete-person` control when the signed-in user
     * may not delete this person's login; empty when deletion is allowed.
     */
    public static function attributes(int $personId): string
    {
        $reason = PersonService::getLoginDeletionBlockedReasons()[$personId] ?? null;

        return $reason === null
            ? ''
            : ' aria-disabled="true" title="' . InputUtils::escapeAttribute($reason) . '"';
    }
}
