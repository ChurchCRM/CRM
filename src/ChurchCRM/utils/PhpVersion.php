<?php
namespace ChurchCRM\Utils;

class PhpVersion
{
    /**
     * Return the required PHP version configured in composer.json `config.platform.php`.
     * Falls back to '8.2.0' if not present or on error.
     *
     * @param string|null $composerFile Optional path to composer.json
     * @return string
     */
    public static function getRequiredPhpVersion(?string $composerFile = null): string
    {
        $fallback = '8.2.0';

        if ($composerFile === null) {
            $composerFile = __DIR__ . '/../../composer.json';
        }

        // Obtain logger if available and emit helpful warnings when we must fall back
        $logger = null;
        if (class_exists('ChurchCRM\\Utils\\LoggerUtils')) {
            $logger = LoggerUtils::getAppLogger();
        }

        if (!file_exists($composerFile)) {
            if ($logger) {
                $logger->warning('composer.json not found at {path}; using fallback PHP version {fallback}', [
                    'path' => $composerFile,
                    'fallback' => $fallback,
                ]);
            }
            return $fallback;
        }

        $json = @file_get_contents($composerFile);
        if ($json === false) {
            if ($logger) {
                $logger->warning('Unable to read composer.json at {path}; using fallback PHP version {fallback}', [
                    'path' => $composerFile,
                    'fallback' => $fallback,
                ]);
            }
            return $fallback;
        }

        $composer = json_decode($json, true);
        if (!is_array($composer) || empty($composer['config']['platform']['php'])) {
            if ($logger) {
                $logger->warning('composer.json at {path} does not contain config.platform.php; using fallback PHP version {fallback}', [
                    'path' => $composerFile,
                    'fallback' => $fallback,
                ]);
            }
            return $fallback;
        }

        $req = (string)$composer['config']['platform']['php'];
        // Normalize short form like "8.2" -> "8.2.0"
        if (preg_match('/^\d+\.\d+$/', $req)) {
            $req .= '.0';
        }

        return $req ?: $fallback;
    }
}
