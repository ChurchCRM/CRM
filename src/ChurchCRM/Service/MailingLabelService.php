<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\MiscUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The text of mailing labels for people and households, in the order they are printed.
 *
 * A label is `['Name', 'Address', 'City', 'State', 'Zip']`. With bulk-mail presort every label
 * also carries a `Note` naming its bundle, and each bundle starts with a heading label that has no State or Zip.
 */
class MailingLabelService
{
    public const BULK_NONE = 0;
    public const BULK_QUIET = 1;
    public const BULK_NOTED = 2;

    private const NAME_LENGTH = 33;
    private const ZIP5_MIN_BUNDLE = 15;
    private const ZIP3_MIN_BUNDLE = 10;
    private const ADC_MIN_BUNDLE = 10;

    /**
     * USPS area distribution centers for standard mail (http://pe.usps.com/text/dmm300/L004.htm, updated 2010-08-26):
     * the three-digit ZIP prefixes of each center, then "_" and its name, whose last word is the center's ZIP.
     * Rows end with "|".
     */
    private const ADC_LIST =
        '005, 115, 117-119                      _LONG ISLAND NY 117         |' .
        '006-009                                _ADC SAN JUAN PR 006        |' .
        '010-017                                _ADC SPRINGFIELD MA 010     |' .
        '018, 019, 021, 022, 024, 055           _ADC BOSTON MA 021          |' .
        '020, 023, 025-029                      _ADC PROVIDENCE RI 028      |' .
        '030-034, 038, 039                      _ADC PORTSMOUTH NH 038      |' .
        '035-037, 050-054, 056-059              _ADC WHITE RIV JCT VT 050   |' .
        '040-049                                _ADC PORTLAND ME 040        |' .
        '060-069                                _ADC SOUTHERN CT 064        |' .
        '070-079, 085-089                       _ADC DV DANIELS NJ 07099    |' .
        '080-084                                _ADC SOUTH JERSEY NJ 080    |' .
        '090-099                                _MILITARY CENTER NY 090     |' .
        '100-102, 104                           _ADC NEW YORK NY 100        |' .
        '103, 110-114, 116                      _ADC QUEENS NY 110          |' .
        '105-109                                _ADC WESTCHESTER NY 105     |' .
        '120-129                                _ADC ALBANY NY 120          |' .
        '130-139                                _ADC SYRACUSE NY 130        |' .
        '140-149                                _ADC BUFFALO NY 140         |' .
        '150-168, 260                           _ADC PITTSBURGH PA 150      |' .
        '169-178                                _ADC HARRISBURG PA 170      |' .
        '179, 189, 193-196                      _ADC SOUTHEASTERN PA 189    |' .
        '180-188                                _ADC LEHIGH VALLEY PA 180   |' .
        '190-192                                _ADC PHILADELPHIA PA 190    |' .
        '197-199                                _ADC WILMINGTON DE 197      |' .
        '200                                    _WASHINGTON DC 200          |' .
        '201, 220-223, 226, 227                 _ADC NORTHERN VA VA 220     |' .
        '202-205                                _ADC WASHINGTON DC 202      |' .
        '206-209                                _ADC SOUTHERN MD MD 207     |' .
        '210-212, 214-219, 254, 267             _ADC LINTHICUM MD 210       |' .
        '224, 225, 228-239, 244                 _ADC RICHMOND VA 230        |' .
        '240-243, 245                           _ADC ROANOKE VA 240         |' .
        '246-253, 255-259                       _ADC CHARLESTON WV 250      |' .
        '261-266, 268                           _ADC CLARKSBURG WV 263      |' .
        '270-279, 285                           _ADC GREENSBORO NC 270      |' .
        '280-284, 286-289, 297                  _ADC CHARLOTTE NC 280       |' .
        '290-296                                _ADC COLUMBIA SC 290        |' .
        '298, 300, 301, 305, 306, 308, 309      _ADC NORTH METRO GA 30197   |' .
        '299, 304, 313-315, 320-324, 326, 344   _ADC JACKSONVILLE FL 32088  |' .
        '302, 303, 311, 399                     _ADC ATLANTA GA 303         |' .
        '307, 370-374, 376-379, 384, 385        _ADC NASHVILLE TN 37099     |' .
        '310, 312, 316-319, 398                 _ADC MACON GA 31293         |' .
        '325, 365, 366, 394, 395                _ADC MOBILE AL 365          |' .
        '327-329, 334, 347, 349                 _ADC MID FLORIDA FL 32799   |' .
        '330-333, 340                           _ADC MIAMI FL 33298         |' .
        '335-339, 341, 342, 346                 _ADC TAMPA FL 335           |' .
        '350-352, 354-359, 362                  _ADC BIRMINGHAM AL 35099    |' .
        '360, 361, 363, 364, 367, 368           _ADC MONTGOMERY AL 36099    |' .
        '369, 390-393, 396, 397                 _ADC JACKSON MS 39099       |' .
        '375, 380-383, 386-389, 723             _ADC MEMPHIS TN 38099       |' .
        '400-409, 411-418, 420-427, 471, 476, 477
                                            _ADC LOUISVILLE KY 400      |' .
        '410, 450-455, 458, 459, 470            _ADC CINCINNATI OH 450      |' .
        '430-438, 456, 457                      _ADC COLUMBUS OH 430        |' .
        '439-449                                _ADC CLEVELAND OH 440       |' .
        '460-469, 472-475, 478, 479             _ADC INDIANAPOLIS IN 460    |' .
        '480-489, 492                           _ADC DETROIT MI 481         |' .
        '490, 491, 493-497                      _ADC GRAND RAPIDS MI 493    |' .
        '498, 499, 530-532, 534, 535, 537-539, 541-545, 549
                                            _ADC MILWAUKEE WI 530       |' .
        '500-509, 520-528, 612                  _ADC DES MOINES IA 50091    |' .
        '510-516, 680, 681, 683-693             _ADC OMAHA NE 680           |' .
        '540, 546-548, 550, 551, 556-559        _ADC ST PAUL MN 550         |' .
        '553-555, 560-564, 566                  _ADC MINNEAPOLIS MN 553     |' .
        '565, 567, 580-588                      _ADC FARGO ND 580           |' .
        '570-577                                _ADC SIOUX FALLS SD 570     |' .
        '590-599, 821                           _ADC BILLINGS MT 590        |' .
        '600-603, 610, 611, 614-616             _ADC CAROL STREAM IL 601    |' .
        '604, 605, 609, 613, 617-619            _ADC S SUBURBAN IL 604      |' .
        '606-608                                _ADC CHICAGO IL 606         |' .
        '620, 622-631, 633-639                  _ADC ST LOUIS MO 63203      |' .
        '640, 641, 644-658, 660-662, 664-668    _ADC KANSAS CITY MO 66340   |' .
        '669-679, 739                           _ADC WICHITA KS 67099       |' .
        '700, 701, 703, 704                     _ADC NEW ORLEANS LA 700     |' .
        '705-708                                _ADC BATON ROUGE LA 707     |' .
        '710-714                                _ADC SHREVEPORT LA 71099    |' .
        '716-722, 724-729                       _ADC LITTLE ROCK AR 72098   |' .
        '730, 731, 734-738, 748                 _ADC OKLAHOMA CITY OK 730   |' .
        '733, 779-789                           _ADC SAN ANTONIO TX 78099   |' .
        '740, 741, 743-747, 749                 _ADC TULSA OK 740           |' .
        '750-759                                _ADC NORTH TEXAS TX 750     |' .
        '760-769                                _ADC FT WORTH TX 760        |' .
        '770-778                                _ADC NORTH HOUSTON TX 773   |' .
        '790-797                                _ADC LUBBOCK TX 793         |' .
        '798, 799, 880, 885                     _ADC EL PASO TX 798         |' .
        '800-816                                _ADC DENVER CO 800          |' .
        '820, 822-831                           _ADC CHEYENNE WY 820        |' .
        '832-834, 836, 837, 979                 _ADC BOISE ID 836           |' .
        '835, 838, 980-985, 988-994, 998, 999   _ADC SEATTLE WA 980         |' .
        '840-847, 898                           _ADC SALT LAKE CTY UT 840   |' .
        '850-853, 855, 859, 860, 863            _ADC PHOENIX AZ 852         |' .
        '856, 857                               _ADC TUCSON AZ 856          |' .
        '864, 889-891, 893-895, 897, 961        _ADC LAS VEGAS NV 890       |' .
        '865, 870-875, 877-879, 881-884         _ADC ALBUQUERQUE NM 870     |' .
        '900-904                                _ADC LOS ANGELES CA 900     |' .
        '905-908                                _ADC LONG BEACH CA 907      |' .
        '910-912, 932, 933, 935                 _ADC PASADENA CA 910        |' .
        '913-916, 930, 931, 934                 _ADC SANTA CLARITA CA 913   |' .
        '917, 918                               _ADC INDUSTRY CA 917        |' .
        '919-921                                _ADC SAN DIEGO CA 920       |' .
        '922-925                                _ADC SN BERNARDINO CA 923   |' .
        '926-928                                _ADC SANTA ANA CA 926       |' .
        '936-939, 950, 951                      _ADC SAN JOSE CA 950        |' .
        '940, 941, 943, 944, 949, 954, 955      _ADC SAN FRANCISCO CA 940   |' .
        '942, 952, 953, 956-960                 _ADC SACRAMENTO CA 956      |' .
        '945-948                                _ADC OAKLAND CA 945         |' .
        '962-966                                _AMF SFO APO/FPO CA 962     |' .
        '967-969                                _ADC HONOLULU HI 967        |' .
        '970-978, 986                           _ADC PORTLAND OR 970        |' .
        '995-997                                _ADC ANCHORAGE AK 995       |';

