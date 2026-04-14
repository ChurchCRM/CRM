<?php

namespace ChurchCRM\Plugins\HelloWorld;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Hello World — minimal community plugin example.
 *
 * This class is shipped as a template under examples/ so it is not
 * loaded at runtime. Use scripts/create-plugin.php to scaffold a new
 * plugin based on this example into src/plugins/community/{id}/.
 *
 * What it demonstrates:
 *   - Extending AbstractPlugin (never touch PluginInterface directly).
 *   - Reading plugin-scoped config via $this->getConfigValue(). The
 *     base class makes sure you can only read plugin.hello-world.*
 *     keys; anything else throws.
 *   - Subscribing to a core hook (PERSON_CREATED) in boot(). The
 *     plugin system only calls boot() on active plugins so there is
 *     no need for an isActive() guard.
 *   - Logging through LoggerUtils::getAppLogger() — this is the only
 *     sanctioned way to write to logs from a plugin.
 *   - Using plugin-local gettext via dgettext('hello-world', ...).
 *     Never call plain gettext() or _() from a plugin — those go to
 *     the core `messages` domain and your translations will not
 *     resolve.
 */
class HelloWorldPlugin extends AbstractPlugin
{
    public function getId(): string
    {
        return 'hello-world';
    }

    public function getName(): string
    {
        return 'Hello World';
    }

    public function getDescription(): string
    {
        return 'Minimal community plugin example.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Called once per request after the plugin is loaded and activated.
     * Register hooks, warm caches, etc. here. Do NOT do slow work in
     * the constructor — boot() is the right place.
     */
    public function boot(): void
    {
        HookManager::addAction(Hooks::PERSON_CREATED, [$this, 'onPersonCreated']);
    }

    /**
     * Required: returns whether the plugin has everything it needs to
     * run. The admin UI shows a "Needs Configuration" badge when this
     * returns false.
     */
    public function isConfigured(): bool
    {
        // This plugin is usable even with an empty greeting.
        return true;
    }

    /**
     * Example hook handler. Note the use of dgettext() — never plain
     * gettext(). This string would live in
     * locale/textdomain/{locale}/LC_MESSAGES/hello-world.mo.
     */
    public function onPersonCreated($person): void
    {
        $greeting = $this->getConfigValue('greeting') ?: dgettext('hello-world', 'Welcome!');

        LoggerUtils::getAppLogger()->info('HelloWorld plugin saw a new person', [
            'greeting' => $greeting,
            // Only log non-PII fields; PII in logs is a compliance issue.
            'personId' => method_exists($person, 'getId') ? $person->getId() : null,
        ]);
    }
}
