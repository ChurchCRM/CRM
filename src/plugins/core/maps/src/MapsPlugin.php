<?php

namespace ChurchCRM\Plugins\Maps;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Utils\LoggerUtils;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
use Geocoder\Query\GeocodeQuery;
use Geocoder\StatefulGeocoder;
use Http\Adapter\Guzzle7\Client;

/**
 * Maps & Geocoding Plugin.
 *
 * Manages Google Maps API credentials, church geo-coordinates, and
 * the visibility of lat/lon fields in the Family Editor.
 *
 * When this plugin is active and configured, it takes precedence over
 * the legacy SystemConfig settings (sGoogleMapsGeocodeKey, iChurchLatitude,
 * iChurchLongitude, bHideLatLon).
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
        return !empty($this->getGoogleMapsGeocodeKey());
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
            [
                'key'  => 'churchLatitude',
                'label' => gettext('Church Latitude'),
                'type' => 'text',
                'help' => gettext('Latitude of the church, used to center the map. Auto-populated on test.'),
            ],
            [
                'key'  => 'churchLongitude',
                'label' => gettext('Church Longitude'),
                'type' => 'text',
                'help' => gettext('Longitude of the church, used to center the map. Auto-populated on test.'),
            ],
            [
                'key'  => 'hideLatLon',
                'label' => gettext('Hide Lat/Lon Fields in Family Editor'),
                'type' => 'checkbox',
                'help' => gettext('Hides the Latitude and Longitude input fields in the Family Editor. Lookups are still performed.'),
            ],
        ];
    }

    // =========================================================================
    // Setting Accessors
    // =========================================================================

    /**
     * Get the configured Google Maps API key.
     *
     * Returns the plugin-level key if set, otherwise falls back to the legacy
     * SystemConfig key (sGoogleMapsGeocodeKey) for backward compatibility.
     */
    public function getGoogleMapsGeocodeKey(): string
    {
        $key = $this->getConfigValue('googleMapsGeocodeKey');
        if (!empty($key)) {
            return $key;
        }
        // Fall back to legacy SystemConfig value
        return \ChurchCRM\dto\SystemConfig::getValue('sGoogleMapsGeocodeKey') ?? '';
    }

    /**
     * Get the stored church latitude.
     */
    public function getChurchLatitude(): string
    {
        return $this->getConfigValue('churchLatitude');
    }

    /**
     * Get the stored church longitude.
     */
    public function getChurchLongitude(): string
    {
        return $this->getConfigValue('churchLongitude');
    }

    /**
     * Return true when Lat/Lon fields should be hidden in the Family Editor.
     *
     * Falls back to the legacy SystemConfig value (bHideLatLon) when no
     * plugin-level value has been set.
     */
    public function isHideLatLon(): bool
    {
        $value = $this->getConfigValue('hideLatLon');
        if ($value !== '') {
            return $value === '1' || strtolower($value) === 'true';
        }
        // Fall back to legacy SystemConfig value
        return \ChurchCRM\dto\SystemConfig::getBooleanValue('bHideLatLon');
    }

    /**
     * Persist the church coordinates into plugin settings.
     *
     * @param string|float $latitude
     * @param string|float $longitude
     */
    public function setChurchLatLong($latitude, $longitude): void
    {
        $this->setConfigValue('churchLatitude', (string) $latitude);
        $this->setConfigValue('churchLongitude', (string) $longitude);
    }

    // =========================================================================
    // Connection Testing
    // =========================================================================

    /**
     * Validate the Google Maps API key by geocoding the church address.
     *
     * When the geocode succeeds the church lat/long are persisted into
     * plugin settings so the caller can see the resolved coordinates in
     * the response.  Password fields that are omitted from $settings fall
     * back to the currently-saved key.
     *
     * @param array $settings Keys: googleMapsGeocodeKey (optional)
     *
     * @return array{success: bool, message: string, details?: array<string, mixed>}
     */
    public function testWithSettings(array $settings): array
    {
        $apiKey = $settings['googleMapsGeocodeKey'] ?? '';

        // Fall back to saved key when the form field was left empty
        if (empty($apiKey)) {
            $apiKey = $this->getGoogleMapsGeocodeKey();
        }

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

            // Persist the resolved coordinates into plugin settings
            $this->setChurchLatLong($latitude, $longitude);

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
