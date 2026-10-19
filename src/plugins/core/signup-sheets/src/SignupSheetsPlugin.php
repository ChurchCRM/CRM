<?php

namespace ChurchCRM\Plugins\SignupSheets;

use ChurchCRM\Plugin\AbstractPlugin;
use RuntimeException;

/**
 * Signup Sheets Plugin.
 *
 * Volunteer and potluck signup sheets, in the spirit of SignUpGenius:
 * - A sheet describes an occasion, optionally attached to a ChurchCRM event
 * - Slots describe what is needed — dishes to bring, roles to serve, shifts to cover
 * - Members claim slots inside the CRM, or anyone can claim them via a share link
 */
class SignupSheetsPlugin extends AbstractPlugin
{
    /**
     * How much looser the total-attempt limit is than the signup limit.
     *
     * @see getPublicAttemptLimit()
     */
    private const ATTEMPT_LIMIT_MULTIPLIER = 5;

    private static ?SignupSheetsPlugin $instance = null;

    private ?SignupSheetService $service = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?SignupSheetsPlugin
    {
        return self::$instance;
    }

    public function getId(): string
    {
        return 'signup-sheets';
    }

    public function getName(): string
    {
        return gettext('Signup Sheets');
    }

    public function getDescription(): string
    {
        return gettext('Volunteer and potluck signup sheets people can fill from inside the CRM or a public share link.');
    }

    public function getType(): string
    {
        return 'core';
    }

    public function getMinimumCRMVersion(): string
    {
        return '7.6.2';
    }

    public function boot(): void
    {
        // No hooks: the plugin owns its own tables and routes and does not
        // observe core lifecycle events. The service is created lazily.
    }

    /**
     * Lazily-created service, shared by the authenticated and public routes.
     */
    public function getService(): SignupSheetService
    {
        return $this->service ??= new SignupSheetService();
    }

    /**
     * Nothing must be configured before the plugin is useful, but it cannot
     * run without the four tables its schema file creates on enable.
     */
    public function isConfigured(): bool
    {
        return $this->getConfigurationError() === null;
    }

    /**
     * Explains a half-provisioned install — tables dropped by hand, or a
     * restore from a dump taken before the plugin was enabled.
     *
     * Unlike the enable gate, this runs on a page render, so a database that
     * cannot answer becomes a message rather than an exception. It still
     * reports the plugin as unconfigured: unverified is not the same as fine.
     */
    public function getConfigurationError(): ?string
    {
        // Deliberately not memoised. A cached answer outlives the state it
        // describes, so this method and getMissingTables() could contradict
        // each other on the same object. The cost of asking again is one
        // indexed information_schema lookup on an admin page.
        try {
            $missingTables = $this->getMissingTables();
        } catch (RuntimeException) {
            return gettext('Signup Sheets could not verify its database tables. Check the database connection, then see the application log.');
        }

        if ($missingTables === []) {
            return null;
        }

        return sprintf(
            gettext('Signup Sheets is missing its database tables (%s). Disable and re-enable the plugin to create them.'),
            implode(', ', $missingTables)
        );
    }

    /**
     * May sheets be published at a public share link?
     */
    public function isPublicSharingAllowed(): bool
    {
        $value = $this->getConfigValue('allowPublicSheets');

        // Default to enabled when the admin has never touched the setting.
        return $value === '' ? true : $this->getBooleanConfigValue('allowPublicSheets');
    }

    /**
     * Maximum accepted signups from one IP address per hour.
     */
    public function getPublicRateLimit(): int
    {
        $configured = (int) $this->getConfigValue('publicRateLimit');

        return $configured > 0 ? $configured : 20;
    }

    /**
     * Maximum submissions of any kind from one IP address per hour.
     *
     * Derived rather than configured, to keep the admin surface to one number.
     * It has to be looser than the signup limit: rejected submissions are
     * counted here so malformed traffic is still throttled, and several people
     * can sit behind one shared address, so a few fumbled forms must not
     * consume everyone's signup allowance.
     */
    public function getPublicAttemptLimit(): int
    {
        return $this->getPublicRateLimit() * self::ATTEMPT_LIMIT_MULTIPLIER;
    }

    public function getContactEmail(): string
    {
        return $this->getConfigValue('contactEmail');
    }

    public function getMenuItems(): array
    {
        return [
            [
                'parent' => 'events',
                'label' => gettext('Signup Sheets'),
                'url' => 'plugins/signup-sheets',
                'icon' => 'fa-clipboard-list',
            ],
        ];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'allowPublicSheets',
                'label' => gettext('Allow public share links'),
                'type' => 'boolean',
                'required' => false,
                'help' => gettext('When enabled, a sheet can be published at a secret link that anyone can use without a CRM login.'),
            ],
            [
                'key' => 'publicRateLimit',
                'label' => gettext('Public signups per hour per visitor'),
                'type' => 'number',
                'required' => false,
                'help' => gettext('Maximum signups accepted from one IP address per hour on public sheets. Rejected submissions are throttled separately, at five times this number.'),
            ],
            [
                'key' => 'contactEmail',
                'label' => gettext('Sheet contact email'),
                'type' => 'text',
                'required' => false,
                'help' => gettext('Shown on public sheets so volunteers know who to ask about the event.'),
            ],
        ];
    }

    public function getClientConfig(): array
    {
        return [
            'allowPublicSheets' => $this->isPublicSharingAllowed(),
        ];
    }
}
