<?php

declare(strict_types=1);

namespace ChurchCRM\Authorization;

/**
 * Staff permission keys (RBAC architecture §3.1). An unknown key is absent;
 * the authorization service denies it. This registry does not decide access.
 */
final class PermissionRegistry
{
    public const SINCE = '7.9.0';

    /** @var array<string, PermissionDefinition>|null */
    private static ?array $byKey = null;

    public static function get(string $key): ?PermissionDefinition
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<string, PermissionDefinition> in registration order
     */
    public static function all(): array
    {
        return self::$byKey ??= self::load();
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, PermissionDefinition>
     */
    private static function load(): array
    {
        $definitions = [];
        foreach (self::entries() as $entry) {
            $definitions[$entry->key] = $entry;
        }

        return $definitions;
    }

    /**
     * @return list<PermissionDefinition>
     */
    private static function entries(): array
    {
        return [
            self::ordinary('people.create'),
            self::ordinary('families.create'),
            self::ordinary('cart.to-family'),
            self::ordinary('people.update'),
            self::ordinary('families.update'),
            self::ordinary('people.properties.assign'),
            self::ordinary('whycame.update'),
            self::ordinary('people.verify'),
            self::ordinary('groups.member-properties.update'),
            self::ordinary('people.delete'),
            self::ordinary('families.delete'),
            self::ordinary('groups.manage'),
            self::ordinary('groups.properties.manage'),
            self::ordinary('sundayschool.manage'),
            self::sensitive('finance.deposits.manage', 'bEnabledFinance'),
            self::sensitive('finance.pledges.manage', 'bEnabledFinance'),
            self::sensitive('finance.reports.read', 'bEnabledFinance'),
            self::ordinary('fundraisers.manage', 'bEnabledFundraiser'),
            self::ordinary('ministries.manage', 'sVolunteerVersion'),
            self::ordinary('notes.read'),
            self::ordinary('notes.create'),
            self::ordinary('events.create', 'bEnabledEvents'),
            self::ordinary('calendars.manage', 'bEnabledEvents'),
            self::ordinary('email.mailto'),
            self::ordinary('system.admin'),
            self::ordinary('roles.assign'),
            self::sensitive('export.people'),
            self::sensitive('communication.log.read'),
            self::sensitive('notes.private.read'),
        ];
    }

    private static function ordinary(string $key, ?string $module = null): PermissionDefinition
    {
        return new PermissionDefinition($key, $module, PermissionDefinition::ORDINARY, self::SINCE);
    }

    private static function sensitive(string $key, ?string $module = null): PermissionDefinition
    {
        return new PermissionDefinition($key, $module, PermissionDefinition::SENSITIVE, self::SINCE);
    }
}
