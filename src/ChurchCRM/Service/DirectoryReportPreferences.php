<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\User;

/**
 * Last-used Directory Report Options for a user (GitHub #11465).
 *
 * user_settings.setting_value is VARCHAR(50), so each DirectoryReportService
 * option key is stored as its own compact setting rather than one JSON blob.
 * ID lists that do not fit in one value are split across numbered keys
 * ({base}.1, {base}.2, …) with the base key holding the chunk count.
 * Layout, title page, paper, font, and child roles are intentionally omitted.
 */
class DirectoryReportPreferences
{
    public const SAVED = 'reports.directory.saved';
    public const CLASSIFICATIONS = 'reports.directory.classifications';
    public const GROUPS = 'reports.directory.groups';
    public const EXCLUDE_INACTIVE = 'reports.directory.excludeInactive';
    public const HEAD_ROLES = 'reports.directory.headRoles';
    public const SPOUSE_ROLES = 'reports.directory.spouseRoles';
    public const SHOW = 'reports.directory.show';
    public const CUSTOM_FIELDS = 'reports.directory.customFields';

    /** Fixed order matching DirectoryReportService::$show keys. */
    public const SHOW_KEYS = [
        'address',
        'mailing',
        'wedding',
        'birthday',
        'familyPhone',
        'familyWork',
        'familyCell',
        'personalPhone',
        'personalWork',
        'personalCell',
        'personalEmail',
        'personalWorkEmail',
        'photo',
    ];

    private const MAX_VALUE = 50;

    /**
     * Hard cap on numbered chunks per list. A few dozen covers any real ID list
     * and bounds loops when the base count key is attacker-controlled via the
     * user-settings API (maintainer review on #11571).
     */
    public const MAX_CHUNKS = 40;

    /**
     * Primary setting names (list bases hold a chunk count; data lives in {base}.N).
     *
     * @return list<string>
     */
    public static function settingNames(): array
    {
        return [
            self::SAVED,
            self::CLASSIFICATIONS,
            self::GROUPS,
            self::EXCLUDE_INACTIVE,
            self::HEAD_ROLES,
            self::SPOUSE_ROLES,
            self::SHOW,
            self::CUSTOM_FIELDS,
        ];
    }

    /**
     * Bases that store integer lists across numbered chunk keys.
     *
     * @return list<string>
     */
    public static function listSettingBases(): array
    {
        return [
            self::CLASSIFICATIONS,
            self::GROUPS,
            self::HEAD_ROLES,
            self::SPOUSE_ROLES,
            self::CUSTOM_FIELDS,
        ];
    }

    /**
     * @param array<string, mixed> $options DirectoryReportService option keys only
     */
    public function save(User $user, array $options): void
    {
        $classifications = $options['classifications'] ?? [];
        if ($classifications === null) {
            $classifications = [];
        }

        $this->saveIdList($user, self::CLASSIFICATIONS, $classifications);
        $this->saveIdList($user, self::GROUPS, $options['groups'] ?? []);
        $user->setSetting(self::EXCLUDE_INACTIVE, !empty($options['excludeInactive']) ? '1' : '0');
        $this->saveIdList($user, self::HEAD_ROLES, $options['headRoles'] ?? []);
        $this->saveIdList($user, self::SPOUSE_ROLES, $options['spouseRoles'] ?? []);
        $user->setSetting(self::SHOW, $this->packShow((array) ($options['show'] ?? [])));
        $this->saveIdList($user, self::CUSTOM_FIELDS, $options['customFieldOrders'] ?? []);
        $user->setSetting(self::SAVED, '1');
    }

    public function clear(User $user): void
    {
        foreach (self::listSettingBases() as $base) {
            $this->clearIdList($user, $base);
        }
        $user->setSetting(self::EXCLUDE_INACTIVE, '');
        $user->setSetting(self::SHOW, '');
        $user->setSetting(self::SAVED, '');
    }

    public function hasSaved(User $user): bool
    {
        return $user->getSettingValue(self::SAVED) === '1';
    }

