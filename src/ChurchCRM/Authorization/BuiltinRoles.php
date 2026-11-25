<?php

declare(strict_types=1);

namespace ChurchCRM\Authorization;

use LogicException;

/**
 * One built-in role per V1 switch, plus Legacy Administrator (RBAC architecture §4).
 * `ministries.manage-mine` is the only grant whose scope is not the whole church.
 */
final class BuiltinRoles
{
    public const SCOPE_ALL = 'all';
    public const SCOPE_MY_MINISTRIES = 'my-ministries';

    /**
     * @return array<string, list<array{permission: string, scope: string}>>
     */
    public static function all(): array
    {
        return [
            'people.add' => self::grantsFor([
                'people.create',
                'families.create',
                'cart.to-family',
            ]),
            'people.edit' => self::grantsFor([
                'people.update',
                'families.update',
                'people.properties.assign',
                'whycame.update',
                'people.verify',
                'groups.member-properties.update',
            ]),
            'people.delete' => self::grantsFor([
                'people.delete',
                'families.delete',
            ]),
            'groups.manage' => self::grantsFor([
                'groups.manage',
                'groups.properties.manage',
                'sundayschool.manage',
            ]),
            'finance' => self::grantsFor([
                'finance.deposits.manage',
                'finance.pledges.manage',
                'finance.reports.read',
            ]),
            'fundraisers.manage' => self::grantsFor([
                'fundraisers.manage',
            ]),
            'ministries.manage-all' => self::grantsFor([
                'ministries.manage',
            ]),
            'ministries.manage-mine' => [
                self::grant('ministries.manage', self::SCOPE_MY_MINISTRIES),
            ],
            'notes' => self::grantsFor([
                'notes.read',
                'notes.create',
            ]),
            'events.add' => self::grantsFor([
                'events.create',
                'calendars.manage',
            ]),
            'email' => self::grantsFor([
                'email.mailto',
            ]),
            'administrator' => self::grantsFor([
                'system.admin',
                'roles.assign',
            ]),
            'legacy-administrator' => self::grantsFor(PermissionRegistry::keys()),
        ];
    }

    /**
     * @return list<array{permission: string, scope: string}>
     */
    public static function grants(string $role): array
    {
        $roles = self::all();
        if (!array_key_exists($role, $roles)) {
            throw new LogicException('Unknown built-in role: ' . $role);
        }

        return $roles[$role];
    }

    /**
     * @param list<string> $permissions
     *
     * @return list<array{permission: string, scope: string}>
     */
    private static function grantsFor(array $permissions): array
    {
        return array_map(
            static fn (string $permission): array => self::grant($permission, self::SCOPE_ALL),
            $permissions,
        );
    }

    /**
     * @return array{permission: string, scope: string}
     */
    private static function grant(string $permission, string $scope): array
    {
        if (PermissionRegistry::get($permission) === null) {
            throw new LogicException('Built-in role grants an unknown permission: ' . $permission);
        }

        return ['permission' => $permission, 'scope' => $scope];
    }
}
