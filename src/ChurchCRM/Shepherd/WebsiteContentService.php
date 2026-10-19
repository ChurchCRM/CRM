<?php

namespace ChurchCRM\Shepherd;

final class WebsiteContentService
{
    private const PAGE_KEYS = [
        'home',
        'visit',
        'services',
        'beliefs',
        'pastors',
        'sermons',
        'events',
        'faqs',
        'ask-a-question',
        'history',
        'for-skeptics',
        'an-appeal',
        'worship-wars',
        'rp-history',
        'links',
        'contact',
        'privacy',
    ];
    private const MAX_ENTRIES = 5000;
    private const MAX_KEY_LENGTH = 512;
    private const MAX_TEXT_LENGTH = 20000;
    private const MAX_CONTENT_BYTES = 2_000_000;

    public function __construct(private readonly WebsiteContentRepository $repository = new WebsiteContentRepository())
    {
    }

    public function getDocument(string $pageKey): array
    {
        $pageKey = self::normalizePageKey($pageKey);
        $row = $this->repository->find($pageKey);

        if ($row === null) {
            $content = [];
            return [
                'page' => $pageKey,
                'content' => (object) $content,
                'revision' => self::revision($content),
                'updatedAt' => null,
            ];
        }

        try {
            $decoded = json_decode((string) $row['content_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Stored website content is not valid JSON.', 0, $exception);
        }

        return self::documentFromContent($pageKey, self::normalizeContent($decoded), (string) $row['revision'], $row['updated_at']);
    }

    public function updateDocument(
        string $pageKey,
        mixed $content,
        string $expectedRevision,
        int $userId
    ): array {
        $pageKey = self::normalizePageKey($pageKey);
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedRevision)) {
            throw new \InvalidArgumentException('A valid content revision is required.');
        }
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid administrator is required.');
        }

        $normalized = self::normalizeContent($content);
        $contentJson = self::encodeContent($normalized);
        $newRevision = self::revision($normalized);
        $emptyRevision = self::revision([]);

        $savedRow = $this->repository->replaceIfRevisionMatches(
            $pageKey,
            $contentJson,
            $newRevision,
            $expectedRevision,
            $emptyRevision,
            $userId
        );

        if ($savedRow === null) {
            return ['conflict' => true, 'document' => $this->getDocument($pageKey)];
        }

        return [
            'conflict' => false,
            'document' => self::documentFromContent(
                $pageKey,
                $normalized,
                (string) $savedRow['revision'],
                $savedRow['updated_at']
            ),
        ];
    }

    public static function normalizePageKey(string $pageKey): string
    {
        $pageKey = strtolower(trim($pageKey));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $pageKey)
            || !in_array($pageKey, self::PAGE_KEYS, true)) {
            throw new \InvalidArgumentException('The website page key is invalid.');
        }

        return $pageKey;
    }

    public static function normalizeContent(mixed $content): array
    {
        if (!is_array($content) || ($content !== [] && array_is_list($content))) {
            throw new \InvalidArgumentException('Website content must be a JSON object.');
        }
        if (count($content) > self::MAX_ENTRIES) {
            throw new \InvalidArgumentException('This page contains too many editable text values.');
        }

        $normalized = [];
        foreach ($content as $key => $entry) {
            if (!is_string($key)
                || strlen($key) > self::MAX_KEY_LENGTH
                || !preg_match('/^[a-z0-9][a-z0-9:._\/-]*$/', $key)) {
                throw new \InvalidArgumentException('An editable text key is invalid.');
            }
            if (!is_array($entry)
                || array_diff(array_keys($entry), ['base', 'value']) !== []
                || count($entry) !== 2
                || !array_key_exists('base', $entry)
                || !array_key_exists('value', $entry)
                || !is_string($entry['base'])
                || !is_string($entry['value'])) {
                throw new \InvalidArgumentException('Each editable text value must include plain-text base and value strings.');
            }

            $base = $entry['base'];
            $value = $entry['value'];
            foreach ([$base, $value] as $text) {
                if (!mb_check_encoding($text, 'UTF-8')
                    || mb_strlen($text, 'UTF-8') > self::MAX_TEXT_LENGTH
                    || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text)) {
                    throw new \InvalidArgumentException('Editable website text contains unsupported characters or is too long.');
                }
            }

            if ($base !== $value) {
                $normalized[$key] = ['base' => $base, 'value' => $value];
            }
        }

        ksort($normalized, SORT_STRING);
        if (strlen(self::encodeContent($normalized)) > self::MAX_CONTENT_BYTES) {
            throw new \InvalidArgumentException('The editable content for this page is too large.');
        }
        return $normalized;
    }

    public static function revision(array $content): string
    {
        return hash('sha256', self::encodeContent($content));
    }

    private static function encodeContent(array $content): string
    {
        ksort($content, SORT_STRING);
        return json_encode(
            (object) $content,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    private static function documentFromContent(
        string $pageKey,
        array $content,
        string $storedRevision,
        mixed $updatedAt
    ): array {
        $computedRevision = self::revision($content);
        if (!preg_match('/^[a-f0-9]{64}$/', $storedRevision) || !hash_equals($computedRevision, $storedRevision)) {
            throw new \RuntimeException('Stored website content revision does not match its content.');
        }

        return [
            'page' => $pageKey,
            'content' => (object) $content,
            'revision' => $storedRevision,
            'updatedAt' => $updatedAt === null ? null : (string) $updatedAt,
        ];
    }
}
