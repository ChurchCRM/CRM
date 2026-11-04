<?php

namespace ChurchCRM\Portal\Permissions\Rules;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Portal\Permissions\BulkPortalRule;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalRuleGrant;
use ChurchCRM\Portal\Permissions\PortalScope;
use Propel\Runtime\ActiveQuery\Criteria;

/** The baseline every signed-in person has (member sign-in design §8.3, source 1). */
final class EveryoneRule implements BulkPortalRule
{
    public const KEY = 'core.everyone';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function grantsFor(Person $person): iterable
    {
        return self::grants((int) $person->getFamId());
    }

    public function getSourceName(): string
    {
        return gettext('Everyone');
    }

    public function getCapabilityKeys(): array
    {
        return [
            CorePortalCapabilities::PROFILE_EDIT,
            CorePortalCapabilities::DEVICES_MANAGE,
            CorePortalCapabilities::FAMILY_VIEW,
            CorePortalCapabilities::CALENDAR_VIEW,
            CorePortalCapabilities::VOLUNTEER_USE,
        ];
    }

    public function grantsForPeople(array $personIds): iterable
    {
        foreach (array_chunk($personIds, 1000) as $chunk) {
            $rows = PersonQuery::create()->filterById($chunk, Criteria::IN)->select(['Id', 'FamId'])->find();
            foreach ($rows as $row) {
                foreach (self::grants((int) $row['FamId']) as $grant) {
                    yield (int) $row['Id'] => $grant;
                }
            }
        }
    }

    /**
     * @return list<PortalRuleGrant>
     */
    private static function grants(int $familyId): array
    {
        $label = gettext('Everyone');
        $grants = [
            new PortalRuleGrant(CorePortalCapabilities::PROFILE_EDIT, null, $label),
            new PortalRuleGrant(CorePortalCapabilities::DEVICES_MANAGE, null, $label),
        ];
        if ($familyId > 0) {
            $grants[] = new PortalRuleGrant(CorePortalCapabilities::FAMILY_VIEW, PortalScope::family($familyId), $label);
        }
        $grants[] = new PortalRuleGrant(CorePortalCapabilities::CALENDAR_VIEW, null, $label);
        $grants[] = new PortalRuleGrant(CorePortalCapabilities::VOLUNTEER_USE, null, $label);

        return $grants;
    }
}
