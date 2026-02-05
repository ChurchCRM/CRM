<?php

namespace ChurchCRM\Plugins\MailChimp;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Utils\LoggerUtils;
use DrewM\MailChimp\MailChimp;

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
    private ?MailChimp $client = null;
    private ?string $apiKey = null;
    private bool $connectionVerified = false;

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

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        // Initialize MailChimp client
        $this->apiKey = $this->getConfigValue('apiKey');
        if (!empty($this->apiKey)) {
            $this->client = new MailChimp($this->apiKey);
        }

        // Register hooks for person/family/group changes
        $this->registerHooks();

        $this->log('MailChimp plugin booted');
    }

    public function activate(): void
    {
        // Nothing special needed on activation
        $this->log('MailChimp plugin activated');
    }

    public function deactivate(): void
    {
        // Clear cached data
        unset($_SESSION['MailChimpLists']);
        $this->log('MailChimp plugin deactivated');
    }

    public function uninstall(): void
    {
        // Clear all MailChimp related session data
        unset($_SESSION['MailChimpLists']);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function registerRoutes($routeCollector): void
    {
        // API routes for MailChimp functionality
        $routeCollector->group('/mailchimp', function ($group) {
            $group->get('/lists', [$this, 'handleGetLists']);
            $group->get('/lists/{listId}', [$this, 'handleGetList']);
            $group->get('/person/{personId}/status', [$this, 'handleGetPersonStatus']);
            $group->post('/person/{personId}/subscribe', [$this, 'handleSubscribe']);
            $group->post('/person/{personId}/unsubscribe', [$this, 'handleUnsubscribe']);
        });
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
                'url' => '/v2/plugins/mailchimp/dashboard',
                'icon' => 'fa-envelope',
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
                'help' => gettext('Get your API key from MailChimp Account Settings > API Keys'),
            ],
        ];
    }

    // =========================================================================
    // Hook Registration
    // =========================================================================

    private function registerHooks(): void
    {
        // Listen for person email changes
        HookManager::addAction(Hooks::PERSON_UPDATED, [$this, 'onPersonUpdated'], 10, 2);
        HookManager::addAction(Hooks::PERSON_DELETED, [$this, 'onPersonDeleted'], 10, 2);

        // Listen for group membership changes (for list sync)
        HookManager::addAction(Hooks::GROUP_MEMBER_ADDED, [$this, 'onGroupMemberAdded'], 10, 3);
        HookManager::addAction(Hooks::GROUP_MEMBER_REMOVED, [$this, 'onGroupMemberRemoved'], 10, 2);
    }

    // =========================================================================
    // Hook Handlers
    // =========================================================================

    /**
     * Handle person email update - sync to MailChimp if needed.
     */
    public function onPersonUpdated($person, array $oldData): void
    {
        if (!$this->isConfigured() || !$this->isActive()) {
            return;
        }

        $newEmail = $person->getEmail();
        $oldEmail = $oldData['email'] ?? null;

        // If email changed, update in MailChimp
        if ($oldEmail !== null && $oldEmail !== $newEmail) {
            $this->updateSubscriberEmail($oldEmail, $newEmail, $person);
        }
    }

    /**
     * Handle person deletion - remove from all MailChimp lists.
     */
    public function onPersonDeleted(int $personId, array $personData): void
    {
        if (!$this->isConfigured() || !$this->isActive()) {
            return;
        }

        $email = $personData['email'] ?? null;
        if (!empty($email)) {
            $this->unsubscribeFromAllLists($email);
        }
    }

    /**
     * Handle group member addition - potentially subscribe to associated list.
     */
    public function onGroupMemberAdded($membership, $group, $person): void
    {
        if (!$this->isConfigured() || !$this->isActive()) {
            return;
        }

        // Check if group has associated MailChimp list
        $listId = $this->getGroupMailChimpListId($group->getId());
        if ($listId && !empty($person->getEmail())) {
            $this->subscribeToList($listId, $person);
        }
    }

    /**
     * Handle group member removal - potentially unsubscribe from associated list.
     */
    public function onGroupMemberRemoved(int $personId, $group): void
    {
        if (!$this->isConfigured() || !$this->isActive()) {
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
            $this->unsubscribeFromList($listId, $person->getEmail());
        }
    }

    // =========================================================================
    // MailChimp API Methods
    // =========================================================================

    /**
     * Check if MailChimp connection is active.
     */
    public function isActive(): bool
    {
        if ($this->connectionVerified) {
            return true;
        }

        if ($this->client === null) {
            return false;
        }

        try {
            $rootAPI = $this->client->get('');
            if (isset($rootAPI['total_subscribers']) && $rootAPI['total_subscribers'] >= 0) {
                $this->connectionVerified = true;

                return true;
            }
        } catch (\Throwable $e) {
            $this->log('MailChimp connection check failed: ' . $e->getMessage(), 'error');
        }

        return false;
    }

    /**
     * Get all MailChimp lists (with caching).
     */
    public function getLists(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        return $this->getListsFromCache();
    }

    /**
     * Get a specific list by ID.
     */
    public function getList(string $listId): ?array
    {
        $lists = $this->getLists();
        foreach ($lists as $list) {
            if ($list['id'] === $listId) {
                return $list;
            }
        }

        return null;
    }

    /**
     * Check email status in all MailChimp lists.
     */
    public function getEmailStatus(string $email): array
    {
        if (!$this->isActive()) {
            return [];
        }

        $lists = $this->getListsFromCache();
        $statuses = [];

        foreach ($lists as $list) {
            $data = $this->client->get("lists/{$list['id']}/members/" . md5(strtolower($email)));
            $statuses[] = [
                'list_id' => $list['id'],
                'list_name' => $list['name'],
                'status' => $data['status'] ?? 'not_found',
                'subscribed_at' => $data['timestamp_signup'] ?? null,
            ];
        }

        return $statuses;
    }

    /**
     * Subscribe a person to a list.
     */
    public function subscribeToList(string $listId, $person): bool
    {
        if (!$this->isActive() || empty($person->getEmail())) {
            return false;
        }

        try {
            $result = $this->client->post("lists/$listId/members", [
                'email_address' => $person->getEmail(),
                'status' => 'subscribed',
                'merge_fields' => [
                    'FNAME' => $person->getFirstName(),
                    'LNAME' => $person->getLastName(),
                ],
            ]);

            if ($this->client->success()) {
                $this->invalidateListCache();
                $this->log("Subscribed {$person->getEmail()} to list $listId");

                return true;
            }

            $this->log("Failed to subscribe {$person->getEmail()}: " . $this->client->getLastError(), 'warning');
        } catch (\Throwable $e) {
            $this->log("Exception subscribing {$person->getEmail()}: " . $e->getMessage(), 'error');
        }

        return false;
    }

    /**
     * Unsubscribe an email from a list.
     */
    public function unsubscribeFromList(string $listId, string $email): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        try {
            $this->client->patch("lists/$listId/members/" . md5(strtolower($email)), [
                'status' => 'unsubscribed',
            ]);

            if ($this->client->success()) {
                $this->invalidateListCache();
                $this->log("Unsubscribed $email from list $listId");

                return true;
            }
        } catch (\Throwable $e) {
            $this->log("Exception unsubscribing $email: " . $e->getMessage(), 'error');
        }

        return false;
    }

    /**
     * Unsubscribe from all lists.
     */
    public function unsubscribeFromAllLists(string $email): void
    {
        $lists = $this->getLists();
        foreach ($lists as $list) {
            $this->unsubscribeFromList($list['id'], $email);
        }
    }

    /**
     * Update subscriber email in all lists.
     */
    private function updateSubscriberEmail(string $oldEmail, string $newEmail, $person): void
    {
        $lists = $this->getLists();
        foreach ($lists as $list) {
            try {
                // Check if subscribed to this list
                $member = $this->client->get("lists/{$list['id']}/members/" . md5(strtolower($oldEmail)));
                if ($member['status'] === 'subscribed') {
                    // Re-subscribe with new email
                    $this->unsubscribeFromList($list['id'], $oldEmail);
                    $this->subscribeToList($list['id'], $person);
                }
            } catch (\Throwable $e) {
                // Member not in this list, skip
            }
        }
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    private function getListsFromCache(): array
    {
        if (!isset($_SESSION['MailChimpLists'])) {
            LoggerUtils::getAppLogger()->debug('Updating MailChimp List Cache');

            $lists = $this->client->get('lists')['lists'] ?? [];

            foreach ($lists as &$list) {
                $list['members'] = [];
                $listMembers = $this->client->get(
                    "lists/{$list['id']}/members",
                    [
                        'count' => $list['stats']['member_count'] ?? 100,
                        'fields' => 'members.id,members.email_address,members.status,members.merge_fields',
                        'status' => 'subscribed',
                    ]
                );

                foreach ($listMembers['members'] ?? [] as $member) {
                    $list['members'][] = [
                        'email' => strtolower($member['email_address']),
                        'first' => $member['merge_fields']['FNAME'] ?? '',
                        'last' => $member['merge_fields']['LNAME'] ?? '',
                        'status' => $member['status'],
                    ];
                }
            }

            $_SESSION['MailChimpLists'] = $lists;
        }

        return $_SESSION['MailChimpLists'] ?? [];
    }

    private function invalidateListCache(): void
    {
        unset($_SESSION['MailChimpLists']);
    }

    /**
     * Get the MailChimp list ID associated with a group.
     *
     * This could be stored in group custom fields or a mapping table.
     * For now, returns null - needs configuration UI.
     */
    private function getGroupMailChimpListId(int $groupId): ?string
    {
        // TODO: Implement group-to-list mapping
        // Could be stored in group_grp custom fields or a plugin-specific table
        return null;
    }

    // =========================================================================
    // API Route Handlers
    // =========================================================================

    public function handleGetLists($request, $response): mixed
    {
        $lists = $this->getLists();

        return $response->withJson([
            'success' => true,
            'data' => $lists,
        ]);
    }

    public function handleGetList($request, $response, array $args): mixed
    {
        $list = $this->getList($args['listId']);

        if ($list === null) {
            return $response->withJson([
                'success' => false,
                'message' => 'List not found',
            ], 404);
        }

        return $response->withJson([
            'success' => true,
            'data' => $list,
        ]);
    }

    public function handleGetPersonStatus($request, $response, array $args): mixed
    {
        $person = \ChurchCRM\model\ChurchCRM\PersonQuery::create()->findPk((int) $args['personId']);

        if ($person === null) {
            return $response->withJson([
                'success' => false,
                'message' => 'Person not found',
            ], 404);
        }

        $status = $this->getEmailStatus($person->getEmail());

        return $response->withJson([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function handleSubscribe($request, $response, array $args): mixed
    {
        $person = \ChurchCRM\model\ChurchCRM\PersonQuery::create()->findPk((int) $args['personId']);
        $body = $request->getParsedBody();
        $listId = $body['listId'] ?? null;

        if ($person === null || $listId === null) {
            return $response->withJson([
                'success' => false,
                'message' => 'Person or List ID required',
            ], 400);
        }

        $success = $this->subscribeToList($listId, $person);

        return $response->withJson([
            'success' => $success,
            'message' => $success ? 'Subscribed' : 'Failed to subscribe',
        ]);
    }

    public function handleUnsubscribe($request, $response, array $args): mixed
    {
        $person = \ChurchCRM\model\ChurchCRM\PersonQuery::create()->findPk((int) $args['personId']);
        $body = $request->getParsedBody();
        $listId = $body['listId'] ?? null;

        if ($person === null || $listId === null) {
            return $response->withJson([
                'success' => false,
                'message' => 'Person or List ID required',
            ], 400);
        }

        $success = $this->unsubscribeFromList($listId, $person->getEmail());

        return $response->withJson([
            'success' => $success,
            'message' => $success ? 'Unsubscribed' : 'Failed to unsubscribe',
        ]);
    }
}
