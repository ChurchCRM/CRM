<?php

namespace ChurchCRM\Service\Geocoding;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Log\LoggerInterface;

/**
 * Tries the configured geocoding providers in order until one returns
 * coordinates. The order comes from the `sGeocoderProviders` setting, a
 * comma-separated list such as "Nominatim, Census"; a church that does not
 * want a provider removes it from the list. Unknown names are logged and
 * ignored; an empty or entirely invalid list falls back to Nominatim.
 */
class GeocoderChain
{
    public const CONFIG_KEY = 'sGeocoderProviders';

    /** Registry of providers that ship with ChurchCRM, keyed by lower-case name. */
    private const PROVIDERS = [
        'nominatim' => NominatimGeocoder::class,
        'census'    => CensusGeocoder::class,
    ];

    /** @var GeocoderProviderInterface[] */
    private array $providers;

    private LoggerInterface $logger;

    /**
     * @param GeocoderProviderInterface[] $providers in order of preference
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
        $this->logger = LoggerUtils::getAppLogger();
    }

    /**
     * Build the chain from the `sGeocoderProviders` setting.
     */
    public static function fromConfig(): self
    {
        return new self(self::resolveProviders((string) SystemConfig::getValue(self::CONFIG_KEY)));
    }

    /**
     * Names of every provider that ships with ChurchCRM, for settings help text.
     *
     * @return string[]
     */
    public static function availableProviderNames(): array
    {
        return array_map(static fn (string $class): string => (new $class())->getName(), array_values(self::PROVIDERS));
    }

    /**
     * Turn "Nominatim, Census" into provider instances, in order.
     *
     * @return GeocoderProviderInterface[]
     */
    public static function resolveProviders(string $ranking): array
    {
        $logger = LoggerUtils::getAppLogger();
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
     * Ask each provider in turn. Returns [0, 0] when none of them found the address.
     *
     * @return array{Latitude: float, Longitude: float}
     */
    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country): array
    {
        foreach ($this->providers as $provider) {
            if (!$provider->supports($country)) {
                $this->logger->debug('Geocoding: ' . $provider->getName() . ' skipped, does not cover "' . $country . '"');
                continue;
            }

            $this->logger->debug('Geocoding: trying ' . $provider->getName());
            try {
                $result = $provider->geocode($street, $city, $state, $zip, $country);
            } catch (\Throwable $exception) {
                $this->logger->warning('Geocoding: ' . $provider->getName() . ' error: ' . $exception->getMessage());
                continue;
            }

            // Cast before comparing: a provider returning integer zeros must not pass as a hit.
            if ($result !== null && ((float) $result['Latitude'] !== 0.0 || (float) $result['Longitude'] !== 0.0)) {
                $this->logger->debug(
                    'Geocoding: ' . $provider->getName() . ' found lat=' . $result['Latitude'] . ', lng=' . $result['Longitude']
                );
                return $result;
            }
            $this->logger->debug('Geocoding: ' . $provider->getName() . ' had no result');
        }

        $this->logger->warning('Geocoding: No results found for address from any provider (see service log for familyId)');
        return ['Latitude' => 0.0, 'Longitude' => 0.0];
    }
}
