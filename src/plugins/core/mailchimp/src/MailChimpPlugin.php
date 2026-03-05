<?php

namespace ChurchCRM\Plugins\MailChimp;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

/**
 * MailChimp Integration Plugin.
 *
 * Provides integration with MailChimp for email marketing:
 * - Sync contacts to MailChimp lists
 * - Subscribe/unsubscribe based on group membership
 * - View MailChimp list status for contacts
 */
class MailChimpPlugin extends AbstractPlugin
{
    private static ?MailChimpPlugin $instance = null;
    private ?MailChimpService $service = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    /**
     * Get the singleton instance of the plugin.
     */
    public static function getInstance(): ?MailChimpPlugin
    {
        return self::$instance;
    }

    public function getId(): string
    {
        return 'mailchimp';
    }

    public function getName(): string
    {
        return 'MailChimp Integration';
    }

    public function getDescription(): string
    {
        return 'Sync ChurchCRM contacts with MailChimp mailing lists.';
    }

    public function boot(): void
    {
        // Initialize the service with the configured API key
        $apiKey = $this->getConfigValue('apiKey');
        $this->service = new MailChimpService($apiKey);

        // Register hooks for person/family/group changes
        $this->registerHooks();

        $this->log('MailChimp plugin booted', 'debug');
    }

    public function activate(): void
    {
        $this->log('MailChimp plugin activated', 'debug');
    }

    public function deactivate(): void
    {
        // Clear cached data
        unset($_SESSION['MailChimpLists']);
        $this->log('MailChimp plugin deactivated', 'debug');
    }

    public function uninstall(): void
    {
        // Clear all MailChimp related session data
        unset($_SESSION['MailChimpLists']);
    }

    public function isConfigured(): bool
    {
        $serviceExists = $this->service !== null;
        $hasApiKey = $serviceExists && $this->service->hasApiKey();
        $hasNoError = $serviceExists && $this->service->getLastError() === null;
        return $serviceExists && $hasApiKey && $hasNoError;
    }

    public function getConfigurationError(): ?string
    {
        if ($this->service === null) {
            return null;
        }
        return $this->service->getLastError();
    }

    /**
     * Check if MailChimp is active (configured and connected).
     */
    public function isActive(): bool
    {
        return $this->service !== null && $this->service->isActive();
    }

    /**
     * Get the internal MailChimp service.
     */
    public function getService(): ?MailChimpService
    {
        return $this->service;
    }

    /**
     * Convenience method to get account info via the service.
     */
    public function getAccountInfo(): array
    {
        return $this->service?->getAccountInfo() ?? [];
    }

    /**
     * Convenience method to get lists via the service.
     */
    public function getLists(): array
    {
        return $this->service?->getLists() ?? [];
    }

    /**
     * Convenience method to get a specific list.
     */
    public function getList(string $listId): ?array
    {
        return $this->service?->getList($listId);
    }

    /**
     * Convenience method to check email status.
     */
    public function isEmailInMailChimp(?string $email): array
    {
        if ($this->service === null) {
            throw new \Exception(gettext('MailChimp is not configured'));
        }
        return $this->service->isEmailInMailChimp($email);
    }

    public function registerRoutes($routeCollector): void
    {
        // Plugin API routes are registered through the plugin system
        // Dashboard view is also provided through plugin views
    }

