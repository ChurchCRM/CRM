<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\KeyManager;
use ChurchCRM\Utils\URLValidator;

/**
 * Service for admin dashboard checks.
 * These checks run only on the admin dashboard, not on every page load.
 */
class AdminService
{
    /**
     * Get setup tasks that need attention.
     * These are configuration items that should be completed during initial setup.
     *
     * @return array Array of setup tasks with 'title', 'desc', 'link', and 'icon'
     */
    public function getSetupTasks(): array
    {
        $tasks = [];

        // Check if church name is still default
        if (SystemConfig::getValue('sChurchName') === 'Some Church') {
            $tasks[] = [
                'title' => gettext('Update Church Name'),
                'desc' => gettext('Church name is set to default value'),
                'link' => SystemURLs::getRootPath() . '/SystemSettings.php',
                'icon' => 'fa-church',
            ];
        }

        // Check if email/SMTP is configured
        if (empty(SystemConfig::hasValidMailServerSettings())) {
            $tasks[] = [
                'title' => gettext('Configure Email'),
                'desc' => gettext('SMTP server settings are not configured'),
                'link' => SystemURLs::getRootPath() . '/SystemSettings.php',
                'icon' => 'fa-envelope',
            ];
        }

        return $tasks;
    }

    /**
     * Get system configuration warnings.
     * These are PHP/server configuration issues that may affect functionality.
     *
     * @return array Array of warnings with 'title', 'desc', 'link', and 'severity'
     */
    public function getSystemWarnings(): array
    {
        $warnings = [];

        // ZipArchive check
        if (!class_exists('ZipArchive')) {
            $warnings[] = [
                'title' => gettext('Missing PHP ZipArchive'),
                'desc' => gettext('ZipArchive extension required for upgrades'),
                'link' => SystemURLs::getRootPath() . '/admin/system/debug',
                'severity' => 'danger',
            ];
        }

        // Prerequisites check
        if (!AppIntegrityService::arePrerequisitesMet()) {
            $warnings[] = [
                'title' => gettext('Unmet Prerequisites'),
                'desc' => gettext('Some application prerequisites are not met'),
                'link' => SystemURLs::getRootPath() . '/admin/system/debug',
                'severity' => 'danger',
            ];
        }

        // Secrets configuration check
        if (!KeyManager::getAreAllSecretsDefined()) {
            $warnings[] = [
                'title' => gettext('Missing Secret Keys'),
                'desc' => gettext('Secret keys missing from Config.php'),
                'link' => SystemURLs::getSupportURL('SecretsConfigurationCheckTask'),
                'severity' => 'danger',
            ];
        }

        // HTTPS check
        if (!isset($_SERVER['HTTPS'])) {
            $warnings[] = [
                'title' => gettext('HTTPS Not Configured'),
                'desc' => gettext('Install TLS/SSL certificate for better security'),
                'link' => SystemURLs::getSupportURL('HttpsTask'),
                'severity' => 'warning',
            ];
        }

        return $warnings;
    }

    /**
     * Check if there are any critical warnings.
     *
     * @return bool True if there are danger-level warnings
     */
    public function hasCriticalWarnings(): bool
    {
        foreach ($this->getSystemWarnings() as $warning) {
            if ($warning['severity'] === 'danger') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the configuration URL ($URL[0]) is valid.
     * Returns error details if invalid, null if valid.
     *
     * @return array|null Error details with 'code', 'message', and 'url', or null if valid
     */
    public function getConfigurationURLError(): ?array
    {
        // Get the configured URL array from Config.php
        global $URL;

        // Check if URL is configured
        if (!isset($URL) || !is_array($URL) || empty($URL[0])) {
            return [
                'code' => 'missing_url',
                'message' => gettext('Base URL is not configured in Config.php'),
                'url' => '',
            ];
        }

        $primaryURL = $URL[0];

        // Validate the URL format and requirements
        if (!URLValidator::isValidConfigURL($primaryURL)) {
            $error = URLValidator::getValidationError($primaryURL);
            if ($error !== null) {
                $error['url'] = $primaryURL;
                return $error;
            }

            // Generic error if no specific error found
            return [
                'code' => 'invalid_url',
                'message' => gettext('Base URL configuration is invalid. Please check your Config.php file.'),
                'url' => $primaryURL,
            ];
        }

        return null;
    }
}
