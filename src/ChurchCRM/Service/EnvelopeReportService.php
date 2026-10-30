<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class EnvelopeReportService
{
    /**
     * Families that have an envelope number, in envelope order.
     *
     * @return array<int, array{envelope: int, family: string}>
     */
    public static function assignments(): array
    {
        $rows = [];
        foreach (FamilyQuery::create()->filterByEnvelope(0, Criteria::GREATER_THAN)->orderByEnvelope()->find() as $family) {
            $rows[] = ['envelope' => (int) $family->getEnvelope(), 'family' => (string) $family->getSalutation()];
        }

        return $rows;
    }
}
