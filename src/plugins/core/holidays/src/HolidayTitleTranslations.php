<?php

namespace ChurchCRM\Plugins\Holidays;

use ChurchCRM\Utils\LoggerUtils;
use Yasumi\TranslationsInterface;

/**
 * Holiday titles the holidays plugin translates itself, for holidays that
 * Yasumi's shared translations do not cover (mainly the plugin's own
 * providers in src/Provider/).
 *
 * Files live in the plugin at `locale/titles/{locale}.json`, one per Yasumi
 * locale or language ("de.json", "pt_BR.json"), as a flat map from the
 * holiday's Yasumi key to its title:
 *
 *     { "madarakaDay": "Madaraka-Tag" }
 *
 * The key is the first argument a provider passes to `new Holiday(...)`.
 * Only the files for the active locale and its parents are read. They are
 * server-side only, so they never reach the browser's client config.
 */
final class HolidayTitleTranslations implements TranslationsInterface
{
    private const MAX_FILE_BYTES = 512 * 1024;

    /** @var array<string, array<string, string>> locale => [holiday key => title] */
    private array $titles = [];

    public function __construct(string $directory, string $locale)
    {
        foreach (HolidayLocale::chain($locale) as $candidate) {
            $map = self::readFile($directory, $candidate);
            if ($map !== []) {
                $this->titles[$candidate] = $map;
            }
        }
    }

    public static function forPlugin(string $locale): self
    {
        return new self(dirname(__DIR__) . '/locale/titles', $locale);
    }

    public function getTranslation(string $key, string $locale): ?string
    {
        return $this->titles[$locale][$key] ?? null;
    }

    public function getTranslations(string $key): array
    {
        $translations = [];
        foreach ($this->titles as $locale => $map) {
            if (isset($map[$key])) {
                $translations[$locale] = $map[$key];
            }
        }

        return $translations;
    }

    /**
     * @return array<string, string>
     */
    private static function readFile(string $directory, string $locale): array
    {
        if (!preg_match('/^[A-Za-z]{2,3}(_[A-Za-z0-9]{2,8})*$/', $locale)) {
            return [];
        }

        $path = rtrim($directory, '/') . '/' . $locale . '.json';
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $size = filesize($path);
        if ($size === false || $size > self::MAX_FILE_BYTES) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true, 8);
        if (!is_array($decoded)) {
            LoggerUtils::getAppLogger()?->warning('Holiday title translations are not a JSON object', ['path' => $path]);

            return [];
        }

        return array_filter(
            $decoded,
            static fn ($title, $key): bool => is_string($key) && is_string($title) && $title !== '',
            ARRAY_FILTER_USE_BOTH
        );
    }
}