    /**
     * @param array<int, string[]> $entries person id => label modes: "indiv" labels the person by
     *                                      name, "fam" gives their household one label. A person
     *                                      can have both (#10343).
     * @return array<int, array<string, string>>
     */
    public static function labels(array $entries, int $bulk, bool $toParents, bool $onlyComplete): array
    {
        $personIds = array_map('intval', array_keys($entries));
        if ($personIds === []) {
            return [];
        }
        $householdIds = array_map('intval', array_keys(array_filter(
            $entries,
            static fn (array $modes): bool => in_array('fam', $modes, true)
        )));

        $adultRoles = self::roleIds(SystemConfig::getValue('sDirRoleHead') . ',' . SystemConfig::getValue('sDirRoleSpouse'));
        $childRoles = self::roleIds(SystemConfig::getValue('sDirRoleChild'));

        $people = PersonQuery::create()
            ->filterById($personIds, Criteria::IN)
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->leftJoinWithFamily()
            ->orderByLastName()
            ->orderByFirstName()
            ->addAscendingOrderByColumn(FamilyTableMap::COL_FAM_ZIP)
            ->find();

        $labels = [];
        $doneFamilies = [];
        foreach ($people as $person) {
            $row = self::row($person);
            foreach ($entries[$row['per_ID']] as $mode) {
                if ($mode === 'fam') {
                    if ($row['per_fam_ID'] === 0 || isset($doneFamilies[$row['per_fam_ID']])) {
                        continue;
                    }
                    $doneFamilies[$row['per_fam_ID']] = true;

                    // One label per household (#9873): the adults' salutation when an adult of the
                    // family is listed, else the children's (so a class list still gets "To the
                    // parents of"), else the family name.
                    $names = self::householdNames($row['per_fam_ID'], $adultRoles, $childRoles, $householdIds);
                    foreach (['adult', 'child', 'other'] as $group) {
                        if ($names[$group] !== null) {
                            $names = [$group => $names[$group]];
                            break;
                        }
                    }
                } else {
                    $name = MiscUtils::formatFullName($row['per_Title'], $row['per_FirstName'], '', $row['per_LastName'], $row['per_Suffix'], 1);
                    $key = in_array($row['per_fmr_ID'], $childRoles, true) ? 'child' : 'indiv';
                    $names = [$key => mb_substr($name, 0, self::NAME_LENGTH)];
                }

                foreach ($names as $key => $name) {
                    if ($name === null) {
                        continue;
                    }
                    if ($toParents && $key === 'child') {
                        $name = "To the parents of:\n" . $name;
                    }

                    // A person's own address wins; otherwise the family's mailing address (#9873).
                    $address = self::address($row);
                    $street = $address['Address1'];
                    if ($address['Address2'] !== '') {
                        $street .= "\n" . $address['Address2'];
                    }

                    if (!$onlyComplete || ($street !== '' && $address['City'] !== '' && $address['State'] !== '' && $address['Zip'] !== '')) {
                        $labels[] = ['Name' => $name, 'Address' => $street, 'City' => $address['City'], 'State' => $address['State'], 'Zip' => $address['Zip']];
                    }
                }
            }
        }

        return $bulk === self::BULK_NONE ? $labels : self::bundles($labels);
    }

