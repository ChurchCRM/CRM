<?php
namespace ChurchCRM\Utils;

class PhpVersion
{
    /**
     * Return the required PHP version configured in composer.json `config.platform.php`.
     *
     * @param string|null $composerFile Optional path to composer.json
     * @return string
     * @throws \RuntimeException if composer.json cannot be read or config.platform.php is missing
     */
    public static function getRequiredPhpVersion(?string $composerFile = null): string
    {
        if ($composerFile === null) {
            $composerFile = __DIR__ . '/../../composer.json';
        }

        if (!file_exists($composerFile)) {
            throw new \RuntimeException(
                "Cannot determine PHP system state: composer.json not found at {$composerFile}. "
                . "Unable to read required PHP version configuration."
            );
        }

        $json = @file_get_contents($composerFile);
        if ($json === false) {
            throw new \RuntimeException(
                "Cannot determine PHP system state: Unable to read composer.json at {$composerFile}. "
                . "Check file permissions or disk integrity."
            );
        }

        $composer = json_decode($json, true);
        if (!is_array($composer)) {
            throw new \RuntimeException(
                "Cannot determine PHP system state: composer.json at {$composerFile} is not valid JSON. "
                . "System configuration is corrupted."
            );
        }

        if (empty($composer['config']['platform']['php'])) {
            throw new \RuntimeException(
                "Cannot determine PHP system state: composer.json at {$composerFile} does not contain "
                . "'config.platform.php' configuration. System setup is incomplete or corrupted."
            );
        }

        $req = (string)$composer['config']['platform']['php'];
        // Normalize short form like "8.2" -> "8.2.0"
        if (preg_match('/^\d+\.\d+$/', $req)) {
            $req .= '.0';
        }

        return $req;
    }
}
