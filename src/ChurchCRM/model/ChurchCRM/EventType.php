<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\EventType as BaseEventType;
use ChurchCRM\model\ChurchCRM\GroupQuery;

/**
 * Skeleton subclass for representing a row from the 'event_types' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class EventType extends BaseEventType
{
    public function isSundaySchool(): bool
    {
        $groupId = $this->getGroupId();
        if (empty($groupId)) {
            return false;
        }

        $group = GroupQuery::create()->findPk((int) $groupId);

        return $group !== null && $group->isSundaySchool();
    }
}