    /**
     * The text printed on a label.
     *
     * @param array<string, string> $label
     */
    public static function text(array $label, int $bulk): string
    {
        $isHeading = !isset($label['State']);
        $lines = [];
        if ($bulk === self::BULK_NOTED) {
            $lines[] = $label['Note'];
        }
        $lines[] = $label['Name'];
        $lines[] = $label['Address'];
        $lines[] = $isHeading ? $label['City'] : sprintf('%s, %s %s', $label['City'], $label['State'], $label['Zip']);

        return implode("\n", $lines);
    }

    /**
     * The CSV for a list of labels: a name or address of several lines becomes several rows.
     *
     * @param array<int, array<string, string>> $labels
     * @return array{headers: string[], rows: array<int, string[]>}
     */
    public static function csv(array $labels, int $bulk): array
    {
        $headers = $bulk !== self::BULK_NONE ? ['ZipBundle'] : [];
        array_push($headers, 'Greeting', 'Name', 'Address', 'City', 'State', 'Zip');

        $rows = [];
        foreach ($labels as $label) {
            $nameLines = explode("\n", $label['Name']);
            $addressLines = explode("\n", $label['Address']);
            for ($i = 0, $count = max(count($nameLines), count($addressLines)); $i < $count; $i++) {
                $first = $i === 0;
                $row = $bulk !== self::BULK_NONE ? [$first ? ($label['Note'] ?? '') : ''] : [];
                array_push(
                    $row,
                    '',
                    $nameLines[$i] ?? '',
                    $addressLines[$i] ?? '',
                    $first ? $label['City'] : '',
                    $first ? ($label['State'] ?? '') : '',
                    $first ? ($label['Zip'] ?? '') : ''
                );
                $rows[] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return int[]
     */
    private static function roleIds(string $list): array
    {
        $ids = array_filter(array_map('trim', explode(',', $list)), 'is_numeric');

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * The person and family columns a label reads.
     *
     * @return array<string, mixed>
     */
    private static function row(Person $person): array
    {
        // The flagged second address when the family has one, the primary otherwise (#10405).
        $family = $person->getFamily()?->getMailingAddressParts() ?? [];

        return [
            'per_ID' => (int) $person->getId(),
            'per_fam_ID' => (int) $person->getFamId(),
            'per_fmr_ID' => (int) $person->getFmrId(),
            'per_Title' => (string) $person->getTitle(),
            'per_FirstName' => (string) $person->getFirstName(),
            'per_LastName' => (string) $person->getLastName(),
            'per_Suffix' => (string) $person->getSuffix(),
            'per_Address1' => $person->getAddress1(),
            'per_Address2' => $person->getAddress2(),
            'per_City' => $person->getCity(),
            'per_State' => $person->getState(),
            'per_Zip' => $person->getZip(),
            'fam_Address1' => $family['Address1'] ?? null,
            'fam_Address2' => $family['Address2'] ?? null,
            'fam_City' => $family['City'] ?? null,
            'fam_State' => $family['State'] ?? null,
            'fam_Zip' => $family['Zip'] ?? null,
        ];
    }

    /**
     * The address block for a label, taken whole from one source: the person's own address when they
     * have entered a street line, otherwise the family's mailing address. The decision is made once for
     * all five parts so a label never mixes a person's street with the family's city.
     *
     * @param array<string, mixed> $row
     * @return array{Address1: string, Address2: string, City: string, State: string, Zip: string}
     */
    private static function address(array $row): array
    {
        $ownStreet = trim((string) $row['per_Address1']) !== '' || trim((string) $row['per_Address2']) !== '';
        $prefix = $ownStreet ? 'per_' : 'fam_';

        return [
            'Address1' => trim((string) $row[$prefix . 'Address1']),
            'Address2' => trim((string) $row[$prefix . 'Address2']),
            'City' => trim((string) $row[$prefix . 'City']),
            'State' => trim((string) $row[$prefix . 'State']),
            'Zip' => trim((string) $row[$prefix . 'Zip']),
        ];
    }

    /**
     * Who a household's label is addressed to: its adults, its children, or, for anyone else, the family.
     * Every name is null when the household has nobody in that group.
     *
     * @param int[] $adultRoles
     * @param int[] $childRoles
     * @param int[] $personIds only these members count
     * @return array{adult: ?string, child: ?string, other: ?string}
     */
    private static function householdNames(int $familyId, array $adultRoles, array $childRoles, array $personIds): array
    {
        $none = ['adult' => null, 'child' => null, 'other' => null];
        $family = FamilyQuery::create()->findPk($familyId);
        if ($family === null) {
            return $none;
        }
        $familyName = (string) $family->getName();

        $members = PersonQuery::create()
            ->filterByFamId($familyId)
            ->filterById($personIds, Criteria::IN)
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->orderByLastName()
            ->orderByFirstName()
            ->find();

        // A role listed for both adults and children counts as adult.
        $adults = [];
        $children = [];
        $others = 0;
        foreach ($members as $member) {
            $role = (int) $member->getFmrId();
            $name = ['first' => (string) $member->getFirstName(), 'last' => (string) $member->getLastName()];
            if (in_array($role, $adultRoles, true)) {
                $adults[] = $name;
            } elseif (in_array($role, $childRoles, true)) {
                $children[] = $name;
            } else {
                $others++;
            }
        }

        return [
            'adult' => self::cut(self::adultNames($adults, $familyName)),
            'child' => self::cut(self::childNames($children, $familyName)),
            'other' => self::cut($others > 0 ? $familyName : null),
        ];
    }

    private static function cut(?string $name): ?string
    {
        return $name === null ? null : mb_substr($name, 0, self::NAME_LENGTH);
    }

    /**
     * @param array<int, array{first: string, last: string}> $adults
     */
    private static function adultNames(array $adults, string $familyName): ?string
    {
        return match (count($adults)) {
            0 => null,
            1 => $adults[0]['first'] . ' ' . $adults[0]['last'],
            2 => $adults[0]['last'] === $adults[1]['last']
                ? $adults[0]['first'] . ' & ' . $adults[1]['first'] . ' ' . $adults[0]['last']
                : $adults[0]['first'] . ' ' . $adults[0]['last'] . ' & ' . $adults[1]['first'] . ' ' . $adults[1]['last'],
            default => $familyName,
        };
    }

    /**
     * @param array<int, array{first: string, last: string}> $children
     */
    private static function childNames(array $children, string $familyName): ?string
    {
        $count = count($children);
        if ($count === 0) {
            return null;
        }
        if ($count === 1) {
            return $children[0]['first'] . ' ' . $children[0]['last'];
        }
        if ($count > 4) {
            return 'The ' . $familyName . ' Family';
        }

        $sameLastName = count(array_unique(array_column($children, 'last'))) === 1;
        if ($count === 2 && !$sameLastName) {
            return $children[0]['first'] . ' ' . $children[0]['last'] . ' & ' . $children[1]['first'] . ' ' . $children[1]['last'];
        }

        $firstNames = array_column($children, 'first');
        $lastFirstName = array_pop($firstNames);

        return ($firstNames === [] ? '' : implode(', ', $firstNames) . ' & ') . $lastFirstName . ' ' . ($sameLastName ? $children[0]['last'] : $familyName);
    }

    /**
     * Orders labels into presort bundles: full five-digit ZIPs with enough labels, then three-digit
     * ZIPs, then area distribution centers, then everything left as one mixed bundle.
     *
     * @param array<int, array<string, string>> $labels
     * @return array<int, array<string, string>>
     */
    private static function bundles(array $labels): array
    {
        $total = count($labels);
        $adc = self::adcByPrefix();
        $out = [];

        $passes = [
            ['ZIP-5', self::ZIP5_MIN_BUNDLE, static fn (array $label): ?string => (string) (int) mb_substr($label['Zip'], 0, 5), 'Bundle '],
            ['ZIP-3', self::ZIP3_MIN_BUNDLE, static fn (array $label): ?string => (string) (int) mb_substr($label['Zip'], 0, 3), 'Bundle '],
            ['ADC', self::ADC_MIN_BUNDLE, static fn (array $label): ?string => $adc[(int) mb_substr($label['Zip'], 0, 3)] ?? null, 'Bundle ADC '],
        ];
        foreach ($passes as [$kind, $minimum, $keyOf, $bundleName]) {
            $groups = [];
            foreach ($labels as $index => $label) {
                $key = $keyOf($label);
                if ($key !== null) {
                    $groups[$key][] = $index;
                }
            }
            foreach ($groups as $key => $indexes) {
                if (count($indexes) < $minimum) {
                    continue;
                }
                $note = '******* Presort ' . $kind . ' ' . $key;
                $out[] = self::heading($note, '** ' . count($indexes) . ' Addresses in ' . $bundleName . $key . ' *', $total, $note . '  ');
                foreach ($indexes as $index) {
                    $out[] = $labels[$index] + ['Note' => $note];
                    unset($labels[$index]);
                }
            }
        }

        if ($labels !== []) {
            $note = '******* Presort MIXED ADC ';
            $out[] = self::heading($note, '** ' . count($labels) . ' Addresses in Bundle *', $total, '******* Presort MIXED ADC   ');
            foreach ($labels as $label) {
                $out[] = $label + ['Note' => $note];
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function heading(string $note, string $name, int $total, string $city): array
    {
        return ['Note' => $note, 'Name' => $name, 'Address' => '** ' . $total . ' Total Addresses *', 'City' => $city];
    }

    /**
     * Three-digit ZIP prefix => the ZIP of its area distribution center.
     *
     * @return array<int, string>
     */
    private static function adcByPrefix(): array
    {
        $byPrefix = [];
        foreach (explode('|', self::ADC_LIST) as $row) {
            $row = trim($row);
            if ($row === '' || !str_contains($row, '_')) {
                continue;
            }
            $center = trim(substr($row, strrpos($row, ' ')), " ,\t\n\r\0\x0B");
            foreach (preg_split('/\s*,\s*/', trim(substr($row, 0, strpos($row, '_')), " ,\t\n\r\0\x0B")) ?: [] as $code) {
                [$start, $end] = array_map('intval', explode('-', $code) + [1 => $code]);
                for ($prefix = $start; $prefix <= $end; $prefix++) {
                    $byPrefix[$prefix] = $center;
                }
            }
        }

        return $byPrefix;
    }
}
