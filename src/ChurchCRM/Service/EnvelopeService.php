<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;

/**
 * Reads and writes family donation envelope numbers (family_fam.fam_Envelope).
 * Envelope number 0 means the family has no envelope.
 */
class EnvelopeService
{
    /** Largest value the signed MEDIUMINT fam_Envelope column holds. */
    public const MAX_ENVELOPE = 8388607;

    /**
     * Families with their stored envelope numbers, ordered by family label.
     * A classification id limits the list to families with at least one member
     * in that classification; 0 lists every family.
     *
     * @return array<int, array{label: string, envelope: int}> keyed by family id
     */
    public static function familyEnvelopes(int $classificationId): array
    {
        $labels = (new PersonService())->getFamilyList(
            SystemConfig::getValue('sDirRoleHead'),
            SystemConfig::getValue('sDirRoleSpouse'),
            $classificationId,
            null,
            true
        );
        if ($labels === []) {
            return [];
        }
        asort($labels);

        $envelopes = [];
        $rows = FamilyQuery::create()
            ->filterById(array_keys($labels))
            ->select(['Id', 'Envelope'])
            ->find();
        foreach ($rows as $row) {
            $envelopes[(int) $row['Id']] = (int) $row['Envelope'];
        }

        $families = [];
        foreach ($labels as $familyId => $label) {
            $families[$familyId] = ['label' => $label, 'envelope' => $envelopes[$familyId] ?? 0];
        }

        return $families;
    }

    /**
     * Parses a typed envelope number. A blank value is 0 (no envelope).
     *
     * @return int|null the number, or null when it is not a whole number from 0 to MAX_ENVELOPE
     */
    public static function parseEnvelope(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        if (preg_match('/^\d{1,9}$/', $value) !== 1) {
            return null;
        }
        $envelope = (int) $value;

        return $envelope <= self::MAX_ENVELOPE ? $envelope : null;
    }

    /**
     * Stores the given envelope numbers, writing only the families whose
     * stored number differs.
     *
     * @param array<int, int> $envelopes envelope number keyed by family id
     *
     * @return int number of families updated
     */
    public static function saveChanged(array $envelopes): int
    {
        if ($envelopes === []) {
            return 0;
        }

        $updated = 0;
        foreach (FamilyQuery::create()->filterById(array_keys($envelopes))->find() as $family) {
            $envelope = $envelopes[(int) $family->getId()];
            if ((int) $family->getEnvelope() !== $envelope) {
                $family->setEnvelope($envelope);
                $family->save();
                $updated++;
            }
        }

        return $updated;
    }
}
