<?php

/**
 * Created by PhpStorm.
 * User: georg
 * Date: 11/25/2017
 * Time: 1:28 PM.
 */

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Version;
use ChurchCRM\Utils\SQLUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\VersionUtils;
use ChurchCRM\Utils\SQLImportException;
use ChurchCRM\Service\TelemetryService;
use Propel\Runtime\Propel;

class UpgradeService
{
    public static function upgradeDatabaseVersion(?bool $oneScriptPerRequest = null): bool
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

        // An older release manager may still be loaded during deployment. Its
        // no-argument call must leave database work for the next HTTP request.
        if ($oneScriptPerRequest === null && PHP_SAPI !== 'cli') {
            $_SESSION['dbUpgradePending'] = true;
            return false;
        }
        $oneScriptPerRequest ??= false;

        //the database isn't at the current version.  Start the upgrade
        $lockName = null;
        try {
            $connection = Propel::getConnection();
            $lockName = 'churchcrm-upgrade-' . sha1((string) $connection->query('SELECT DATABASE()')->fetchColumn());
            $lock = $connection->prepare('SELECT GET_LOCK(?, 0)');
            $lock->execute([$lockName]);
            if ((int) $lock->fetchColumn() !== 1) {
                $lockName = null;
                throw new \RuntimeException('A database upgrade is already running. Try again shortly.', 409);
            }
            // This journal must work before legacy application tables are migrated.
            $progressRepository = new UpgradeProgressRepository($connection);
            $progressRepository->ensureTable();

            $dbUpdatesFile = file_get_contents(SystemURLs::getDocumentRoot() . '/mysql/upgrade.json');
            MiscUtils::throwIfFailed($dbUpdatesFile);

            $dbUpdates = json_decode($dbUpdatesFile, true, 512);

            $scriptsThisRequest = 0;
            $upgradeScriptsExecuted = 0;
            foreach ($dbUpdates as $dbUpdate) {
                $scriptName = null;
                $version = null;
                try {
                    if (in_array(VersionUtils::getDBVersion(), $dbUpdate['versions'])) {
                        $version = new Version();
                        $version->setVersion($dbUpdate['dbVersion']);
                        $version->setUpdateStart(new \DateTimeImmutable());

                        $logger->info('New Version: ' . $version->getVersion());
                        $scriptName = null;
                        foreach ($dbUpdate['scripts'] as $dbScript) {
                            $progressRow = $progressRepository->find($dbUpdate['dbVersion'], $dbScript);
                            $status = $progressRow['status'] ?? null;
                            if ($status === 'complete') {
                                continue;
                            }
                            if ($status !== null) {
                                if ($status === 'started') {
                                    $wasStarted = $progressRepository->setStatus($dbUpdate['dbVersion'], $dbScript, 'reported', 'started');
                                    $startedAt = new \DateTimeImmutable($progressRow['started_at']);
                                    $elapsedMs = max(0, (time() - $startedAt->getTimestamp()) * 1000);
                                    if ($wasStarted) {
                                        TelemetryService::captureUpgradeInterruption($dbUpdate['dbVersion'], $elapsedMs);
                                    }
                                }
                                throw new \RuntimeException("Upgrade file $dbScript failed or was interrupted. Check the server logs and repair or restore the database before retrying.");
                            }
                            if ($oneScriptPerRequest && $scriptsThisRequest === 1) {
                                return false;
                            }
                            $progressRepository->start($dbUpdate['dbVersion'], $dbScript, date('Y-m-d H:i:s'));
                            $scriptName = SystemURLs::getDocumentRoot() . $dbScript;

                            $logger->info('Upgrade DB - ' . $scriptName);
                            $sqlDurationMs = null;
                            if (pathinfo($scriptName, PATHINFO_EXTENSION) === 'sql') {
                                $sqlStartedAt = hrtime(true);
                                try {
                                    SQLUtils::sqlImport($scriptName, $connection);
                                    $sqlDurationMs = (int) ((hrtime(true) - $sqlStartedAt) / 1_000_000);
                                } catch (\Throwable $e) {
                                    $durationMs = (int) ((hrtime(true) - $sqlStartedAt) / 1_000_000);
                                    $progressRepository->setStatus($dbUpdate['dbVersion'], $dbScript, 'reported', 'started');
                                    if ($e instanceof SQLImportException) {
                                        TelemetryService::captureUpgradeSqlError($dbUpdate['dbVersion'], $durationMs, $e->getDatabaseError());
                                    } else {
                                        TelemetryService::captureUpgradeSqlDuration($dbUpdate['dbVersion'], $durationMs, false);
                                    }
                                    throw $e;
                                }
                            } elseif (pathinfo($scriptName, PATHINFO_EXTENSION) === 'php') {
                                require $scriptName;
                            } else {
                                throw new \Exception("Invalid upgrade file specified: $scriptName");
                            }
                            $progressRepository->setStatus($dbUpdate['dbVersion'], $dbScript, 'complete');
                            if ($sqlDurationMs !== null) {
                                TelemetryService::captureUpgradeSqlDuration($dbUpdate['dbVersion'], $sqlDurationMs, true);
                            }
                            $scriptsThisRequest++;
                        }
                        $startedAt = $progressRepository->earliestStart($dbUpdate['dbVersion']);
                        if ($startedAt) {
                            $version->setUpdateStart(new \DateTimeImmutable($startedAt));
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
                            'version'                   => $version?->getVersion(),
                            'numUpgradeScriptsExecuted' => $upgradeScriptsExecuted,
                        ]
                    );

                    throw $exc;
                }
            }

            if ($upgradeScriptsExecuted === 0) {
                throw new \RuntimeException('No upgrade path for ' . VersionUtils::getDBVersion() . ' to ' . $installed_version);
            }

            if (VersionUtils::getDBVersion() !== $installed_version) {
                return false;
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
            if (!$exc instanceof SQLImportException && $exc->getCode() !== 409) {
                TelemetryService::captureUpgradeFailure($installed_version, $exc::class);
            }
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
