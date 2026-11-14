<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\User;

/**
 * Last-used Directory Report Options for a user (GitHub #11465).
 *
 * user_settings.setting_value is VARCHAR(50), so each DirectoryReportService
 * option key is stored as its own compact setting rather than one JSON blob.
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
     * @param array<string, mixed> $options DirectoryReportService option keys only
     */
    public function save(User $user, array $options): void
    {
        $classifications = $options['classifications'] ?? [];
        if ($classifications === null) {
            $classifications = [];
        }

        $user->setSetting(self::CLASSIFICATIONS, $this->packIds($classifications));
        $user->setSetting(self::GROUPS, $this->packIds($options['groups'] ?? []));
        $user->setSetting(self::EXCLUDE_INACTIVE, !empty($options['excludeInactive']) ? '1' : '0');
        $user->setSetting(self::HEAD_ROLES, $this->packIds($options['headRoles'] ?? []));
        $user->setSetting(self::SPOUSE_ROLES, $this->packIds($options['spouseRoles'] ?? []));
        $user->setSetting(self::SHOW, $this->packShow((array) ($options['show'] ?? [])));
        $user->setSetting(self::CUSTOM_FIELDS, $this->packIds($options['customFieldOrders'] ?? []));
        $user->setSetting(self::SAVED, '1');
    }

    public function clear(User $user): void
    {
        foreach (self::settingNames() as $name) {
            $user->setSetting($name, '');
        }
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
            'classifications' => $this->unpackIds($user->getSettingValue(self::CLASSIFICATIONS)),
            'groups' => $this->unpackIds($user->getSettingValue(self::GROUPS)),
            'excludeInactive' => $user->getSettingValue(self::EXCLUDE_INACTIVE) === '1',
            'headRoles' => $this->unpackIds($user->getSettingValue(self::HEAD_ROLES)),
            'spouseRoles' => $this->unpackIds($user->getSettingValue(self::SPOUSE_ROLES)),
            'show' => $this->unpackShow($user->getSettingValue(self::SHOW)),
            'customFieldOrders' => $this->unpackIds($user->getSettingValue(self::CUSTOM_FIELDS)),
        ];
    }

    /**
     * @param array<int|string> $ids
     */
    private function packIds(array $ids): string
    {
        $parts = [];
        $length = 0;
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                continue;
            }
            $piece = (string) (int) $id;
            $extra = $parts === [] ? strlen($piece) : strlen($piece) + 1;
            if ($length + $extra > self::MAX_VALUE) {
                break;
            }
            $parts[] = $piece;
            $length += $extra;
        }

        return implode(',', $parts);
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
