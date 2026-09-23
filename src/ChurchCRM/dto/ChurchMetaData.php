<?php

namespace ChurchCRM\dto;

use ChurchCRM\Service\ChurchLogoService;
use ChurchCRM\Utils\GeoUtils;

/**
 * Strongly-typed accessors for the church-identity portion of SystemConfig
 * (`sChurchName`, `sChurchAddress`, ...). Each string getter always
 * returns a trimmed string — never null — so callers don't need to cast,
 * trim, or null-check at every use site. An unset/whitespace-only config
 * value surfaces as `""` and callers can apply `?:` fallbacks cleanly.
 */
class ChurchMetaData
{
    /**
     * The church's social media networks, in the order they are rendered
     * everywhere (Church Info preview, member portal footer, ...). Each entry
     * names the SystemConfig key holding the URL and the Font Awesome *Free*
     * brand class used for its icon — `fa-brands fa-x-twitter` is the free
     * build's name for the X glyph.
     */
    private const SOCIAL_NETWORKS = [
        ['id' => 'x', 'label' => 'X', 'config' => 'sChurchX', 'icon' => 'fa-brands fa-x-twitter'],
        ['id' => 'youtube', 'label' => 'YouTube', 'config' => 'sChurchYouTube', 'icon' => 'fa-brands fa-youtube'],
        ['id' => 'facebook', 'label' => 'Facebook', 'config' => 'sChurchFacebook', 'icon' => 'fa-brands fa-facebook'],
        ['id' => 'instagram', 'label' => 'Instagram', 'config' => 'sChurchInstagram', 'icon' => 'fa-brands fa-instagram'],
    ];

    /** Read a SystemConfig key as a trimmed string, coalescing null. */
    private static function readString(string $key): string
    {
        return trim((string) SystemConfig::getValue($key));
    }

    public static function getChurchName(): string
    {
        return self::readString('sChurchName');
    }

    public static function getChurchFullAddress(): string
    {
        $address = [];
        if (self::getChurchAddress() !== '') {
            $address[] = self::getChurchAddress();
        }

        if (self::getChurchCity() !== '') {
            $address[] = self::getChurchCity() . ',';
        }

        if (self::getChurchState() !== '') {
            $address[] = self::getChurchState();
        }

        if (self::getChurchZip() !== '') {
            $address[] = self::getChurchZip();
        }
        if (self::getChurchCountry() !== '') {
            $address[] = self::getChurchCountry();
        }

        return implode(' ', $address);
    }

    public static function getChurchAddress(): string
    {
        return self::readString('sChurchAddress');
    }

    public static function getChurchCity(): string
    {
        return self::readString('sChurchCity');
    }

    public static function getChurchState(): string
    {
        return self::readString('sChurchState');
    }

    public static function getChurchZip(): string
    {
        return self::readString('sChurchZip');
    }

    public static function getChurchCountry(): string
    {
        return self::readString('sChurchCountry');
    }

    public static function getChurchEmail(): string
    {
        return self::readString('sChurchEmail');
    }

    public static function getChurchPhone(): string
    {
        return self::readString('sChurchPhone');
    }

    public static function getChurchWebSite(): string
    {
        return self::readString('sChurchWebSite');
    }

    /**
     * True when an administrator has uploaded a church logo through
     * Admin -> Church Information.
     */
    public static function hasCustomLogo(): bool
    {
        return ChurchLogoService::hasCustomLogo();
    }

    /**
     * Absolute URL of the church logo for use in email templates (and
     * eventually other external-facing surfaces like letters or reports).
     *
     * Precedence: the uploaded `Images/church-logo.png` -> the `sChurchLogoURL`
     * setting (when it is a valid http(s) URL) -> the bundled ChurchCRM logo,
     * so external email clients always see a working image.
     */
    public static function getChurchLogoURL(): string
    {
        return self::resolveLogo(SystemURLs::getURL(), true);
    }

