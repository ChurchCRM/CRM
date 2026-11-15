<?php

namespace ChurchCRM\Service;

use ChurchCRM\Remote\CentralServices;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Curated community-feedback topics for the "We Need Your Help" page.
 *
 * The header badge and the page load this through GET /api/community-feedback.
 * The first call in a session fetches External/feedback.json; later calls
 * reuse that result for CACHE_SECONDS. Sign-in does not call this.
 * Installs never call the GitHub API. A failed fetch returns an empty list.
 */
class CommunityFeedbackService
{
    private const MAX_ITEMS = 20;

    private const CACHE_SECONDS = 300;

    private const DISCUSSION_URL_PATTERN = '/^https:\/\/github\.com\/ChurchCRM\/CRM\/discussions\/\d+$/';

    /**
     * @return array{count: int, items: array<int, array{id: string, title: string, summary: string, category: string, discussionUrl: string, expiresAt: ?string}>}
     */
    public static function getPayload(): array
    {
        $cached = $_SESSION['CommunityFeedbackCache'] ?? null;
        if (
            is_array($cached)
            && isset($cached['fetchedAt'], $cached['items'])
            && is_array($cached['items'])
            && (time() - (int) $cached['fetchedAt']) < self::CACHE_SECONDS
        ) {
            $items = self::validateItems($cached['items']);

            return ['count' => count($items), 'items' => $items];
        }

        $items = self::fetchRemoteItems();
        $_SESSION['CommunityFeedbackCache'] = [
            'fetchedAt' => time(),
            'items' => $items,
        ];

        return ['count' => count($items), 'items' => $items];
    }

    /**
     * @return array<int, array{id: string, title: string, summary: string, category: string, discussionUrl: string, expiresAt: ?string}>
     */
    private static function fetchRemoteItems(): array
    {
        try {
            $url = CentralServices::FEEDBACK_URL;
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $contents = @file_get_contents($url, false, $context);
            if ($contents === false) {
                LoggerUtils::getAppLogger()->warning('Failed to fetch remote community feedback', ['url' => $url]);

                return [];
            }
            $data = json_decode($contents, true);
            if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
                LoggerUtils::getAppLogger()->warning('Malformed remote community feedback', ['url' => $url]);

                return [];
            }

            return self::validateItems($data['items']);
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Error processing remote community feedback', ['error' => $e->getMessage()]);

            return [];
        }
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
