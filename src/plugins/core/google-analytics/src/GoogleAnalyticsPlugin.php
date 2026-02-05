<?php

namespace ChurchCRM\Plugins\GoogleAnalytics;

use ChurchCRM\Plugin\AbstractPlugin;

/**
 * Google Analytics Plugin.
 *
 * Injects Google Analytics 4 (GA4) tracking code into pages.
 */
class GoogleAnalyticsPlugin extends AbstractPlugin
{
    private ?string $trackingId = null;

    public function getId(): string
    {
        return 'google-analytics';
    }

    public function getName(): string
    {
        return 'Google Analytics';
    }

    public function getDescription(): string
    {
        return 'Track ChurchCRM usage with Google Analytics 4.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        $this->trackingId = $this->getConfigValue('trackingId');
        $this->log('Google Analytics plugin booted');
    }

    public function activate(): void
    {
        $this->log('Google Analytics plugin activated');
    }

    public function deactivate(): void
    {
        $this->log('Google Analytics plugin deactivated');
    }

    public function uninstall(): void
    {
        // Nothing to clean up
    }

    public function isConfigured(): bool
    {
        return !empty($this->trackingId);
    }

    public function registerRoutes($routeCollector): void
    {
        // No routes needed - analytics is injected into pages
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'trackingId',
                'label' => gettext('GA4 Measurement ID'),
                'type' => 'text',
                'help' => gettext('Your GA4 Measurement ID (G-XXXXXXXXXX)'),
            ],
        ];
    }

    // =========================================================================
    // Page Content Injection
    // =========================================================================

    /**
     * Inject Google Analytics tracking code into page head.
     * Google recommends placing the gtag.js snippet in the <head> section.
     * This is called automatically for active plugins.
     */
    public function getHeadContent(): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $trackingId = $this->trackingId;

        ob_start();
        require __DIR__ . '/../templates/tracking-code.php';

        return ob_get_clean();
    }

    /**
     * Get JavaScript code to track a custom event.
     *
     * @param string $eventName Event name
     * @param array  $params    Event parameters
     *
     * @return string JavaScript code to track event
     */
    public function getEventTrackingCode(string $eventName, array $params = []): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $eventName = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
        $paramsJson = json_encode($params, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        return "gtag('event', '{$eventName}', {$paramsJson});";
    }
}
