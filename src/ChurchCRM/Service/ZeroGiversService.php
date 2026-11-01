<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Zero Givers: families with a living member of classification 1 (Voting Members)
 * and no payment in the period.
 * Callers authorize: the Finance routes sit behind FinanceRoleAuthMiddleware.
 *
 * Classification ID 1 is the historical "Voting Members" / Member classification
 * used by the original Zero Givers report. No system config currently overrides it.
 */
class ZeroGiversService
{
    /** Voting Members / Member classification. Hard-coded to match legacy report. */
    private const MEMBER_CLASSIFICATION_ID = 1;

    /**
     * @return array{dateStart: string, dateEnd: string, letterhead: string}
     */
    public static function criteriaFromQuery(array $query): array
    {
        $dateStart = self::validDate($query['DateStart'] ?? '');
        $dateEnd = self::validDate($query['DateEnd'] ?? '');

        // Default to the current fiscal year when no dates are supplied.
        // This codifies FiscalYearUtils for all finance year reports.
        if ($dateStart === '' && $dateEnd === '') {
            $fy = FiscalYearUtils::getFiscalYearDatesById(FiscalYearUtils::getCurrentFiscalYearId());
            $dateStart = $fy['startDate'];
            $dateEnd = $fy['endDate'];
        }
        $dateStart = $dateStart !== '' ? $dateStart : $dateEnd;
        $dateEnd = $dateEnd !== '' ? $dateEnd : $dateStart;
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }

        $letterhead = is_scalar($query['letterhead'] ?? null) ? (string) $query['letterhead'] : '';

        return [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'letterhead' => in_array($letterhead, ['graphic', 'address', 'none'], true) ? $letterhead : 'address',
        ];
    }

    /**
     * Active families with a living classification-1 member, once each in id order.
     *
     * @return Family[]
     */
    public static function families(string $dateStart, string $dateEnd): array
    {
        $memberFamilyIds = array_map('intval', PersonQuery::create()
            ->filterByClsId(self::MEMBER_CLASSIFICATION_ID)
            ->filterByLiving()
            ->filterByFamId(0, Criteria::GREATER_THAN)
            ->select('FamId')
            ->distinct()
            ->find()
            ->toArray());

        $paidFamilyIds = array_map('intval', PledgeQuery::create()
            ->filterForZeroGivers($dateStart, $dateEnd)
            ->select('FamId')
            ->distinct()
            ->find()
            ->toArray());

        $zeroGiverIds = array_values(array_diff($memberFamilyIds, $paidFamilyIds));
        if ($zeroGiverIds === []) {
            return [];
        }

        return FamilyQuery::create()
            ->filterById($zeroGiverIds, Criteria::IN)
            ->filterByDateDeactivated(null)
            ->orderById()
            ->find()
            ->getArrayCopy();
    }

    /**
     * Template data: one letter per family.
     *
     * Address uses getMailingAddressLines() which already resolves primary vs
     * secondary (Address2) mailing address and formats the postal block correctly.
     *
     * @param Family[] $families
     */
    public static function document(array $families, string $dateStart, string $dateEnd, string $letterhead): array
    {
        $start = DateTimeUtils::formatDate($dateStart);
        $end = DateTimeUtils::formatDate($dateEnd);
        $graphic = $letterhead === 'graphic' ? MpdfRenderer::graphicLetterhead() : null;

        $letters = [];
        foreach ($families as $family) {
            $letters[] = [
                'salutation' => $family->getSalutation(),
                'address' => $family->getMailingAddressLines(),
            ];
        }

        return [
            'letterhead' => $graphic !== null ? 'graphic' : ($letterhead === 'none' ? 'none' : 'address'),
            'letterhead_image' => $graphic,
            'opening' => trim(SystemConfig::getValue('sTaxReport1') . ' ' . ($dateStart === $dateEnd ? $start : $start . ' – ' . $end) . ' ' . SystemConfig::getValue('sZeroGivers')),
            'thanks' => SystemConfig::getValue('sZeroGivers2'),
            'questions' => SystemConfig::getValue('sZeroGivers3'),
            'sincerely' => SystemConfig::getValue('sConfirmSincerely'),
            'signer' => SystemConfig::getValue('sTaxSigner'),
            'letters' => $letters,
        ];
    }

    /**
     * @param Family[] $families
     * @return array{0: string[], 1: array<int, array<int, string|int|null>>}
     */
    public static function csv(array $families): array
    {
        $headers = ['fam_ID', 'fam_Name', 'fam_Address1', 'fam_Address2', 'fam_City', 'fam_State', 'fam_Zip', 'fam_Country'];
        $rows = [];
        foreach ($families as $family) {
            $rows[] = [
                $family->getId(),
                $family->getName(),
                $family->getAddress1(),
                $family->getAddress2(),
                $family->getCity(),
                $family->getState(),
                $family->getZip(),
                $family->getCountry(),
            ];
        }

        return [$headers, $rows];
    }

    public static function fileLabel(): string
    {
        return 'ZeroGivers-' . date(SystemConfig::getValue('sDateFilenameFormat'));
    }

    private static function validDate(mixed $value): string
    {
        $date = InputUtils::filterDate(is_scalar($value) ? (string) $value : '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < 1900) {
            return '';
        }

        return $date;
    }
}
