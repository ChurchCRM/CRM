<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Zero Givers: families with a living member of classification 1 and no payment in the period.
 * Callers authorize: the Finance routes sit behind FinanceRoleAuthMiddleware.
 */
class ZeroGiversService
{
    private const MEMBER_CLASSIFICATION_ID = 1;

    /**
     * @return array{dateStart: string, dateEnd: string, letterhead: string}
     */
    public static function criteriaFromQuery(array $query): array
    {
        $dateStart = self::validDate($query['DateStart'] ?? '');
        $dateEnd = self::validDate($query['DateEnd'] ?? '');
        if ($dateStart === '' && $dateEnd === '') {
            $dateStart = $dateEnd = DateTimeUtils::getTodayDate();
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
     * Each family once, in id order.
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
            ->orderById()
            ->find()
            ->getArrayCopy();
    }

    /**
     * Template data: one letter per family.
     *
     * @param Family[] $families
     */
    public static function document(array $families, string $dateStart, string $dateEnd, string $letterhead): array
    {
        $start = DateTimeUtils::formatDate($dateStart);
        $end = DateTimeUtils::formatDate($dateEnd);
        $graphic = $letterhead === 'graphic' ? self::graphicLetterhead() : null;

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

    public static function contentDisposition(): string
    {
        $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';

        return $disposition . '; filename="ZeroGivers' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf"';
    }

    private static function graphicLetterhead(): ?string
    {
        $path = trim((string) SystemConfig::getValue('bDirLetterHead'));
        if ($path !== '' && (is_readable($path) || preg_match('#^https://#i', $path))) {
            return $path;
        }

        return null;
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