    /**
     * Root-path-relative URL of the church logo, for use in the application's
     * own templates (sidebar brand, login and auth pages).
     *
     * Precedence: the uploaded `Images/church-logo.png` -> the bundled
     * ChurchCRM logo. The configured `sChurchLogoURL` is deliberately skipped
     * because in-app pages are served with a Content-Security-Policy whose
     * `img-src` is `'self'`, so a remote logo would be blocked and render broken.
     */
    public static function getChurchLogoPath(): string
    {
        return self::resolveLogo(SystemURLs::getRootPath(), false);
    }

    /**
     * Shared logo precedence. $prefix is prepended to locally served images.
     * When $allowConfiguredUrl is true a valid remote `sChurchLogoURL` is
     * returned unchanged; callers rendering in-app pages must pass false so the
     * CSP `img-src 'self'` policy cannot block the logo.
     */
    private static function resolveLogo(string $prefix, bool $allowConfiguredUrl): string
    {
        if (ChurchLogoService::hasCustomLogo()) {
            // Content-hash cache-buster: the URL changes whenever the bytes do,
            // so a re-upload is picked up immediately, even seconds apart.
            return $prefix . '/Images/' . ChurchLogoService::LOGO_FILENAME
                . '?v=' . ChurchLogoService::getVersion();
        }

        if ($allowConfiguredUrl) {
            $configured = self::readString('sChurchLogoURL');
            if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL) !== false) {
                return $configured;
            }
        }

        return $prefix . '/Images/logo-churchcrm-350.jpg';
    }

    /**
     * Church latitude as a float; `0.0` when unset. Triggers a geocode
     * against the configured full address on first read if missing.
     */
    public static function getChurchLatitude(): float
    {
        if (self::readString('iChurchLatitude') === '') {
            self::updateLatLng();
        }

        return (float) SystemConfig::getValue('iChurchLatitude');
    }

    public static function getChurchLongitude(): float
    {
        if (self::readString('iChurchLongitude') === '') {
            self::updateLatLng();
        }

        return (float) SystemConfig::getValue('iChurchLongitude');
    }

    /** True when a geocoded latitude is stored; use in place of the old `!== ''` check. */
    public static function hasChurchLocation(): bool
    {
        return self::readString('iChurchLatitude') !== '' && self::readString('iChurchLongitude') !== '';
    }

    public static function getChurchTimeZone(): string
    {
        return self::readString('sTimeZone');
    }

    private static function updateLatLng(): void
    {
        if (self::getChurchFullAddress() !== '') {
            $latLng = GeoUtils::getLatLong(self::getChurchFullAddress());
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                SystemConfig::setValue('iChurchLatitude', $latLng['Latitude']);
                SystemConfig::setValue('iChurchLongitude', $latLng['Longitude']);
            }
        }
    }

    /**
     * The church's configured social media links, in the stable order
     * X, YouTube, Facebook, Instagram. Networks with no URL configured are
     * omitted entirely, so a caller can render the result directly and show
     * nothing when the array is empty.
     *
     * @return array<int, array{id: string, label: string, url: string, icon: string}>
     */
    public static function getChurchSocialLinks(): array
    {
        $links = [];
        foreach (self::SOCIAL_NETWORKS as $network) {
            $url = self::readString($network['config']);
            if ($url === '') {
                continue;
            }

            $links[] = [
                'id'    => $network['id'],
                'label' => $network['label'],
                'url'   => $url,
                'icon'  => $network['icon'],
            ];
        }

        return $links;
    }

    /**
     * The social networks as the Church Info editor needs them: every network
     * (not just the configured ones), each carrying its SystemConfig key and
     * current value so the form can render an input per network.
     *
     * @return array<int, array{id: string, label: string, config: string, icon: string, url: string}>
     */
    public static function getChurchSocialNetworkFields(): array
    {
        $fields = [];
        foreach (self::SOCIAL_NETWORKS as $network) {
            $fields[] = $network + ['url' => self::readString($network['config'])];
        }

        return $fields;
    }

    /**
     * Validation rule for a church social media URL: empty means "not set",
     * anything else must be an absolute `https://` URL with a host. Plain
     * `http://`, scheme-relative and bare handles are rejected — these links
     * are rendered as outbound anchors on pages members see.
     */
    public static function isValidSocialUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host   = parse_url($url, PHP_URL_HOST);

        return strtolower((string) $scheme) === 'https' && !empty($host);
    }

}