    /**
     * @return array{
     *     classifications: list<int>,
     *     groups: list<int>,
     *     excludeInactive: bool,
     *     headRoles: list<int>,
     *     spouseRoles: list<int>,
     *     show: array<string, bool>,
     *     customFieldOrders: list<int>
     * }|null
     */
    public function load(User $user): ?array
    {
        if (!$this->hasSaved($user)) {
            return null;
        }

        return [
            'classifications' => $this->loadIdList($user, self::CLASSIFICATIONS),
            'groups' => $this->loadIdList($user, self::GROUPS),
            'excludeInactive' => $user->getSettingValue(self::EXCLUDE_INACTIVE) === '1',
            'headRoles' => $this->loadIdList($user, self::HEAD_ROLES),
            'spouseRoles' => $this->loadIdList($user, self::SPOUSE_ROLES),
            'show' => $this->unpackShow($user->getSettingValue(self::SHOW)),
            'customFieldOrders' => $this->loadIdList($user, self::CUSTOM_FIELDS),
        ];
    }

    /**
     * @param array<int|string> $ids
     */
    private function saveIdList(User $user, string $base, array $ids): void
    {
        $chunks = $this->chunkIds($ids);
        if (count($chunks) > self::MAX_CHUNKS) {
            $chunks = array_slice($chunks, 0, self::MAX_CHUNKS);
        }
        $count = max(count($chunks), 1);
        $previousCount = $this->boundedChunkCount($user->getSettingValue($base));

        for ($i = 1; $i <= $count; $i++) {
            $user->setSetting($base . '.' . $i, $chunks[$i - 1] ?? '');
        }
        // Only clear chunk keys that were previously stored — never invent empty rows.
        for ($i = $count + 1; $i <= $previousCount; $i++) {
            $name = $base . '.' . $i;
            if ($user->getSetting($name) !== null) {
                $user->setSetting($name, '');
            }
        }
        $user->setSetting($base, (string) $count);
    }

    /**
     * @return list<int>
     */
    private function loadIdList(User $user, string $base): array
    {
        $raw = $user->getSettingValue($base);
        // Legacy single-value writes stored the CSV in the base key itself.
        if ($raw !== '' && !ctype_digit($raw)) {
            return $this->unpackIds($raw);
        }

        $count = $this->boundedChunkCount($raw);
        if ($count < 1) {
            return [];
        }

        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $name = $base . '.' . $i;
            if ($user->getSetting($name) === null) {
                break;
            }
            $value = $user->getSettingValue($name);
            if ($value === '') {
                break;
            }
            foreach ($this->unpackIds($value) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function clearIdList(User $user, string $base): void
    {
        $count = $this->boundedChunkCount($user->getSettingValue($base));
        for ($i = 1; $i <= $count; $i++) {
            $name = $base . '.' . $i;
            if ($user->getSetting($name) !== null) {
                $user->setSetting($name, '');
            }
        }
        $user->setSetting($base, '');
    }

    /**
     * Digits-only chunk counts, hard-capped so a huge user_settings value cannot
     * force unbounded getSettingValue / setSetting loops.
     */
    private function boundedChunkCount(string $raw): int
    {
        if ($raw === '' || !ctype_digit($raw)) {
            return 0;
        }

        return min((int) $raw, self::MAX_CHUNKS);
    }

    /**
     * Split IDs into CSV chunks that each fit in VARCHAR(50). Never drops IDs.
     *
     * @param array<int|string> $ids
     * @return list<string>
     */
    private function chunkIds(array $ids): array
    {
        $chunks = [];
        $parts = [];
        $length = 0;

        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                continue;
            }
            $piece = (string) (int) $id;
            $extra = $parts === [] ? strlen($piece) : strlen($piece) + 1;
            if ($length + $extra > self::MAX_VALUE) {
                if ($parts !== []) {
                    $chunks[] = implode(',', $parts);
                }
                $parts = [$piece];
                $length = strlen($piece);
                continue;
            }
            $parts[] = $piece;
            $length += $extra;
        }

        if ($parts !== []) {
            $chunks[] = implode(',', $parts);
        }

        return $chunks;
    }

    /**
     * @return list<int>
     */
    private function unpackIds(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, bool> $show
     */
    private function packShow(array $show): string
    {
        $bits = '';
        foreach (self::SHOW_KEYS as $key) {
            $bits .= !empty($show[$key]) ? '1' : '0';
        }

        return $bits;
    }

    /**
     * @return array<string, bool>
     */
    private function unpackShow(string $value): array
    {
        $show = [];
        foreach (self::SHOW_KEYS as $i => $key) {
            $show[$key] = isset($value[$i]) && $value[$i] === '1';
        }

        return $show;
    }
}
