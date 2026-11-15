<?php

namespace ChurchCRM\Service;

use ChurchCRM\Remote\CentralServices;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Curated community-feedback topics for the "We Need Your Help" page.
 *
 * On login, AuthenticationManager calls fetchRemoteFeedback() ONCE, which
 * fetches the maintainer-curated JSON from CentralServices::FEEDBACK_URL
 * and stores the validated items in $_SESSION['CommunityFeedbackItems'].
 *
 * No external HTTP calls happen in the render path, and installs never
 * call the GitHub API — the list is curated in External/feedback.json,
 * the same pattern NotificationService uses for notifications.json.
 * Updating the ballot therefore needs no release.
 */
class CommunityFeedbackService
{
    private const MAX_ITEMS = 20;

    private const DISCUSSION_URL_PATTERN = '/^https:\/\/github\.com\/ChurchCRM\/CRM\/discussions\/\d+$/';

    /**
     * Fetch feedback topics from the remote GitHub-hosted JSON file.
     * Called ONCE on login by AuthenticationManager — never in the render path.
     * Results are stored in $_SESSION['CommunityFeedbackItems'].
     * Never throws: an offline install or a malformed file simply leaves
     * the page in its fallback state.
     */
    public static function fetchRemoteFeedback(): void
    {
        try {
            $url = CentralServices::FEEDBACK_URL;
            // Runs inside the sign-in request, so never let a slow remote host hold up the redirect.
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $contents = @file_get_contents($url, false, $context);
            if ($contents === false) {
                LoggerUtils::getAppLogger()->warning('Failed to fetch remote community feedback', ['url' => $url]);

                return;
            }
            $data = json_decode($contents, true);
            if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
                LoggerUtils::getAppLogger()->warning('Malformed remote community feedback', ['url' => $url]);

                return;
            }
            $_SESSION['CommunityFeedbackItems'] = self::validateItems($data['items']);
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Error processing remote community feedback', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Return the validated feedback topics cached in the session at login.
     *
     * @return array<int, array{id: string, title: string, summary: string, category: string, discussionUrl: string, expiresAt: ?string}>
     */
    public static function getItems(): array
    {
        if (!isset($_SESSION['CommunityFeedbackItems']) || !is_array($_SESSION['CommunityFeedbackItems'])) {
            return [];
        }

        return self::validateItems($_SESSION['CommunityFeedbackItems']);
    }

    /**
     * Keep only well-formed, unexpired topics: non-empty id/title/summary,
     * a ChurchCRM Discussion URL, and an expiresAt that is null or today
     * or later. Dedupes by id and caps the list at MAX_ITEMS.
     *
     * @param array<int, mixed> $items
     * @return array<int, array{id: string, title: string, summary: string, category: string, discussionUrl: string, expiresAt: ?string}>
     */
    private static function validateItems(array $items): array
    {
        $validated = [];
        $seenIds = [];
        $today = date('Y-m-d');

        foreach ($items as $item) {
            if (is_object($item)) {
                $item = get_object_vars($item);
            }
            if (!is_array($item)) {
                continue;
            }

            $id = isset($item['id']) && is_string($item['id']) ? trim($item['id']) : '';
            $title = isset($item['title']) && is_string($item['title']) ? trim($item['title']) : '';
            $summary = isset($item['summary']) && is_string($item['summary']) ? trim($item['summary']) : '';
            $discussionUrl = isset($item['discussionUrl']) && is_string($item['discussionUrl']) ? trim($item['discussionUrl']) : '';
            if ($id === '' || $title === '' || $summary === '' || $discussionUrl === '') {
                continue;
            }
            if (isset($seenIds[$id])) {
                continue;
            }
            if (preg_match(self::DISCUSSION_URL_PATTERN, $discussionUrl) !== 1) {
                continue;
            }

            $expiresAt = $item['expiresAt'] ?? null;
            if ($expiresAt !== null) {
                if (!is_string($expiresAt) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt) !== 1 || $expiresAt < $today) {
                    continue;
                }
            }

            $seenIds[$id] = true;
            $validated[] = [
                'id' => $id,
                'title' => $title,
                'summary' => $summary,
                'category' => isset($item['category']) && is_string($item['category']) ? trim($item['category']) : '',
                'discussionUrl' => $discussionUrl,
                'expiresAt' => $expiresAt,
            ];

            if (count($validated) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $validated;
    }
}
