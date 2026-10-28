<?php

/**
 * Created by PhpStorm.
 * User: georg
 * Date: 11/25/2017
 * Time: 1:28 PM.
 */

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\Version;
use ChurchCRM\Utils\SQLUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\VersionUtils;
use Propel\Runtime\Propel;

class UpgradeService
{
    public static function upgradeDatabaseVersion(): bool
    {
        $logger = LoggerUtils::getAppLogger();
        
        // Invalidate OPcache for the Composer-generated version files so subsequent requests
        // (including other FPM workers) read the newly-deployed version data from disk.
        // opcache_reset() only affects the current worker; opcache_invalidate() targets
        // specific files and forces recompilation across the cache.
        if (function_exists('opcache_invalidate')) {
            $docRoot = SystemURLs::getDocumentRoot();
            $composerVersionFiles = [
                $docRoot . '/vendor/composer/InstalledVersions.php',
                $docRoot . '/vendor/composer/installed.php',
                $docRoot . '/vendor/autoload.php',
            ];
            foreach ($composerVersionFiles as $file) {
                if (file_exists($file)) {
                    opcache_invalidate($file, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // Reset VersionUtils in-process static cache so this request also picks up the new version.
        // Guard with method_exists: during an in-place upgrade the old class definition may still
        // be loaded in memory (resetCache() was added in 7.0.3), so calling it unconditionally
        // would fatal on upgrades from any release that predates the method.
        if (method_exists(VersionUtils::class, 'resetCache')) {
            VersionUtils::resetCache();
        }
        
        $db_version = VersionUtils::getDBVersion();
        $installed_version = VersionUtils::getInstalledVersion();

        $logger->info(
            "Current Version: $db_version, Installed Version: $installed_version",
            [
                'dbVersion'                => $db_version,
                'softwareInstalledVersion' => $installed_version,
            ]
        );
        if ($db_version === $installed_version) {
            $logger->info('Database is already at current version, no upgrade needed');
            return true;
        }

        //the database isn't at the current version.  Start the upgrade
        $lockName = null;
        try {
            $connection = Propel::getConnection();
            $lockName = 'churchcrm-upgrade-' . sha1((string) $connection->query('SELECT DATABASE()')->fetchColumn());
            $lock = $connection->prepare('SELECT GET_LOCK(?, 0)');
            $lock->execute([$lockName]);
            if ((int) $lock->fetchColumn() !== 1) {
                $lockName = null;
                throw new \RuntimeException('A database upgrade is already running. Retry after it finishes.');
            }

            $dbUpdatesFile = file_get_contents(SystemURLs::getDocumentRoot() . '/mysql/upgrade.json');
            MiscUtils::throwIfFailed($dbUpdatesFile);

            $dbUpdates = json_decode($dbUpdatesFile, true, 512);

            $upgradeScriptsExecuted = 0;
            foreach ($dbUpdates as $dbUpdate) {
                try {
                    if (in_array(VersionUtils::getDBVersion(), $dbUpdate['versions'])) {
                        $version = new Version();
                        $version->setVersion($dbUpdate['dbVersion']);
                        $version->setUpdateStart(new \DateTimeImmutable());

                        $logger->info('New Version: ' . $version->getVersion());
                        $scriptName = null;
                        foreach ($dbUpdate['scripts'] as $dbScript) {
                            // Legacy string entries deliberately default to unsafe on retry.
                            $scriptPath = is_string($dbScript) ? $dbScript : $dbScript['path'];
                            $retryable = is_array($dbScript) && ($dbScript['retryable'] ?? false) === true;
                            $scriptName = SystemURLs::getDocumentRoot() . $scriptPath;
                            $progressKey = 'dbUpgrade.' . sha1($dbUpdate['dbVersion'] . ':' . $scriptPath);
                            $progress = ConfigQuery::create()->findOneByName($progressKey);
                            if ($progress !== null && $progress->getValue() === 'complete') {
                                $logger->info('Skipping completed upgrade file: ' . $scriptPath);
                                continue;
                            }
                            if ($progress !== null && !$retryable) {
                                throw new \RuntimeException(
                                    "Upgrade file $scriptPath was interrupted and is not safe to retry. "
                                    . 'Restore a database backup or have an administrator repair this migration before continuing.'
                                );
                            }
                            if ($progress === null) {
                                $progress = new Config();
                                $progress->setName($progressKey);
                            }
                            // Commit this before executing: DDL can commit even if the request dies.
                            $progress->setValue('started');
                            $progress->save($connection);

                            $logger->info('Upgrade DB - ' . $scriptName);
                            if (pathinfo($scriptName, PATHINFO_EXTENSION) === 'sql') {
                                SQLUtils::sqlImport($scriptName, $connection);
                            } elseif (pathinfo($scriptName, PATHINFO_EXTENSION) === 'php') {
                                require $scriptName;
                            } else {
                                throw new \Exception("Invalid upgrade file specified: $scriptName");
                            }
                            $progress->setValue('complete');
                            $progress->save($connection);
                        }
                        $version->setUpdateEnd(new \DateTimeImmutable());
                        $version->save();

                        // increment the number of scripts executed.
                        // If no scripts run, then there is no supported upgrade path defined in the JSON file
                        $upgradeScriptsExecuted++;
                    }
                } catch (\Throwable $exc) {
                    $logger->error(
                        'Failure executing upgrade script(s): ' . $exc->getMessage(),
                        [
                            'exception'                 => $exc,
                            'scriptName'                => $scriptName,
                            'version'                   => $version->getVersion(),
                            'numUpgradeScriptsExecuted' => $upgradeScriptsExecuted,
                        ]
                    );

                    throw $exc;
                }
            }

            if ($upgradeScriptsExecuted === 0) {
                $logger->warning('No upgrade path for ' . VersionUtils::getDBVersion() . ' to ' . $installed_version);
            }

            // Mark session to avoid immediate redirect loop while bootstrapper re-reads DB version.
            // Also clear update-notification session variables so the notification disappears
            // immediately after upgrade without requiring the user to log out and back in.
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            try {
                $_SESSION['dbUpgradeJustRan'] = true;
                $_SESSION['systemUpdateAvailable'] = false;
                $_SESSION['systemUpdateVersion'] = null;
                $_SESSION['systemLatestVersion'] = null;
                unset($_SESSION['ChurchCRMReleases']);
                unset($_SESSION['ChurchCRMAllStableReleases']);
            } catch (\Exception $e) {
                // ignore session write failures - not critical
            }

            return true;
        } catch (\Throwable $exc) {
            $logger->error(
                'Database upgrade failed: ' . $exc->getMessage(),
                ['exception' => $exc]
            );

            throw $exc; //allow the method requesting the upgrade to handle this failure also.
        } finally {
            if ($lockName !== null) {
                $release = $connection->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$lockName]);
            }
        }
    }
}
