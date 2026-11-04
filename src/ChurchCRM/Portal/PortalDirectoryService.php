<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The Member Portal directory: who it lists, who may open it, and what it shows.
 *
 * `bPortalShowDirectory` switches the `portal.directory.view` capability on and off. The rest
 * of the administrator's choice is one JSON config item, `aPortalDirectory`. A field the
 * administrator leaves off is never read into an entry, so neither the page nor the API can
 * show it.
 */
final class PortalDirectoryService
{
    public const SWITCH_NAME = 'bPortalShowDirectory';
    public const CONFIG_NAME = 'aPortalDirectory';

    public const DATE_WITH_YEAR = 'withYear';
    public const DATE_WITHOUT_YEAR = 'withoutYear';
    public const DATE_HIDDEN = 'hidden';
    public const DATE_MODES = [self::DATE_WITH_YEAR, self::DATE_WITHOUT_YEAR, self::DATE_HIDDEN];

    /** The printed directory's "Information to Include", with its defaults. */
    public const FIELDS = [
        'address' => true,
        'mailingAddress' => false,
        'homePhone' => true,
        'personalPhone' => true,
        'workPhone' => true,
        'cellPhone' => true,
        'email' => true,
        'workEmail' => true,
        'photo' => true,
    ];

    /** Search only: the shortest query answered, and the most entries one answer holds. */
    public const SEARCH_MIN_LENGTH = 2;
    public const SEARCH_LIMIT = 20;

    private const MEMBER_CLASSIFICATION = 1;
    private const CLASSIFICATION_LIST = 1;

    /** @var array<string, mixed>|null */
    private static ?array $settings = null;

    /**
     * The stored choice with defaults filled in. Classifications the church has since
     * deleted are dropped.
     *
     * @return array{listed: int[], viewers: int[], fields: array<string, bool>, birthday: string, anniversary: string, browse: bool}
     */
    public static function settings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $stored = json_decode((string) SystemConfig::getValue(self::CONFIG_NAME), true);
        $stored = is_array($stored) ? $stored : [];
        $known = self::classificationIds();

        $fields = self::FIELDS;
        foreach (is_array($stored['fields'] ?? null) ? $stored['fields'] : [] as $field => $on) {
            if (array_key_exists($field, $fields)) {
                $fields[$field] = (bool) $on;
            }
        }

        $known = array_flip($known);
        $ids = static fn (mixed $value): array => array_values(array_filter(
            array_map('intval', is_array($value) ? $value : []),
            static fn (int $id): bool => isset($known[$id])
        ));
        $mode = static fn (mixed $value): string => in_array($value, self::DATE_MODES, true) ? $value : self::DATE_WITHOUT_YEAR;

