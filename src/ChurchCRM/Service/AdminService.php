<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\URLValidator;

/**
 * Service for admin dashboard checks.
 * These checks run only on the admin dashboard, not on every page load.
 */
class AdminService
{
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
