<?php
/*
 * GHSA-mp2w-4q3r-ppx7 — migrate legacy Config.php to JSON values file.
 *
 * Installs that were created by the pre-fix setup wizard have an
 * Include/Config.php rendered via string substitution, with DB credentials
 * baked into PHP literals. This migration moves the install-time values
 * out of executable PHP into Include/config-values.json and replaces
 * Config.php with the static bootstrap that reads and validates the JSON
 * at runtime via ChurchCRM\Config\ConfigLoader.
 *
 * The script is idempotent — re-running on an already-migrated install is
 * a no-op. It is registered in upgrade.json under the "current" block so
 * it runs as the terminal step of every upgrade path.
 *
 * Running context: this file is `require_once`'d by
 * ChurchCRM\Service\UpgradeService::upgradeDatabaseVersion() from the
 * admin's web request, at which point Include/Config.php has already been
 * loaded and the legacy $sSERVERNAME / $dbPort / $sUSER / $sPASSWORD /
 * $sDATABASE / $sRootPath / $URL globals are in scope.
 */

$includeDir = realpath(__DIR__ . '/../../Include');
if ($includeDir === false) {
    throw new RuntimeException('Unable to resolve Include directory path during config migration');
}

$configPath  = $includeDir . '/Config.php';
$examplePath = $includeDir . '/Config.php.example';
$valuesPath  = $includeDir . '/config-values.json';
$backupPath  = $includeDir . '/Config.php.legacy-backup';

// Already migrated — skip silently. This also covers fresh installs that
// were created by the new JSON-based wizard.
if (file_exists($valuesPath)) {
    return;
}

// Legacy Config.php was required earlier in the request chain, so the
// install-time variables are in the global scope.
$required = ['sSERVERNAME', 'dbPort', 'sUSER', 'sPASSWORD', 'sDATABASE', 'sRootPath', 'URL'];
foreach ($required as $var) {
    if (!array_key_exists($var, $GLOBALS)) {
        throw new RuntimeException("Legacy config variable not available for migration: \${$var}");
    }
}

$values = [
    'DB_SERVER_NAME' => (string) $GLOBALS['sSERVERNAME'],
    'DB_SERVER_PORT' => (string) $GLOBALS['dbPort'],
    'DB_NAME'        => (string) $GLOBALS['sDATABASE'],
    'DB_USER'        => (string) $GLOBALS['sUSER'],
    'DB_PASSWORD'    => (string) $GLOBALS['sPASSWORD'],
    'ROOT_PATH'      => (string) $GLOBALS['sRootPath'],
    'URL'            => (string) ($GLOBALS['URL'][0] ?? ''),
];

$json = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$tmp  = $valuesPath . '.tmp';
if (file_put_contents($tmp, $json, LOCK_EX) === false) {
    throw new RuntimeException('Failed to write config values file during upgrade migration');
}
@chmod($tmp, 0640);
if (!rename($tmp, $valuesPath)) {
    @unlink($tmp);
    throw new RuntimeException('Failed to finalize config values file during upgrade migration');
}

// Preserve the legacy Config.php so admins can recover any custom lines
// (extra $URL[N] entries, $bLockURL=TRUE, custom error_reporting, etc.).
if (file_exists($configPath) && !copy($configPath, $backupPath)) {
    throw new RuntimeException('Failed to back up legacy Config.php during upgrade migration');
}

// Replace Config.php with the static bootstrap that reads the JSON file.
if (!copy($examplePath, $configPath)) {
    throw new RuntimeException('Failed to install new Config.php bootstrap during upgrade migration');
}
