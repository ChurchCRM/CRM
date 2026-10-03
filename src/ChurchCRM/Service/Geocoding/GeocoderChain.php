<?php

namespace ChurchCRM\Service\Geocoding;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;
use Generator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Asks the configured geocoding providers one by one. The order comes from the
 * `sGeocoderProviders` setting, a comma-separated list such as "Nominatim, Census"
 * (just "Nominatim" by default; Census is opt-in). Unknown names are logged and
 * ignored; an empty or entirely invalid list falls back to Nominatim.
 *
 * results() is lazy: a provider is only called when the caller asks for the next
 * result, so stopping at the first usable one never contacts the rest.
 */
class GeocoderChain
{
    public const CONFIG_KEY = 'sGeocoderProviders';

    /** No further provider is started after this many seconds, which keeps a family save under PHP's 30 s limit. */
    public const TIME_BUDGET_SECONDS = 18;

    /** Registry of providers that ship with ChurchCRM, keyed by lower-case name. */
    private const PROVIDERS = [
        'nominatim' => NominatimGeocoder::class,
        'census'    => CensusGeocoder::class,
    ];

    /** @var array<string, GeocoderProviderInterface[]> providers per ranking string, resolved once per request */
    private static array $resolved = [];

    /** @var GeocoderProviderInterface[] */
    private array $providers;

    private LoggerInterface $logger;

    /**
     * @param GeocoderProviderInterface[] $providers in order of preference
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
        $this->logger = LoggerUtils::getAppLogger() ?? new NullLogger();
    }

    /**
     * Build the chain from the `sGeocoderProviders` setting.
     */
    public static function fromConfig(): self
    {
        $ranking = (string) SystemConfig::getValue(self::CONFIG_KEY);

        return new self(self::$resolved[$ranking] ??= self::resolveProviders($ranking));
    }

    /**
     * The country used to decide which providers apply: the record's own, else the
     * default country, else the church country. Null when all of them are blank.
     */
    public static function resolveCountry(?string $recordCountry): ?string
    {
        foreach ([$recordCountry, SystemConfig::getValue('sDefaultCountry'), SystemConfig::getValue('sChurchCountry')] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Turn "Nominatim, Census" into provider instances, in order.
     *
     * @return GeocoderProviderInterface[]
     */
    public static function resolveProviders(string $ranking): array
    {
        $logger = LoggerUtils::getAppLogger() ?? new NullLogger();
        $providers = [];
        $seen = [];

        foreach (explode(',', $ranking) as $name) {
            $key = strtolower(trim($name));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            if (!isset(self::PROVIDERS[$key])) {
                $logger->warning('Geocoding: unknown provider "' . trim($name) . '" in ' . self::CONFIG_KEY . ' ignored');
                continue;
            }
            $seen[$key] = true;
            $class = self::PROVIDERS[$key];
            $providers[] = new $class();
        }

        if ($providers === []) {
            $logger->warning('Geocoding: ' . self::CONFIG_KEY . ' names no usable provider, falling back to Nominatim');
            $providers[] = new NominatimGeocoder();
        }

        return $providers;
    }

    /**
     * @return GeocoderProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * One usable result per provider that found the address, in provider order.
     * Providers that do not cover the country, fail, or find nothing are skipped.
     * $country is the record's own country; the country that decides which providers
     * apply is resolved from it (see resolveCountry()).
     *
     * @return Generator<int, GeocodeResult>
     */
    public function results(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): Generator
    {
        $resolvedCountry = self::resolveCountry($country);
        $startedAt = microtime(true);

        foreach ($this->providers as $provider) {
            if (microtime(true) - $startedAt >= static::TIME_BUDGET_SECONDS) {
                $this->logger->warning('Geocoding: time budget used up, not trying ' . $provider->getName() . ' or later providers');
                return;
            }

            if (!$provider->supports($resolvedCountry)) {
                $this->logger->debug('Geocoding: ' . $provider->getName() . ' skipped, does not cover "' . $resolvedCountry . '"');
                continue;
            }

            $this->logger->debug('Geocoding: trying ' . $provider->getName());
            try {
                $result = $provider->geocode($street, $city, $state, $zip, $country);
            } catch (\Throwable $exception) {
                $this->logger->warning('Geocoding: ' . $provider->getName() . ' error: ' . $exception->getMessage());
                continue;
            }

            if ($result === null || !$result->hasCoordinates()) {
                $this->logger->debug('Geocoding: ' . $provider->getName() . ' had no result');
                continue;
            }

            $this->logger->debug('Geocoding: ' . $provider->getName() . ' found lat=' . $result->latitude . ', lng=' . $result->longitude);
            yield $result;
        }
    }

    /**
     * The first usable result, or null when no provider found the address.
     */
    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): ?GeocodeResult
    {
        foreach ($this->results($street, $city, $state, $zip, $country) as $result) {
            return $result;
        }

        $this->logger->warning('Geocoding: No results found for address from any provider (see service log for familyId)');

        return null;
    }
}
