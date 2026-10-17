<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\SystemConfig;
use Throwable;

/**
 * The Member Portal's own configuration values.
 *
 * Every portal setting is a `ConfigItem` in `config_cfg` with no System
 * Settings category, declared by `SystemConfig::init()` and edited on
 * Admin → Member Portal (design P9). Reading one through this class rather
 * than through `SystemConfig` directly buys two things:
 *
 *  - a named default for an installation whose database predates the item, and
 *  - a single place to look when a new portal switch is added.
 *
 * `SystemConfig::getBooleanValue()` throws for a name it does not know, so an
 * unknown item is treated as "off" here. That is what a member should see
 * while an upgrade is half-applied: the smaller surface, never the larger one.
 */
class PortalSettings
{
    /** Whether a member may change their own date of birth (design §5.2). */
    public const ALLOW_BIRTHDAY_EDIT = 'bPortalAllowBirthdayEdit';

    public static function allowsBirthdayEdit(): bool
    {
        return self::getBoolean(self::ALLOW_BIRTHDAY_EDIT);
    }

    /**
     * A boolean setting, or false when this installation does not declare it.
     */
    private static function getBoolean(string $name): bool
    {
        try {
            return SystemConfig::getBooleanValue($name);
        } catch (Throwable) {
            return false;
        }
    }
}
