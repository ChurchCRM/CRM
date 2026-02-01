<?php

namespace ChurchCRM\Plugin\Hook;

use ChurchCRM\Utils\LoggerUtils;

/**
 * Hook Manager for ChurchCRM Plugin System.
 *
 * Implements WordPress-style actions and filters for extensibility.
 *
 * Actions: Execute callbacks at specific points (no return value)
 * Filters: Modify data through a chain of callbacks (returns modified value)
 *
 * Example usage:
 *   // Register an action
 *   HookManager::addAction('person.created', function($person) {
 *       // Do something when person is created
 *   });
 *
 *   // Trigger an action
 *   HookManager::doAction('person.created', $person);
 *
 *   // Register a filter
 *   HookManager::addFilter('menu.items', function($items) {
 *       $items[] = new MenuItem(...);
 *       return $items;
 *   });
 *
 *   // Apply a filter
 *   $menuItems = HookManager::applyFilters('menu.items', $menuItems);
 */
class HookManager
{
    /**
     * Registered action callbacks.
     *
     * @var array<string, array<int, callable[]>>
     */
    private static array $actions = [];

    /**
     * Registered filter callbacks.
     *
     * @var array<string, array<int, callable[]>>
     */
    private static array $filters = [];

    /**
     * Track how many times each action has been called.
     *
     * @var array<string, int>
     */
    private static array $actionCounts = [];

    /**
     * Currently executing hooks (for nested hook detection).
     *
     * @var string[]
     */
    private static array $currentHooks = [];

    /**
     * Add a callback to an action hook.
     *
     * @param string   $hookName Hook identifier (e.g., 'person.created')
     * @param callable $callback Callback to execute
     * @param int      $priority Lower numbers execute first (default: 10)
     */
    public static function addAction(string $hookName, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$actions[$hookName])) {
            self::$actions[$hookName] = [];
        }
        if (!isset(self::$actions[$hookName][$priority])) {
            self::$actions[$hookName][$priority] = [];
        }
        self::$actions[$hookName][$priority][] = $callback;
    }

    /**
     * Remove a specific callback from an action hook.
     *
     * @param string   $hookName Hook identifier
     * @param callable $callback Callback to remove
     * @param int      $priority Priority it was registered with
     */
    public static function removeAction(string $hookName, callable $callback, int $priority = 10): bool
    {
        if (!isset(self::$actions[$hookName][$priority])) {
            return false;
        }

        $key = array_search($callback, self::$actions[$hookName][$priority], true);
        if ($key !== false) {
            unset(self::$actions[$hookName][$priority][$key]);

            return true;
        }

        return false;
    }

    /**
     * Execute all callbacks for an action hook.
     *
     * @param string $hookName Hook identifier
     * @param mixed  ...$args  Arguments to pass to callbacks
     */
    public static function doAction(string $hookName, ...$args): void
    {
        self::$currentHooks[] = $hookName;

        if (!isset(self::$actionCounts[$hookName])) {
            self::$actionCounts[$hookName] = 0;
        }
        self::$actionCounts[$hookName]++;

        if (!isset(self::$actions[$hookName])) {
            array_pop(self::$currentHooks);

            return;
        }

        // Sort by priority (lower numbers first)
        ksort(self::$actions[$hookName]);

        foreach (self::$actions[$hookName] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    call_user_func_array($callback, $args);
                } catch (\Throwable $e) {
                    LoggerUtils::getAppLogger()->error(
                        "Error executing action hook '$hookName' at priority $priority",
                        ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                    );
                }
            }
        }

        array_pop(self::$currentHooks);
    }

    /**
     * Add a callback to a filter hook.
     *
     * @param string   $hookName Hook identifier (e.g., 'menu.items')
     * @param callable $callback Callback that receives and returns the filtered value
     * @param int      $priority Lower numbers execute first (default: 10)
     */
    public static function addFilter(string $hookName, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$filters[$hookName])) {
            self::$filters[$hookName] = [];
        }
        if (!isset(self::$filters[$hookName][$priority])) {
            self::$filters[$hookName][$priority] = [];
        }
        self::$filters[$hookName][$priority][] = $callback;
    }

    /**
     * Remove a specific callback from a filter hook.
     *
     * @param string   $hookName Hook identifier
     * @param callable $callback Callback to remove
     * @param int      $priority Priority it was registered with
     */
    public static function removeFilter(string $hookName, callable $callback, int $priority = 10): bool
    {
        if (!isset(self::$filters[$hookName][$priority])) {
            return false;
        }

        $key = array_search($callback, self::$filters[$hookName][$priority], true);
        if ($key !== false) {
            unset(self::$filters[$hookName][$priority][$key]);

            return true;
        }

        return false;
    }

    /**
     * Apply all filter callbacks and return the modified value.
     *
     * @param string $hookName Hook identifier
     * @param mixed  $value    The value to filter
     * @param mixed  ...$args  Additional arguments to pass to callbacks
     *
     * @return mixed The filtered value
     */
    public static function applyFilters(string $hookName, mixed $value, ...$args): mixed
    {
        self::$currentHooks[] = $hookName;

        if (!isset(self::$filters[$hookName])) {
            array_pop(self::$currentHooks);

            return $value;
        }

        // Sort by priority (lower numbers first)
        ksort(self::$filters[$hookName]);

        foreach (self::$filters[$hookName] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $value = call_user_func_array($callback, [$value, ...$args]);
                } catch (\Throwable $e) {
                    LoggerUtils::getAppLogger()->error(
                        "Error executing filter hook '$hookName' at priority $priority",
                        ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                    );
                }
            }
        }

        array_pop(self::$currentHooks);

        return $value;
    }

    /**
     * Check if an action hook has any callbacks registered.
     */
    public static function hasAction(string $hookName): bool
    {
        return isset(self::$actions[$hookName]) && !empty(self::$actions[$hookName]);
    }

    /**
     * Check if a filter hook has any callbacks registered.
     */
    public static function hasFilter(string $hookName): bool
    {
        return isset(self::$filters[$hookName]) && !empty(self::$filters[$hookName]);
    }

    /**
     * Get the number of times an action has been triggered.
     */
    public static function didAction(string $hookName): int
    {
        return self::$actionCounts[$hookName] ?? 0;
    }

    /**
     * Get the currently executing hook name.
     */
    public static function currentHook(): ?string
    {
        return end(self::$currentHooks) ?: null;
    }

    /**
     * Check if currently executing within a specific hook.
     */
    public static function doingHook(?string $hookName = null): bool
    {
        if ($hookName === null) {
            return !empty(self::$currentHooks);
        }

        return in_array($hookName, self::$currentHooks, true);
    }

    /**
     * Get all registered hooks (for debugging).
     *
     * @return array{actions: array, filters: array}
     */
    public static function getAllHooks(): array
    {
        return [
            'actions' => array_keys(self::$actions),
            'filters' => array_keys(self::$filters),
        ];
    }

    /**
     * Clear all hooks (useful for testing).
     */
    public static function reset(): void
    {
        self::$actions = [];
        self::$filters = [];
        self::$actionCounts = [];
        self::$currentHooks = [];
    }
}
