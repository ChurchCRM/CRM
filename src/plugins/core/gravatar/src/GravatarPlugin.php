<?php

namespace ChurchCRM\Plugins\Gravatar;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;

/**
 * Gravatar Integration Plugin.
 *
 * Provides Gravatar profile photos as fallback for members
 * who don't have uploaded photos in ChurchCRM.
 *
 * @see https://gravatar.com/
 */
class GravatarPlugin extends AbstractPlugin
{
    private const GRAVATAR_BASE_URL = 'https://www.gravatar.com/avatar/';

    /**
     * Default image types supported by Gravatar.
     */
    private const DEFAULT_IMAGES = ['mp', 'identicon', 'monsterid', 'wavatar', 'retro', 'robohash'];

    private bool $enabled = false;
    private string $defaultImage = 'mp';

    public function getId(): string
    {
        return 'gravatar';
    }

    public function getName(): string
    {
        return 'Gravatar Photos';
    }

    public function getDescription(): string
    {
        return 'Use Gravatar profile photos for members without uploaded photos.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        $this->enabled = SystemConfig::getBooleanValue('bEnableGravatarPhotos');
        $defaultImage = SystemConfig::getValue('sGravatarDefaultImage');

        if (in_array($defaultImage, self::DEFAULT_IMAGES, true)) {
            $this->defaultImage = $defaultImage;
        }

        $this->log('Gravatar plugin booted');
    }

    public function activate(): void
    {
        $this->log('Gravatar plugin activated');
    }

    public function deactivate(): void
    {
        $this->log('Gravatar plugin deactivated');
    }

    public function uninstall(): void
    {
        // Nothing to clean up
    }

    public function isConfigured(): bool
    {
        return $this->enabled;
    }

    public function registerRoutes($routeCollector): void
    {
        // No custom routes - integrates with photo system
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'bEnableGravatarPhotos',
                'label' => gettext('Enable Gravatar Photos'),
                'type' => 'boolean',
            ],
            [
                'key' => 'sGravatarDefaultImage',
                'label' => gettext('Default Image Style'),
                'type' => 'select',
                'options' => self::DEFAULT_IMAGES,
                'help' => gettext('Image shown when no Gravatar exists for email'),
            ],
        ];
    }

    // =========================================================================
    // Gravatar Methods
    // =========================================================================

    /**
     * Get a Gravatar URL for an email address.
     *
     * @param string $email Email address
     * @param int    $size  Image size in pixels (1-2048)
     *
     * @return string Gravatar URL
     */
    public function getGravatarUrl(string $email, int $size = 200): string
    {
        $email = strtolower(trim($email));
        $hash = md5($email);

        $params = http_build_query([
            's' => min(max($size, 1), 2048),
            'd' => $this->defaultImage,
            'r' => 'g', // Rating: g (general audiences)
        ]);

        return self::GRAVATAR_BASE_URL . $hash . '?' . $params;
    }

    /**
     * Check if an email has a Gravatar.
     *
     * @param string $email Email address
     *
     * @return bool True if Gravatar exists
     */
    public function hasGravatar(string $email): bool
    {
        $email = strtolower(trim($email));
        $hash = md5($email);
        $url = self::GRAVATAR_BASE_URL . $hash . '?d=404';

        $headers = @get_headers($url);

        return $headers && strpos($headers[0], '200') !== false;
    }

    /**
     * Get photo URL for a person (Gravatar fallback logic).
     *
     * @param string $email           Person's email
     * @param bool   $hasUploadedPhoto Whether person has uploaded photo
     * @param int    $size            Desired size
     *
     * @return string|null Gravatar URL or null if disabled/no email
     */
    public function getPhotoFallbackUrl(string $email, bool $hasUploadedPhoto, int $size = 200): ?string
    {
        // Only provide Gravatar if enabled and person has no uploaded photo
        if (!$this->enabled || $hasUploadedPhoto || empty($email)) {
            return null;
        }

        return $this->getGravatarUrl($email, $size);
    }
}
