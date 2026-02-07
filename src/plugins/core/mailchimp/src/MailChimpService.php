<?php

namespace ChurchCRM\Plugins\MailChimp;

use ChurchCRM\Utils\LoggerUtils;
use DrewM\MailChimp\MailChimp;

/**
 * MailChimp Service - Provides MailChimp API functionality.
 *
 * This service is used internally by the MailChimpPlugin.
 * External code should access MailChimp functionality through the plugin.
 */
class MailChimpService
{
    private ?MailChimp $client = null;
    private bool $connectionVerified = false;
    private ?string $apiKey = null;
    private ?string $lastError = null;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
        if (!empty($this->apiKey)) {
            try {
                $this->client = new MailChimp($this->apiKey);
            } catch (\Throwable $e) {
                // Invalid API key format - client stays null
                $this->lastError = $e->getMessage();
                LoggerUtils::getAppLogger()->warning(
                    'MailChimp API key format invalid',
                    ['error' => $e->getMessage()]
                );
            }
        }
    }

    /**
     * Get the last error message, if any.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Check if MailChimp API key is configured.
     */
    public function hasApiKey(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Check if MailChimp connection is active and working.
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
            LoggerUtils::getAppLogger()->warning('MailChimp connection check failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Get MailChimp account information.
     *
     * @return array Account info including name, total_subscribers, last_login, etc.
     */
    public function getAccountInfo(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        try {
            $info = $this->client->get('');
            return [
                'account_name' => $info['account_name'] ?? '',
                'email' => $info['email'] ?? '',
                'total_subscribers' => $info['total_subscribers'] ?? 0,
                'industry_stats' => $info['industry_stats'] ?? [],
                'last_login' => $info['last_login'] ?? null,
            ];
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Failed to get MailChimp account info: ' . $e->getMessage());
            return [];
        }
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
     *
     * @param string|null $email The email address to check
     * @return array List statuses for the email
     * @throws \Exception If email is empty or MailChimp is not active
     */
    public function isEmailInMailChimp(?string $email): array
    {
        if (empty($email)) {
            throw new \Exception(gettext('No email passed in'));
        }

        if (!$this->isActive()) {
            throw new \Exception(gettext('Mailchimp is not active'));
        }

        $lists = $this->getListsFromCache();
        $listsStatus = [];
        foreach ($lists as $list) {
            $data = $this->client->get('lists/' . $list['id'] . '/members/' . md5(strtolower($email)));
            LoggerUtils::getAppLogger()->debug($email . ' is ' . ($data['status'] ?? 'unknown') . ' to ' . $list['name']);
            $listsStatus[] = [
                'name' => $list['name'],
                'status' => $data['status'] ?? 'not_found',
                'stats' => $data['stats'] ?? null,
            ];
        }

        return $listsStatus;
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
            $this->client->post("lists/$listId/members", [
                'email_address' => $person->getEmail(),
                'status' => 'subscribed',
                'merge_fields' => [
                    'FNAME' => $person->getFirstName(),
                    'LNAME' => $person->getLastName(),
                ],
            ]);

            if ($this->client->success()) {
                $this->invalidateListCache();
                LoggerUtils::getAppLogger()->info("Subscribed {$person->getEmail()} to list $listId");
                return true;
            }

            LoggerUtils::getAppLogger()->warning("Failed to subscribe {$person->getEmail()}: " . $this->client->getLastError());
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error("Exception subscribing {$person->getEmail()}: " . $e->getMessage());
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
                LoggerUtils::getAppLogger()->info("Unsubscribed $email from list $listId");
                return true;
            }
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error("Exception unsubscribing $email: " . $e->getMessage());
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
    public function updateSubscriberEmail(string $oldEmail, string $newEmail, $person): void
    {
        $lists = $this->getLists();
        foreach ($lists as $list) {
            try {
                // Check if subscribed to this list
                $member = $this->client->get("lists/{$list['id']}/members/" . md5(strtolower($oldEmail)));
                if (($member['status'] ?? '') === 'subscribed') {
                    // Re-subscribe with new email
                    $this->unsubscribeFromList($list['id'], $oldEmail);
                    $this->subscribeToList($list['id'], $person);
                }
            } catch (\Throwable $e) {
                // Member not in this list, skip
            }
        }
    }

    /**
     * Get the raw MailChimp client for advanced operations.
     */
    public function getClient(): ?MailChimp
    {
        return $this->client;
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
}
