<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\FamilyQuery;

class EnvelopeReportService
{
    /**
     * Families that have an envelope number, in the Envelope Manager's order.
     *
     * The family set and the name order are the manager's own (EnvelopeService::
     * familyEnvelopes()): a classification limits the list to families with at least
     * one member in it, and the name sort is the manager's label order. The envelope
     * sort is numeric by envelope number, as the manager sorts it. The printed family
     * text stays the salutation, as before.
     *
     * @return array<int, array{envelope: int, family: string}>
     */
    public static function assignments(int $classificationId = 0, string $sortBy = 'envelope'): array
    {
        $families = EnvelopeService::familyEnvelopes($classificationId);
        $orderedIds = [];
        foreach ($families as $familyId => $family) {
            if ($family['envelope'] > 0) {
                $orderedIds[] = $familyId;
            }
        }
        if ($orderedIds === []) {
            return [];
        }

        $rows = [];
        foreach (FamilyQuery::create()->filterById($orderedIds)->find() as $family) {
            $rows[(int) $family->getId()] = ['envelope' => (int) $family->getEnvelope(), 'family' => (string) $family->getSalutation()];
        }

        if ($sortBy === 'name') {
            $sorted = [];
            foreach ($orderedIds as $familyId) {
                if (isset($rows[$familyId])) {
                    $sorted[] = $rows[$familyId];
                }
            }

            return $sorted;
        }

        $sorted = array_values($rows);
        usort($sorted, static fn (array $a, array $b): int => $a['envelope'] <=> $b['envelope']);

        return $sorted;
    }
}
