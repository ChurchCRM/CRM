<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\Pledge;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The donor of a pledge or payment: the person it names, otherwise its family.
 * Finance summaries group rows by donor so per-person giving is never folded into the family.
 */
class PledgeDonor
{
    public static function key(Pledge $pledge): ?string
    {
        if ((int) $pledge->getPerId() > 0) {
            return 'p' . (int) $pledge->getPerId();
        }
        if ((int) $pledge->getFamId() > 0) {
            return 'f' . (int) $pledge->getFamId();
        }

        return null;
    }

    /**
     * @param iterable<Pledge> ...$pledgeSets
     * @return array<string, array{donor_key: string, person_id: int, person_name: string, family_id: int, family_name: string, envelope: string, display_name: string}>
     */
    public static function describe(iterable ...$pledgeSets): array
    {
        $byKey = [];
        foreach ($pledgeSets as $pledges) {
            foreach ($pledges as $pledge) {
                $key = self::key($pledge);
                if ($key !== null && !isset($byKey[$key])) {
                    $byKey[$key] = $pledge;
                }
            }
        }

        $personIds = [];
        foreach ($byKey as $pledge) {
            if ((int) $pledge->getPerId() > 0) {
                $personIds[] = (int) $pledge->getPerId();
            }
        }
        $personNames = [];
        foreach (PersonQuery::create()->filterById($personIds, Criteria::IN)->find() as $person) {
            $personNames[(int) $person->getId()] = $person->getFullName();
        }

        $donors = [];
        foreach ($byKey as $key => $pledge) {
            $family = $pledge->getFamily();
            $personId = (int) $pledge->getPerId();
            $personName = $personNames[$personId] ?? '';
            $familyName = $family !== null ? (string) $family->getName() : '';
            $donors[$key] = [
                'donor_key'    => $key,
                'person_id'    => $personId,
                'person_name'  => $personName,
                'family_id'    => $family !== null ? (int) $family->getId() : 0,
                'family_name'  => $familyName,
                'envelope'     => $family !== null ? (string) ($family->getEnvelope() ?? '') : '',
                'display_name' => $personName !== '' ? $personName : $familyName,
            ];
        }

        return $donors;
    }
}