    public function getMenuItems(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        return [
            [
                'parent' => 'email',
                'label' => gettext('MailChimp Dashboard'),
                'url' => 'plugins/mailchimp/dashboard',
                'icon' => 'fa-brands fa-mailchimp',
            ],
        ];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'apiKey',
                'label' => gettext('MailChimp API Key'),
                'type' => 'password',
                'required' => true,
                'help' => gettext('Get your API key from MailChimp: Account Settings > Extras > API Keys'),
            ],
            [
                'key' => 'defaultListId',
                'label' => gettext('Default List ID'),
                'type' => 'text',
                'required' => false,
                'help' => gettext('Find in MailChimp: Audience > Settings > Audience name and defaults > Audience ID'),
            ],
        ];
    }

    /**
     * Test MailChimp connection using the provided API key.
     *
     * Falls back to the saved API key if the settings array omits it
     * (e.g. password field not re-entered in the UI).
     *
     * {@inheritdoc}
     */
    public function testWithSettings(array $settings): array
    {
        $apiKey = $settings['apiKey'] ?? '';
        if (empty($apiKey)) {
            $apiKey = $this->getConfigValue('apiKey');
        }

        if (empty($apiKey)) {
            return ['success' => false, 'message' => gettext('API Key is required.')];
        }

        try {
            $service = new MailChimpService($apiKey);

            if ($service->getLastError() !== null) {
                return [
                    'success' => false,
                    'message' => gettext('Invalid API key format. Check your MailChimp API key.'),
                ];
            }

            $accountInfo = $service->getAccountInfo();

            if (empty($accountInfo)) {
                return [
                    'success' => false,
                    'message' => gettext('Could not connect to MailChimp. Please check your API key.'),
                ];
            }

            return [
                'success' => true,
                'message' => sprintf(
                    gettext('Connected to MailChimp! Account: %s (%d subscribers)'),
                    $accountInfo['account_name'] ?? 'Unknown',
                    $accountInfo['total_subscribers'] ?? 0
                ),
                'details' => $accountInfo,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => gettext('MailChimp connection failed.'),
            ];
        }
    }

    // =========================================================================
    // Hook Registration
    // =========================================================================

    private function registerHooks(): void
    {
        // Listen for person email changes
        HookManager::addAction(Hooks::PERSON_UPDATED, [$this, 'onPersonUpdated'], 10);
        HookManager::addAction(Hooks::PERSON_DELETED, [$this, 'onPersonDeleted'], 10);

        // Listen for group membership changes (for list sync)
        HookManager::addAction(Hooks::GROUP_MEMBER_ADDED, [$this, 'onGroupMemberAdded'], 10);
        HookManager::addAction(Hooks::GROUP_MEMBER_REMOVED, [$this, 'onGroupMemberRemoved'], 10);
    }

    // =========================================================================
    // Hook Handlers
    // =========================================================================

    /**
     * Handle person email update - sync to MailChimp if needed.
     */
    public function onPersonUpdated($person, array $oldData): void
    {
        if (!$this->isActive()) {
            return;
        }

        $newEmail = $person->getEmail();
        $oldEmail = $oldData['email'] ?? null;

        // If email changed, update in MailChimp
        if ($oldEmail !== null && $oldEmail !== $newEmail) {
            $this->service->updateSubscriberEmail($oldEmail, $newEmail, $person);
        }
    }

    /**
     * Handle person deletion - remove from all MailChimp lists.
     */
    public function onPersonDeleted(int $personId, array $personData): void
    {
        if (!$this->isActive()) {
            return;
        }

        $email = $personData['email'] ?? null;
        if (!empty($email)) {
            $this->service->unsubscribeFromAllLists($email);
        }
    }

    /**
     * Handle group member addition - potentially subscribe to associated list.
     */
    public function onGroupMemberAdded($membership, $group, $person): void
    {
        if (!$this->isActive()) {
            return;
        }

        // Check if group has associated MailChimp list
        $listId = $this->getGroupMailChimpListId($group->getId());
        if ($listId && !empty($person->getEmail())) {
            $this->service->subscribeToList($listId, $person);
        }
    }

    /**
     * Handle group member removal - potentially unsubscribe from associated list.
     */
    public function onGroupMemberRemoved(int $personId, $group): void
    {
        if (!$this->isActive()) {
            return;
        }

        // Get person email from ID
        $person = \ChurchCRM\model\ChurchCRM\PersonQuery::create()->findPk($personId);
        if ($person === null || empty($person->getEmail())) {
            return;
        }

        // Check if group has associated MailChimp list
        $listId = $this->getGroupMailChimpListId($group->getId());
        if ($listId) {
            $this->service->unsubscribeFromList($listId, $person->getEmail());
        }
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    /**
     * Get the MailChimp list ID associated with a group.
     *
     * Group-to-list mapping is not yet implemented.
     * Future enhancement could store mapping in group custom fields or a plugin-specific table.
     *
     * @return null Always returns null until group-to-list mapping is implemented
     */
    private function getGroupMailChimpListId(int $groupId): ?string
    {
        return null;
    }
}
