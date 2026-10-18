<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Builds the attendance roster of an event view with one person query for the
 * attendees, check-in actors and check-out actors together (#10540).
 */
class EventAttendanceRosterService
{
    /**
     * @param iterable<\ChurchCRM\model\ChurchCRM\EventAttend> $attendances
     * @param (callable(int[]): iterable<\ChurchCRM\model\ChurchCRM\Person>)|null $personLoader replaces the Propel person query; called once with the unique ids
     *
     * @return array<int, array<string, mixed>>
     */
    public static function build(iterable $attendances, ?callable $personLoader = null): array
    {
        $ids = [];
        foreach ($attendances as $att) {
            foreach ([$att->getPersonId(), $att->getCheckinId(), $att->getCheckoutId()] as $id) {
                if ($id) {
                    $ids[(int) $id] = (int) $id;
                }
            }
        }

        $people = [];
        if ($ids !== []) {
            $ids = array_values($ids);
            $found = $personLoader !== null
                ? $personLoader($ids)
                : PersonQuery::create()->filterById($ids, Criteria::IN)->find();
            foreach ($found as $person) {
                $people[(int) $person->getId()] = $person;
            }
        }

        $roster = [];
        foreach ($attendances as $att) {
            $person = $people[(int) $att->getPersonId()] ?? null;
            if ($person === null) {
                continue;
            }

            $checkinBy = $att->getCheckinId() ? ($people[(int) $att->getCheckinId()] ?? null) : null;
            $checkoutBy = $att->getCheckoutId() ? ($people[(int) $att->getCheckoutId()] ?? null) : null;

            $roster[] = [
                'personId'     => $att->getPersonId(),
                'fullName'     => $person->getFullName(),
                'familyId'     => $person->getFamId(),
                'checkinDate'  => $att->getCheckinDate() ? date_format($att->getCheckinDate(), SystemConfig::getValue('sDateTimeFormat')) : null,
                'checkinBy'    => $checkinBy ? $checkinBy->getFullName() : '',
                'checkoutDate' => $att->getCheckoutDate() ? date_format($att->getCheckoutDate(), SystemConfig::getValue('sDateTimeFormat')) : null,
                'checkoutBy'   => $checkoutBy ? $checkoutBy->getFullName() : '',
                'isCheckedOut' => $att->getCheckoutDate() !== null,
            ];
        }

        return $roster;
    }
}
