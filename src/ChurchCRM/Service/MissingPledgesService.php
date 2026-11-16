<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Missing Pledges: active families that pledged in one fiscal year and have no pledge in another.
 * Pledges are summed per family and year, so a family whose pledges cancel out has not pledged.
 */
class MissingPledgesService
{
    /**
     * @return array<int, array{id: int, label: string}>
     */
    public static function fiscalYears(): array
    {
        $previousFyId = FiscalYearUtils::getCurrentFiscalYearId() - 1;

        return FiscalYearUtils::buildFiscalYearList(min((new FinancialService())->getOldestPledgeFyId(), $previousFyId));
    }

    /**
     * Defaults to families that pledged last fiscal year and have not pledged this year.
     *
     * @return array{pledgedFyId: int, missingFyId: int}
     */
    public static function criteriaFromQuery(array $query): array
    {
        $availableFyIds = array_column(self::fiscalYears(), 'id');
        $pledgedFyId = InputUtils::filterInt($query['pledgedFY'] ?? '');
        $missingFyId = InputUtils::filterInt($query['missingFY'] ?? '');

        if (!in_array($pledgedFyId, $availableFyIds, true)) {
            $pledgedFyId = max(1, FiscalYearUtils::getCurrentFiscalYearId() - 1);
        }
        if (!in_array($missingFyId, $availableFyIds, true) || $missingFyId === $pledgedFyId) {
            $missingFyId = in_array($pledgedFyId + 1, $availableFyIds, true) ? $pledgedFyId + 1 : $pledgedFyId - 1;
        }

        return ['pledgedFyId' => $pledgedFyId, 'missingFyId' => $missingFyId];
    }

    /**
     * Families in name order, each with its pledge total for the pledged year in cents.
     *
     * @return array<int, array{family: Family, pledgedCents: int}>
     */
    public static function families(int $pledgedFyId, int $missingFyId): array
    {
        $cents = [];
        foreach (PledgeQuery::create()
            ->filterByPledgeOrPayment('Pledge')
            ->filterByFyId([$pledgedFyId, $missingFyId], Criteria::IN)
            ->find() as $pledge
        ) {
            $familyId = (int) $pledge->getFamId();
            $fiscalYearId = (int) $pledge->getFyId();
            $cents[$familyId][$fiscalYearId] = ($cents[$familyId][$fiscalYearId] ?? 0)
                + (int) round((float) $pledge->getAmount() * 100);
        }

        $pledgedCents = [];
        foreach ($cents as $familyId => $byYear) {
            if (($byYear[$pledgedFyId] ?? 0) > 0 && ($byYear[$missingFyId] ?? 0) <= 0) {
                $pledgedCents[$familyId] = $byYear[$pledgedFyId];
            }
        }
        if ($pledgedCents === []) {
            return [];
        }

        $families = [];
        foreach (FamilyQuery::create()
            ->filterById(array_keys($pledgedCents), Criteria::IN)
            ->filterByDateDeactivated(null)
            ->orderByName()
            ->orderById()
            ->find() as $family
        ) {
            $families[] = ['family' => $family, 'pledgedCents' => $pledgedCents[(int) $family->getId()]];
        }

        return $families;
    }

    /**
     * Template data for the PDF.
     *
     * @param array<int, array{family: Family, pledgedCents: int}> $families
     */
    public static function document(array $families, int $pledgedFyId, int $missingFyId): array
    {
        $rows = [];
        $totalCents = 0;
        foreach ($families as $entry) {
            $family = $entry['family'];
            $rows[] = [
                'family' => (string) $family->getName(),
                'address' => $family->getMailingAddressLines(),
                'phone' => (string) $family->getHomePhone(),
                'pledged' => CurrencyFormatter::format($entry['pledgedCents'] / 100),
            ];
            $totalCents += $entry['pledgedCents'];
        }

        $pledgedYear = FiscalYearUtils::formatFiscalYearLabel($pledgedFyId);
        $missingYear = FiscalYearUtils::formatFiscalYearLabel($missingFyId);

        return [
            'pledged_fiscal_year' => $pledgedYear,
            'summary' => sprintf(gettext('Families that pledged for %s and have no pledge for %s.'), $pledgedYear, $missingYear),
            'family_count' => sprintf(ngettext('%d family', '%d families', count($rows)), count($rows)),
            'rows' => $rows,
            'total' => CurrencyFormatter::format($totalCents / 100),
        ];
    }

    /**
     * @param array<int, array{family: Family, pledgedCents: int}> $families
     * @return array{0: string[], 1: array<int, array<int, string|int>>}
     */
    public static function csv(array $families): array
    {
        $headers = ['fam_ID', 'fam_Name', 'fam_Address1', 'fam_Address2', 'fam_City', 'fam_State', 'fam_Zip', 'fam_Country', 'fam_HomePhone', 'plg_amount'];
        $rows = [];
        foreach ($families as $entry) {
            $family = $entry['family'];
            $mailing = $family->getMailingAddressParts();
            $rows[] = [
                (int) $family->getId(),
                (string) $family->getName(),
                $mailing['Address1'],
                $mailing['Address2'],
                $mailing['City'],
                $mailing['State'],
                $mailing['Zip'],
                $mailing['Country'],
                (string) $family->getHomePhone(),
                number_format($entry['pledgedCents'] / 100, 2, '.', ''),
            ];
        }

        return [$headers, $rows];
    }

    public static function fileLabel(int $pledgedFyId, int $missingFyId): string
    {
        return 'MissingPledges-' . $pledgedFyId . '-' . $missingFyId;
    }
}
