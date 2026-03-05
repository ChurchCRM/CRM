<?php

namespace ChurchCRM\Plugins\Maps;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Plugin\AbstractPlugin;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
use Geocoder\Query\GeocodeQuery;
use Geocoder\StatefulGeocoder;
use Http\Adapter\Guzzle7\Client;

/**
 * Maps & Geocoding Plugin.
 *
 * Manages the Google Maps API key used for server-side address geocoding
 * (stored as plugin.maps.googleMapsGeocodeKey in system_config).
 *
 * Church lat/long (iChurchLatitude, iChurchLongitude) remain in system_config
 * as church information settings; the test endpoint populates them via
 * SystemConfig::setValue() after a successful geocode.
 */
class MapsPlugin extends AbstractPlugin
{
    private static ?MapsPlugin $instance = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?MapsPlugin
    {
        return self::$instance;
    }

    public function getId(): string
    {
        return 'maps';
    }

    public function getName(): string
    {
        return 'Maps & Geocoding';
    }

    public function getDescription(): string
    {
        return 'Google Maps integration for address geocoding and church location display.';
    }

    public function boot(): void
    {
        $this->log('Maps plugin booted', 'debug');
    }

    public function isConfigured(): bool
    {
        return !empty($this->getConfigValue('googleMapsGeocodeKey'));
    }

    public function getConfigurationError(): ?string
    {
        if (!$this->isConfigured()) {
            return gettext('Google Maps API Key is required for geocoding.');
        }
        return null;
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key'  => 'googleMapsGeocodeKey',
                'label' => gettext('Google Maps API Key'),
                'type' => 'text',
                'help' => gettext('Used for server-side address geocoding. Get yours at https://developers.google.com/maps/documentation/javascript/get-api-key'),
            ],
        ];
    }

    // =========================================================================
    // Connection Testing
    // =========================================================================

    /**
     * Validate the Google Maps API key by geocoding the church address.
     *
     * On success the resolved coordinates are written directly to the
     * iChurchLatitude and iChurchLongitude system config values so they
     * are immediately available to the rest of the application.
     *
     * @param array $settings Keys: googleMapsGeocodeKey
     *
     * @return array{success: bool, message: string, details?: array<string, mixed>}
     */
    public function testWithSettings(array $settings): array
    {
        $apiKey = $settings['googleMapsGeocodeKey'] ?? $this->getConfigValue('googleMapsGeocodeKey');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => gettext('Google Maps API Key is required.'),
            ];
        }

        $churchAddress = ChurchMetaData::getChurchFullAddress();
        if (empty($churchAddress)) {
            return [
                'success' => false,
                'message' => gettext('Church address is not configured. Please set the church address in General Settings before testing.'),
            ];
        }

        try {
            $localeInfo = Bootstrapper::getCurrentLocale();
            $adapter    = new Client();
            $provider   = new GoogleMaps($adapter, null, $apiKey);
            $geoCoder   = new StatefulGeocoder($provider, $localeInfo->getShortLocale());
            $result     = $geoCoder->geocodeQuery(GeocodeQuery::create($churchAddress));

            if ($result->isEmpty()) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        gettext('No geocoding results returned for address: %s'),
                        $churchAddress
                    ),
                ];
            }

            $firstResult = $result->get(0);
            $coordinates = $firstResult->getCoordinates();
            $latitude    = $coordinates->getLatitude();
            $longitude   = $coordinates->getLongitude();

            // Store resolved coordinates in the standard church-info system config
            SystemConfig::setValue('iChurchLatitude', (string) $latitude);
            SystemConfig::setValue('iChurchLongitude', (string) $longitude);

            $this->log('Maps plugin test succeeded', 'info', [
                'address'   => $churchAddress,
                'latitude'  => $latitude,
                'longitude' => $longitude,
            ]);

            return [
                'success' => true,
                'message' => sprintf(
                    gettext('Address geocoded successfully: %s → %s, %s'),
                    $churchAddress,
                    $latitude,
                    $longitude
                ),
                'details' => [
                    'address'   => $churchAddress,
                    'latitude'  => $latitude,
                    'longitude' => $longitude,
                ],
            ];
        } catch (\Exception $e) {
            $this->log('Maps plugin test failed: ' . $e->getMessage(), 'warning');

            return [
                'success' => false,
                'message' => sprintf(
                    gettext('Geocoding failed: %s. Please verify your API key and ensure the Geocoding API is enabled.'),
                    $e->getMessage()
                ),
            ];
        }
    }
}
