<?php

namespace ChurchCRM\Plugins\GoogleAnalytics;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;

/**
 * Google Analytics Plugin.
 *
 * Injects Google Analytics tracking code into pages.
 * Supports both Universal Analytics (UA-) and GA4 (G-) tracking IDs.
 */
class GoogleAnalyticsPlugin extends AbstractPlugin
{
    private ?string $trackingId = null;
    private bool $isGA4 = false;

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
        return 'Track ChurchCRM usage with Google Analytics.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        $this->trackingId = $this->getConfigValue('trackingId');

        if ($this->isConfigured()) {
            // Determine if GA4 or Universal Analytics
            $this->isGA4 = str_starts_with($this->trackingId, 'G-');
        }

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
                'label' => gettext('Google Analytics Tracking ID'),
                'type' => 'text',
                'help' => gettext('GA4 (G-XXXXXXXXXX) or Universal Analytics (UA-XXXXXX-X)'),
            ],
        ];
    }

    // =========================================================================
    // Analytics Methods
    // =========================================================================

    /**
     * Get the Google Analytics tracking code to inject into pages.
     *
     * @return string HTML/JavaScript code
     */
    public function getTrackingCode(): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        if ($this->isGA4) {
            return $this->getGA4TrackingCode();
        }

        return $this->getUniversalAnalyticsCode();
    }

    /**
     * Get GA4 tracking code (gtag.js).
     */
    private function getGA4TrackingCode(): string
    {
        $trackingId = htmlspecialchars($this->trackingId, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!-- Google Analytics (GA4) - ChurchCRM Plugin -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$trackingId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$trackingId}');
</script>
HTML;
    }

    /**
     * Get Universal Analytics tracking code (analytics.js).
     */
    private function getUniversalAnalyticsCode(): string
    {
        $trackingId = htmlspecialchars($this->trackingId, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!-- Google Analytics (Universal) - ChurchCRM Plugin -->
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
  ga('create', '{$trackingId}', 'auto');
  ga('send', 'pageview');
</script>
HTML;
    }

    /**
     * Track a custom event.
     *
     * @param string $category Event category
     * @param string $action   Event action
     * @param string $label    Event label (optional)
     * @param int    $value    Event value (optional)
     *
     * @return string JavaScript code to track event
     */
    public function getEventTrackingCode(string $category, string $action, string $label = '', int $value = 0): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $category = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        if ($this->isGA4) {
            return <<<JS
gtag('event', '{$action}', {
  'event_category': '{$category}',
  'event_label': '{$label}',
  'value': {$value}
});
JS;
        }

        return <<<JS
ga('send', 'event', '{$category}', '{$action}', '{$label}', {$value});
JS;
    }
}
