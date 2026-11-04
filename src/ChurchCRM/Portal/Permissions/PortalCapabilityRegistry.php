<?php

namespace ChurchCRM\Portal\Permissions;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginInterface;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Utils\LoggerUtils;
use Throwable;

/**
 * Every portal capability and rule this installation knows (member sign-in
 * design §8.1, §8.5). Capabilities live in code, not in a table: core registers
 * its catalog on first use, and each installed plugin's `portal.capabilities`
 * block is read from its manifest. A plugin that is turned off keeps its
 * capabilities listed, so the admin pages can show them and a check can say
 * why it refuses, but its rules are not loaded and `isOwnerActive()` is false.
 */
final class PortalCapabilityRegistry
{
    private const PLUGIN_KEY_PATTERN = '/^plugin\.([a-z0-9][a-z0-9-]*)\.[a-z0-9][a-z0-9._-]*$/';
    private const SCOPE_TYPE_PATTERN = '/^[a-z][a-z0-9_-]{0,31}$/';

    /** @var array<string, PortalCapability> */
    private static array $capabilities = [];

    /** @var array<string, array{rule: PortalRule, owner: string}> */
    private static array $rules = [];

    /** @var array<string, bool> */
    private static array $ownerActive = [];

    private static bool $booted = false;

    public static function register(PortalCapability $capability): void
    {
        self::$capabilities[$capability->key] = $capability;
    }

    public static function registerRule(PortalRule $rule, string $owner = PortalCapability::OWNER_CORE): void
    {
        self::$rules[$rule->getKey()] = ['rule' => $rule, 'owner' => $owner];
    }

    public static function get(string $key): ?PortalCapability
    {
        self::boot();

        return self::$capabilities[$key] ?? null;
    }

    /**
     * @return array<string, PortalCapability> in registration order, core first
     */
    public static function all(): array
    {
        self::boot();

        return self::$capabilities;
    }

    /**
     * The rules whose owner is on.
     *
     * @return array<string, PortalRule>
     */
    public static function activeRules(): array
    {
        self::boot();

        $rules = [];
        foreach (self::$rules as $key => $entry) {
            if (self::isOwnerActive($entry['owner'])) {
                $rules[$key] = $entry['rule'];
            }
        }

        return $rules;
    }

    /** Who registered a rule: `core` or a plugin id. A plugin's rules give only its own capabilities. */
    public static function ruleOwner(string $ruleKey): string
    {
        self::boot();

        return self::$rules[$ruleKey]['owner'] ?? PortalCapability::OWNER_CORE;
    }

    public static function isOwnerActive(string $owner): bool
    {
        if ($owner === PortalCapability::OWNER_CORE) {
            return true;
        }

        return self::$ownerActive[$owner] ??= PluginManager::isPluginActive($owner);
    }

    /** The prefix every capability key of a plugin carries (#10511 §7). */
    public static function pluginKeyPrefix(string $pluginId): string
    {
        return 'plugin.' . $pluginId . '.';
    }

    /** @internal tests, and code that changes what is registered within one request */
    public static function reset(): void
    {
        self::$capabilities = [];
        self::$rules = [];
        self::$ownerActive = [];
        self::$booted = false;
    }

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        CorePortalCapabilities::register();

        PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');
        foreach (PluginManager::getDiscoveredPluginMetadata() as $pluginId => $metadata) {
            foreach ($metadata->getPortalCapabilities() as $declaration) {
                $capability = self::pluginCapability((string) $pluginId, $declaration);
                if ($capability !== null) {
                    self::register($capability);
                }
            }
        }
        foreach (PluginManager::getActivePlugins() as $pluginId => $plugin) {
            self::registerPluginRules((string) $pluginId, $plugin);
        }
    }

    private static function pluginCapability(string $pluginId, mixed $declaration): ?PortalCapability
    {
        $key = is_array($declaration) ? (string) ($declaration['key'] ?? '') : '';
        $scopeType = is_array($declaration) ? (string) ($declaration['scopeType'] ?? PortalScope::NONE) : '';
        $matches = [];
        if (
            !preg_match(self::PLUGIN_KEY_PATTERN, $key, $matches)
            || $matches[1] !== $pluginId
            || !preg_match(self::SCOPE_TYPE_PATTERN, $scopeType)
        ) {
            LoggerUtils::getAppLogger()->warning('Ignoring an invalid portal capability in a plugin manifest', [
                'plugin' => $pluginId,
                'key' => $key,
            ]);

            return null;
        }

        return new PortalCapability(
            $key,
            (string) ($declaration['label'] ?? $key),
            (string) ($declaration['description'] ?? ''),
            $pluginId,
            $scopeType,
        );
    }

    private static function registerPluginRules(string $pluginId, PluginInterface $plugin): void
    {
        try {
            $rules = $plugin->getPortalRules();
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->error('A plugin failed to list its portal rules', [
                'plugin' => $pluginId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($rules as $rule) {
            if ($rule instanceof PortalRule) {
                self::registerRule($rule, $pluginId);
            }
        }
    }
}
