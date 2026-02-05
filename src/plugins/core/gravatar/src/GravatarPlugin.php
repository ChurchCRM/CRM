<?php

namespace ChurchCRM\Plugins\Gravatar;

use ChurchCRM\Plugin\AbstractPlugin;

/**
 * Gravatar Integration Plugin.
 *
 * A config-only plugin that enables Gravatar support in ChurchCRM.
 * The actual Gravatar rendering is handled client-side by avatar-loader.ts
 * using the avatar-initials library.
 *
 * This plugin exposes:
 * - enabled: Whether Gravatar is enabled (passed to client as bEnableGravatarPhotos)
 * - defaultImage: Gravatar fallback style (mp, identicon, monsterid, etc.)
 *
 * @see webpack/avatar-loader.ts for client-side implementation
 * @see https://gravatar.com/
 */
class GravatarPlugin extends AbstractPlugin
{
    /**
     * Default image types supported by Gravatar.
     * @see https://docs.gravatar.com/general/images/
     */
    public const DEFAULT_IMAGES = [
        'mp' => 'Mystery Person - Gray silhouette outline',
        'identicon' => 'Identicon - Geometric pattern based on email',
        'monsterid' => 'MonsterID - Unique generated monster face',
        'wavatar' => 'Wavatar - Abstract generated face',
        'retro' => 'Retro - 8-bit arcade-style pixel art',
        'robohash' => 'RoboHash - Unique generated robot',
        'blank' => 'Blank - Transparent (falls back to initials)',
    ];

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
        // No additional configuration required beyond being enabled
        return true;
    }

    public function registerRoutes($routeCollector): void
    {
        // No custom routes - Gravatar handled client-side
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'defaultImage',
                'label' => gettext('Default Image Style'),
                'type' => 'select',
                'options' => array_keys(self::DEFAULT_IMAGES),
                'optionLabels' => array_values(self::DEFAULT_IMAGES),
                'help' => gettext('Image shown when no Gravatar exists for the email address'),
            ],
        ];
    }

    /**
     * Get the configured default image style.
     */
    public function getDefaultImage(): string
    {
        return $this->getConfigValue('defaultImage') ?: 'blank';
    }

    /**
     * Get client-side configuration for this plugin.
     * This is exposed to JavaScript via window.CRM.plugins.gravatar
     *
     * @return array Configuration for client-side use
     */
    public function getClientConfig(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'defaultImage' => $this->getDefaultImage(),
        ];
    }
}
