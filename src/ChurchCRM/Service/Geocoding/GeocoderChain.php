<?php

namespace ChurchCRM\Service\Geocoding;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\AddressDefaults;
use ChurchCRM\Utils\LoggerUtils;
use Generator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Asks the configured geocoding providers one by one. The order comes from the
 * `sGeocoderProviders` setting, a comma-separated list such as "Nominatim, US Census"
 * (just "Nominatim" by default; US Census is opt-in). Unknown names are logged and
 * ignored; an empty or entirely invalid list falls back to Nominatim.
 *
 * results() is lazy: a provider is only called when the caller asks for the next
 * result, so stopping at the first usable one never contacts the rest.
 *
 * A provider that is unavailable (timeout, 429, 5xx) is retried with backoff inside
 * TIME_BUDGET_SECONDS, then the next provider is tried. lookup() tells "not found"
 * from "unavailable".
 */
class GeocoderChain
{
    public const CONFIG_KEY = 'sGeocoderProviders';

    /** No further provider is started after this many seconds, which keeps a family save under PHP's 30 s limit. */
    public const TIME_BUDGET_SECONDS = 18;

    /** Extra attempts after a provider reports unavailable. */
    public const MAX_RETRIES = 2;

    /** Longest a single request may take, before the remaining budget shortens it. */
    private const REQUEST_TIMEOUT_SECONDS = 10;

    /** Registry of providers that ship with ChurchCRM, keyed by lower-case name. */
    private const PROVIDERS = [
        'nominatim' => NominatimGeocoder::class,
        'us census' => CensusGeocoder::class,
    ];

    /** @var array<string, GeocoderProviderInterface[]> providers per ranking string, resolved once per request */
    private static array $resolved = [];

    /** @var GeocoderProviderInterface[] */
    private array $providers;

    private LoggerInterface $logger;

    /**
     * @param GeocoderProviderInterface[] $providers in order of preference
     */
    public function __construct(array $providers, ?LoggerInterface $logger = null)
    {
        $this->providers = $providers;
        $this->logger = $logger ?? LoggerUtils::getAppLogger() ?? new NullLogger();
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
     * Names of the providers that ship with ChurchCRM, for the settings picker.
     *
     * @return string[]
     */
    public static function availableProviderNames(): array
    {
        return array_map(static fn (string $class): string => (new $class())->getName(), array_values(self::PROVIDERS));
    }

    /**
     * The country used to decide which providers apply: the record's own, else the
     * default country, else the church country. Null when all of them are blank.
     */
    public static function resolveCountry(?string $recordCountry): ?string
    {
        $recordCountry = trim((string) $recordCountry);
        if ($recordCountry !== '') {
            return $recordCountry;
        }

        return AddressDefaults::country() ?: null;
    }

    /**
     * Turn "Nominatim, US Census" into provider instances, in order.
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
     * Providers that do not cover the country, are unavailable, or find nothing are skipped.
     * $country is the record's own country; the country that decides which providers
     * apply is resolved from it (see resolveCountry()).
     *
     * @return Generator<int, GeocodeResult>
     */
    public function results(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): Generator
    {
        foreach ($this->outcomes($street, $city, $state, $zip, $country) as $outcome) {
            if ($outcome->result !== null) {
                yield $outcome->result;
            }
        }
    }

    /**
     * The first provider's answer that found the address. Otherwise unavailable when
     * any provider that was asked could not answer (the address may resolve later),
     * else not found.
     */
    public function lookup(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): GeocodeOutcome
    {
        $unavailable = false;
        foreach ($this->outcomes($street, $city, $state, $zip, $country) as $outcome) {
            if ($outcome->isFound()) {
                return $outcome;
            }
            $unavailable = $unavailable || $outcome->isUnavailable();
        }

        if ($unavailable) {
            $this->logger->warning('Geocoding: no provider could answer, the address may resolve later');

            return GeocodeOutcome::unavailable();
        }

        $this->logger->warning('Geocoding: No results found for address from any provider (see service log for familyId)');

        return GeocodeOutcome::notFound();
    }

    /**
     * The first usable result, or null when no provider found the address.
     */
    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): ?GeocodeResult
    {
        return $this->lookup($street, $city, $state, $zip, $country)->result;
    }

    /**
     * One outcome per provider asked, lazily, in provider order. A provider that is
     * unavailable is retried with jittered backoff while the time budget allows.
     *
     * @return Generator<int, GeocodeOutcome>
     */
    private function outcomes(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): Generator
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

            $outcome = $this->ask($provider, $startedAt, $street, $city, $state, $zip, $country);
            if ($outcome->isFound()) {
                $result = $outcome->result;
                $this->logger->debug('Geocoding: ' . $provider->getName() . ' found lat=' . $result?->latitude . ', lng=' . $result?->longitude);
            } else {
                $this->logger->debug('Geocoding: ' . $provider->getName() . ' ' . ($outcome->isUnavailable() ? 'was unavailable' : 'had no result'));
            }

            yield $outcome;
        }
    }

    private function ask(GeocoderProviderInterface $provider, float $startedAt, string $street, ?string $city, ?string $state, ?string $zip, ?string $country): GeocodeOutcome
    {
        for ($attempt = 1; ; $attempt++) {
            $remaining = static::TIME_BUDGET_SECONDS - (microtime(true) - $startedAt);
            $this->logger->debug('Geocoding: trying ' . $provider->getName() . ' (attempt ' . $attempt . ')');

            try {
                $outcome = $provider->geocode($street, $city, $state, $zip, $country, max(1, min(self::REQUEST_TIMEOUT_SECONDS, (int) ceil($remaining))));
            } catch (\Throwable $exception) {
                $this->logger->warning('Geocoding: ' . $provider->getName() . ' error: ' . $exception->getMessage());

                return GeocodeOutcome::unavailable();
            }

            if (!$outcome->isUnavailable()) {
                return $outcome;
            }

            // Nominatim allows one request per second, so wait at least that long.
            $wait = max(1, $outcome->retryAfterSeconds ?? 0) + random_int(0, 500) / 1000;
            $elapsed = microtime(true) - $startedAt;
            $this->logger->warning(
                'Geocoding: ' . $provider->getName() . ' unavailable (attempt ' . $attempt . ' of ' . (self::MAX_RETRIES + 1)
                . ', retry-after ' . ($outcome->retryAfterSeconds ?? 'none') . ')'
            );
            if ($attempt > self::MAX_RETRIES || $elapsed + $wait + 1 >= static::TIME_BUDGET_SECONDS) {
                return $outcome;
            }

            $this->pause($wait);
        }
    }

    protected function pause(float $seconds): void
    {
        usleep((int) ($seconds * 1_000_000));
    }
}
