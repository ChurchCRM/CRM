<?php

namespace ChurchCRM\Portal\Permissions\Rules;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalRule;
use ChurchCRM\Portal\Permissions\PortalRuleGrant;
use ChurchCRM\Portal\Permissions\PortalScope;

/** The baseline every signed-in person has (member sign-in design §8.3, source 1). */
final class EveryoneRule implements PortalRule
{
    public const KEY = 'core.everyone';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function grantsFor(Person $person): iterable
    {
        $label = gettext('Everyone');

        yield new PortalRuleGrant(CorePortalCapabilities::PROFILE_EDIT, null, $label);
        yield new PortalRuleGrant(CorePortalCapabilities::DEVICES_MANAGE, null, $label);

        $familyId = (int) $person->getFamId();
        if ($familyId > 0) {
            yield new PortalRuleGrant(CorePortalCapabilities::FAMILY_VIEW, PortalScope::family($familyId), $label);
        }

        yield new PortalRuleGrant(CorePortalCapabilities::CALENDAR_VIEW, null, $label);
        yield new PortalRuleGrant(CorePortalCapabilities::VOLUNTEER_USE, null, $label);
    }
}
