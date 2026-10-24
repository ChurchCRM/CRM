<?php

namespace ChurchCRM\data;

/**
 * Grade names by country (#10189).
 *
 * Each grade is keyed by its level: the typical age of a child at the start of
 * that school year. The level is also the grade's lst_OptionID, so switching a
 * church to another country's names renames grades without changing anyone's
 * grade, and an English Year 2 child is a US 1st grader. Names are data in the
 * country's own usage, like the seeded classification names, not translated.
 */
class GradePresets
{
    public const DEFAULT_KEY = 'us';

    /**
     * @return array<string, array{name: string, grades: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            'us' => [
                'name' => gettext('United States'),
                'grades' => [4 => 'Pre-K', 5 => 'Kindergarten'] + self::ordinals(6, 1, 12, '%s'),
            ],
            'canada' => [
                'name' => gettext('Canada'),
                'grades' => [4 => 'Junior Kindergarten', 5 => 'Kindergarten'] + self::numbered(6, 1, 12, '%d'),
            ],
            'england-wales' => [
                'name' => gettext('England and Wales'),
                'grades' => [3 => 'Nursery', 4 => 'Reception'] + self::numbered(5, 1, 13, 'Year %d'),
            ],
            'scotland' => [
                'name' => gettext('Scotland'),
                'grades' => [4 => 'Nursery'] + self::numbered(5, 1, 7, 'P%d') + self::numbered(12, 1, 6, 'S%d'),
            ],
            'northern-ireland' => [
                'name' => gettext('Northern Ireland'),
                'grades' => [3 => 'Nursery'] + self::numbered(4, 1, 7, 'P%d') + self::numbered(11, 8, 14, 'Year %d'),
            ],
            'ireland' => [
                'name' => gettext('Ireland'),
                'grades' => [3 => 'Pre-school', 4 => 'Junior Infants', 5 => 'Senior Infants']
                    + self::ordinals(6, 1, 6, '%s Class')
                    + self::ordinals(12, 1, 3, '%s Year')
                    + [15 => 'Transition Year', 16 => '5th Year', 17 => '6th Year'],
            ],
            'australia' => [
                'name' => gettext('Australia'),
                'grades' => [4 => 'Pre-school', 5 => 'Foundation'] + self::numbered(6, 1, 12, 'Year %d'),
            ],
            'new-zealand' => [
                'name' => gettext('New Zealand'),
                'grades' => [4 => 'Pre-school'] + self::numbered(5, 1, 13, 'Year %d'),
            ],
            'south-africa' => [
                'name' => gettext('South Africa'),
                'grades' => [4 => 'RR', 5 => 'R'] + self::numbered(6, 1, 12, '%d'),
            ],
            'philippines' => [
                'name' => gettext('Philippines'),
                'grades' => [4 => 'Pre-Kindergarten', 5 => 'Kindergarten'] + self::numbered(6, 1, 12, '%d'),
            ],
        ];
    }

    /**
     * @return array{name: string, grades: array<int, string>}|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * The preset for a church in this country, or null when ChurchCRM has none for it.
     *
     * @param string $countryCode ISO 3166-1 alpha-2, see Countries::toISO()
     */
    public static function keyForCountry(string $countryCode, string $state = ''): ?string
    {
        $state = strtolower(trim($state));

        return match (strtoupper($countryCode)) {
            'US' => 'us',
            'CA' => 'canada',
            'GB' => match ($state) {
                'scotland' => 'scotland',
                'northern ireland' => 'northern-ireland',
                default => 'england-wales',
            },
            'IE' => 'ireland',
            'AU' => 'australia',
            'NZ' => 'new-zealand',
            'ZA' => 'south-africa',
            'PH' => 'philippines',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private static function numbered(int $firstLevel, int $from, int $to, string $format): array
    {
        $grades = [];
        for ($n = $from; $n <= $to; $n++) {
            $grades[$firstLevel + $n - $from] = sprintf($format, $n);
        }

        return $grades;
    }

    /**
     * @return array<int, string>
     */
    private static function ordinals(int $firstLevel, int $from, int $to, string $format): array
    {
        $grades = [];
        for ($n = $from; $n <= $to; $n++) {
            $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
            $grades[$firstLevel + $n - $from] = sprintf($format, $n . $suffix);
        }

        return $grades;
    }
}