        return self::$settings = [
            'listed' => $ids($stored['listed'] ?? [self::MEMBER_CLASSIFICATION]),
            'viewers' => $ids($stored['viewers'] ?? [self::MEMBER_CLASSIFICATION]),
            'fields' => $fields,
            'birthday' => $mode($stored['birthday'] ?? null),
            'anniversary' => $mode($stored['anniversary'] ?? null),
            'browse' => is_bool($stored['browse'] ?? null) ? $stored['browse'] : true,
        ];
    }

    /**
     * Replace the parts of the choice that `$input` names; the rest stays as it is.
     *
     * @param array<string, mixed> $input
     *
     * @throws InvalidArgumentException when a part is malformed or names something unknown
     *
     * @return array{listed: int[], viewers: int[], fields: array<string, bool>, birthday: string, anniversary: string, browse: bool}
     */
    public static function save(array $input): array
    {
        $unknown = array_diff(array_keys($input), ['listed', 'viewers', 'fields', 'birthday', 'anniversary', 'browse']);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(gettext('Unknown directory setting: %s'), implode(', ', $unknown)));
        }

        $settings = self::settings();
        foreach (['listed', 'viewers'] as $key) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = self::validClassificationIds($input[$key]);
            }
        }
        if (array_key_exists('fields', $input)) {
            if (!is_array($input['fields'])) {
                throw new InvalidArgumentException(gettext('Send the fields to show as an object of true and false values.'));
            }
            foreach ($input['fields'] as $field => $on) {
                if (!array_key_exists($field, self::FIELDS) || !is_bool($on)) {
                    throw new InvalidArgumentException(sprintf(gettext('Unknown directory field: %s'), (string) $field));
                }
                $settings['fields'][$field] = $on;
            }
        }
        foreach (['birthday', 'anniversary'] as $key) {
            if (array_key_exists($key, $input)) {
                if (!in_array($input[$key], self::DATE_MODES, true)) {
                    throw new InvalidArgumentException(gettext('Choose how dates are shown from the list.'));
                }
                $settings[$key] = $input[$key];
            }
        }
        if (array_key_exists('browse', $input)) {
            if (!is_bool($input['browse'])) {
                throw new InvalidArgumentException(gettext('Send browse as true or false.'));
            }
            $settings['browse'] = $input['browse'];
        }

        SystemConfig::setValue(self::CONFIG_NAME, json_encode($settings));
        self::$settings = null;

        return self::settings();
    }

    public static function isViewerClassification(int $classificationId): bool
    {
        return in_array($classificationId, self::settings()['viewers'], true);
    }

    /**
     * What a member is shown for `$query`, grouped under first letters. With browsing on that
     * is everyone, or the entries whose names hold every word of the query. Search only, it is
     * nothing until the query has SEARCH_MIN_LENGTH characters, then at most SEARCH_LIMIT
     * matching entries, so the whole list never leaves the server.
     *
     * @return array{browse: bool, query: string, groups: array<int, array{letter: string, entries: array<int, array<string, mixed>>}>, truncated: bool}
     */
    public static function find(string $query = ''): array
    {
        $browse = self::settings()['browse'];
        $query = trim($query);
        if (!$browse && mb_strlen($query) < self::SEARCH_MIN_LENGTH) {
            return ['browse' => false, 'query' => $query, 'groups' => [], 'truncated' => false];
        }

        $words = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $entries = array_values(array_filter(
            self::entries(),
            static fn (array $entry): bool => array_all($words, static fn (string $word): bool => str_contains($entry['searchText'], $word))
        ));
        $truncated = !$browse && count($entries) > self::SEARCH_LIMIT;

        return [
            'browse' => $browse,
            'query' => $query,
            'groups' => self::group($truncated ? array_slice($entries, 0, self::SEARCH_LIMIT) : $entries),
            'truncated' => $truncated,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     *
     * @return array<int, array{letter: string, entries: array<int, array<string, mixed>>}>
     */
    private static function group(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $letter = mb_strtoupper(mb_substr($entry['sortName'], 0, 1));
            $letter = preg_match('/^\p{L}$/u', $letter) === 1 ? $letter : '#';
            $groups[$letter] ??= ['letter' => $letter, 'entries' => []];
            $groups[$letter]['entries'][] = $entry;
        }

        return array_values($groups);
    }

    /**
     * One entry per family with two or more listed members and one per other listed person,
     * sorted by name.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function entries(): array
    {
        $settings = self::settings();
        $entries = [];
        $byFamily = [];
        foreach (self::listedPeople() as $person) {
            $familyId = (int) $person->getFamId();
            if ($familyId === 0) {
                $entries[] = self::personEntry($person, $settings);
            } else {
                $byFamily[$familyId][] = $person;
            }
        }
        foreach ($byFamily as $members) {
            $entries[] = count($members) === 1
                ? self::personEntry($members[0], $settings)
                : self::familyEntry($members, $settings);
        }

        usort($entries, static fn (array $a, array $b): int => strnatcasecmp($a['sortName'], $b['sortName']) ?: $a['id'] <=> $b['id']);

        return array_map(static fn (array $entry): array => $entry + [
            'searchText' => mb_strtolower(implode(' ', [$entry['name'], ...array_column($entry['members'], 'name')])),
        ], $entries);
    }

    /** Whether the directory shows this person's photo. */
    public static function showsPersonPhoto(int $personId): bool
    {
        return self::settings()['fields']['photo'] && self::listedPeople(personId: $personId) !== [];
    }

    /** Whether the directory shows this family's photo: only a family entry carries one. */
    public static function showsFamilyPhoto(int $familyId): bool
    {
        return self::settings()['fields']['photo'] && count(self::listedPeople(familyId: $familyId)) > 1;
    }

    /**
     * Living, active, reviewed people in a listed classification, outside deactivated families.
     *
     * @return Person[]
     */
    private static function listedPeople(?int $personId = null, ?int $familyId = null): array
    {
        $listed = self::settings()['listed'];
        if ($listed === []) {
            return [];
        }

        $query = PersonQuery::create()
            ->filterByClsId($listed, Criteria::IN)
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->filterByDateDeactivated(null, Criteria::ISNULL)
            ->filterByNeedsReview(false)
            ->leftJoinWithFamily()
            ->where('Family.DateDeactivated IS NULL');
        if ($personId !== null) {
            $query->filterById($personId);
        }
        if ($familyId !== null) {
            $query->filterByFamId($familyId);
        }

        return self::withRecordPolicy($query->find()->getData());
    }

    /**
     * #10524's protected-household record policy applies here once it is merged.
     *
     * @param Person[] $people
     *
     * @return Person[]
     */
    private static function withRecordPolicy(array $people): array
    {
        return $people;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private static function personEntry(Person $person, array $settings): array
    {
        $fields = $settings['fields'];
        $family = $person->getFamily();
        $homePhone = $fields['homePhone'] ? $person->getResolvedHomePhone() : '';

        return [
            'kind' => 'person',
            'id' => (int) $person->getId(),
            'name' => $person->getFullName(),
            'sortName' => trim($person->getLastName() . ', ' . $person->getFirstName(), ', '),
            'photoUrl' => $fields['photo'] ? self::photoUrl('Person', (int) $person->getId()) : '',
            'initials' => self::initials($person),
            'address' => $fields['address'] ? self::lines(Family::formatAddressBlock([
                'Address1' => $person->getResolvedAddress1(),
                'Address2' => $person->getResolvedAddress2(),
                'City' => $person->getResolvedCity(),
                'State' => $person->getResolvedState(),
                'Zip' => $person->getResolvedZip(),
                'Country' => $person->getResolvedCountry(),
            ])) : [],
            'mailingAddress' => self::mailingAddress($family, $fields),
            'homePhone' => $homePhone,
            'anniversary' => '',
            'members' => [self::member($person, $settings, $homePhone)],
        ];
    }

    /**
     * @param Person[]             $people
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private static function familyEntry(array $people, array $settings): array
    {
        $fields = $settings['fields'];
        $family = $people[0]->getFamily();
        $people = self::sortedMembers($people);
        $partners = array_map(
            static fn (Person $person): string => (string) $person->getFirstName(),
            array_filter($people, static fn (Person $person): bool => self::roleRank($person) < 2)
        );
        $homePhone = $fields['homePhone'] ? (string) $family->getHomePhone() : '';

        return [
            'kind' => 'family',
            'id' => (int) $family->getId(),
            'name' => $partners === []
                ? (string) $family->getName()
                : sprintf('%s, %s', $family->getName(), implode(' ' . gettext('and') . ' ', $partners)),
            'sortName' => (string) $family->getName(),
            'photoUrl' => $fields['photo'] ? self::photoUrl('Family', (int) $family->getId()) : '',
            'initials' => mb_strtoupper(mb_substr((string) $family->getName(), 0, 1)),
            'address' => $fields['address'] ? self::lines(Family::formatAddressBlock($family->getPrimaryAddressParts())) : [],
            'mailingAddress' => self::mailingAddress($family, $fields),
            'homePhone' => $homePhone,
            'anniversary' => self::anniversary($family, $settings['anniversary']),
            'members' => array_map(static fn (Person $person): array => self::member($person, $settings, $homePhone), $people),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private static function member(Person $person, array $settings, string $entryHomePhone): array
    {
        $fields = $settings['fields'];
        $personalPhone = $fields['personalPhone'] ? trim((string) $person->getHomePhone()) : '';

        return [
            'id' => (int) $person->getId(),
            'name' => $person->getFullName(),
            'initials' => self::initials($person),
            'photoUrl' => $fields['photo'] ? self::photoUrl('Person', (int) $person->getId()) : '',
            'birthday' => self::birthday($person, $settings['birthday']),
            'homePhone' => $personalPhone === $entryHomePhone ? '' : $personalPhone,
            'workPhone' => $fields['workPhone'] ? trim((string) $person->getWorkPhone()) : '',
            'cellPhone' => $fields['cellPhone'] ? trim((string) $person->getCellPhone()) : '',
            'email' => $fields['email'] ? trim((string) $person->getEmail()) : '',
            'workEmail' => $fields['workEmail'] ? trim((string) $person->getWorkEmail()) : '',
        ];
    }

    /**
     * Heads first, then spouses, children and everyone else, as the printed directory orders
     * them; within a role, oldest first.
     *
     * @param Person[] $people
     *
     * @return Person[]
     */
    private static function sortedMembers(array $people): array
    {
        usort($people, static fn (Person $a, Person $b): int => [self::roleRank($a), (int) $a->getBirthYear() ?: PHP_INT_MAX, $a->getFirstName()]
            <=> [self::roleRank($b), (int) $b->getBirthYear() ?: PHP_INT_MAX, $b->getFirstName()]);

        return $people;
    }

    private static function roleRank(Person $person): int
    {
        $role = (int) $person->getFmrId();
        foreach (['sDirRoleHead', 'sDirRoleSpouse', 'sDirRoleChild'] as $rank => $config) {
            if (in_array($role, array_map('intval', explode(',', (string) SystemConfig::getValue($config))), true)) {
                return $rank;
            }
        }

        return 3;
    }

    private static function birthday(Person $person, string $mode): string
    {
        $month = (int) $person->getBirthMonth();
        $day = (int) $person->getBirthDay();
        if ($mode === self::DATE_HIDDEN || $month === 0 || $day === 0) {
            return '';
        }

        return self::formatDate($mode === self::DATE_WITH_YEAR && !$person->hideAge() ? (int) $person->getBirthYear() : null, $month, $day);
    }

    private static function anniversary(Family $family, string $mode): string
    {
        $date = $family->getWeddingdate();
        if ($mode === self::DATE_HIDDEN || !$date instanceof DateTimeInterface) {
            return '';
        }

        return self::formatDate($mode === self::DATE_WITH_YEAR ? (int) $date->format('Y') : null, (int) $date->format('n'), (int) $date->format('j'));
    }

    /** A year of null formats month and day only; 2000 is a leap year, so 29 February survives. */
    private static function formatDate(?int $year, int $month, int $day): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-n-j', sprintf('%d-%d-%d', $year ?? 2000, $month, $day));
        if ($date === false || (int) $date->format('n') !== $month) {
            return '';
        }

        return $date->format((string) SystemConfig::getValue($year === null ? 'sDateFormatNoYear' : 'sDateFormatLong'));
    }

    /**
     * @param array<string, bool> $fields
     *
     * @return string[]
     */
    private static function mailingAddress(?Family $family, array $fields): array
    {
        if (!$fields['address'] || !$fields['mailingAddress'] || $family === null || !$family->hasDistinctMailingAddress()) {
            return [];
        }

        return self::lines($family->getMailingAddressLines());
    }

    /** @return string[] */
    private static function lines(string $block): array
    {
        return array_values(array_filter(explode("\n", $block), static fn (string $line): bool => trim($line) !== ''));
    }

    private static function photoUrl(string $type, int $id): string
    {
        $photo = new Photo($type, $id);
        if (!$photo->hasUploadedPhoto()) {
            return '';
        }

        $url = SystemURLs::getRootPath() . '/api/portal/directory/' . strtolower($type) . '/' . $id . '/photo';
        $version = $photo->getPhotoModifiedTime();

        return $version ? $url . '?v=' . $version : $url;
    }

    private static function initials(Person $person): string
    {
        return mb_strtoupper(mb_substr((string) $person->getFirstName(), 0, 1) . mb_substr((string) $person->getLastName(), 0, 1));
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return int[]
     */
    private static function validClassificationIds(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(gettext('Send the classifications as a list of ids.'));
        }

        $known = array_flip(self::classificationIds());
        $ids = [];
        foreach ($value as $id) {
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                throw new InvalidArgumentException(gettext('Send the classifications as a list of ids.'));
            }
            if (!isset($known[(int) $id])) {
                throw new InvalidArgumentException(sprintf(gettext('There is no classification with id %d.'), (int) $id));
            }
            $ids[(int) $id] = (int) $id;
        }

        return array_values($ids);
    }

    /**
     * Every classification id the church has, plus 0 for people with none.
     *
     * @return int[]
     */
    private static function classificationIds(): array
    {
        $ids = ListOptionQuery::create()->filterById(self::CLASSIFICATION_LIST)->select(['OptionId'])->find()->toArray();

        return [...array_map('intval', $ids), 0];
    }
}
