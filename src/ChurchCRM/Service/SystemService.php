<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Prerequisite;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use PDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;

require SystemURLs::getDocumentRoot() . '/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php';

class SystemService
{
    public static function getCopyrightDate(): string
    {
        return (new \DateTime())->format('Y');
    }

    public function getConfigurationSetting($settingName, $settingValue): void
    {
        AuthService::requireUserGroupMembership('bAdmin');
    }

    public function setConfigurationSetting($settingName, $settingValue): void
    {
        AuthService::requireUserGroupMembership('bAdmin');
    }



    public static function getDBServerVersion()
    {
        try {
            return Propel::getServiceContainer()->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (\Exception $exc) {
            return 'Could not obtain DB Server Version';
        }
    }

    public static function getPrerequisiteStatus(): string
    {
        if (AppIntegrityService::arePrerequisitesMet()) {
            return 'All Prerequisites met';
        }

        $unmet = AppIntegrityService::getUnmetPrerequisites();

        $unmetNames = array_map(fn (Prerequisite $o): string => $o->getName(), $unmet);

        return 'Missing Prerequisites: ' . json_encode(array_values($unmetNames));
    }

    private static function isTimerThresholdExceeded(string $LastTime, int $ThresholdHours): bool
    {
        if (empty($LastTime)) {
            return true;
        }
        $tz = DateTimeUtils::getConfiguredTimezone();
        $now = new \DateTime('now', $tz);  //get the current time
        $previous = \DateTime::createFromFormat(SystemConfig::getValue('sDateFilenameFormat'), $LastTime, $tz); // get a DateTime object for the last time a backup was done.
        if ($previous === false) {
            return true;
        }
        $diff = abs($now->getTimestamp() - $previous->getTimestamp()) / 60 / 60;

        return $diff >= $ThresholdHours;
    }

    /**
     * Config key holding the moment the timer jobs last completed.
     */
    public const TIMER_JOBS_LAST_RUN_CONFIG = 'sLastTimerJobsRunDateTime';

    /**
     * Storage format for TIMER_JOBS_LAST_RUN_CONFIG. Sorts lexicographically in
     * the same order it sorts chronologically, which is what lets the rate-limit
     * claim below be a single conditional UPDATE.
     */
    private const TIMER_JOBS_TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    /**
     * Run the scheduled ("timer") jobs.
     *
     * Two callers reach this (issue #9724):
     *   - `cli/timerjobs.php`, the real scheduler entry point, which passes
     *     $force = true because the administrator's crontab decides the cadence;
     *   - `POST /api/background/timerjobs`, fired from the page footer on every
     *     authenticated page load. That fallback exists for installs with no
     *     cron access, and it is rate limited here so a busy Sunday morning
     *     runs the jobs once, not once per page view.
     *
     * @param bool $force Skip the rate limit (command-line / explicit runs)
     *
     * @return bool true if the jobs ran, false if the rate limit skipped them
     */
    public static function runTimerJobs(bool $force = false): bool
    {
        if (!self::claimTimerJobsRun($force)) {
            LoggerUtils::getAppLogger()->debug('Skipping background job processing — last run is inside the minimum interval', [
                'minIntervalMinutes' => self::getTimerJobsMinIntervalMinutes(),
                'lastRun' => SystemConfig::getValue(self::TIMER_JOBS_LAST_RUN_CONFIG),
            ]);

            return false;
        }

        LoggerUtils::getAppLogger()->debug('Starting background job processing');
        self::$lastTimerJobFailures = [];

        self::runTimerJob('BirthdayEmailService', static function (): void {
            BirthdayEmailService::run();
        });

        // Fire the CRON_RUN hook so plugins can register scheduled tasks.
        // Each active plugin registers a handler on Hooks::CRON_RUN in boot().
        // HookManager catches and logs any per-plugin errors so one failing
        // plugin cannot block the others.
        self::runTimerJob('CRON_RUN hook', static function (): void {
            HookManager::doAction(Hooks::CRON_RUN);
        });

        LoggerUtils::getAppLogger()->debug('Finished background job processing');

        return true;
    }

    /**
     * Claim the right to run the timer jobs now, recording the run timestamp.
     *
     * The timestamp doubles as the rate-limit token and as the staleness marker
     * the admin dashboard reads, so it is written *before* the jobs run: a job
     * that crashes must not let the next page load retry it immediately, and
     * `runTimerJob()` already isolates individual failures.
     *
     * The claim is a single conditional statement, so the database decides the
     * winner when several page loads arrive together — the same reason
     * BirthdayEmailService claims its day atomically (#9727).
     */
    private static function claimTimerJobsRun(bool $force): bool
    {
        $tz = DateTimeUtils::getConfiguredTimezone();
        $now = new \DateTime('now', $tz);
        $nowString = $now->format(self::TIMER_JOBS_TIMESTAMP_FORMAT);

        $minIntervalMinutes = self::getTimerJobsMinIntervalMinutes();
        if ($force || $minIntervalMinutes <= 0) {
            try {
                SystemConfig::setValue(self::TIMER_JOBS_LAST_RUN_CONFIG, $nowString);
            } catch (PropelException $e) {
                // On the very first run there is no row yet, so this writes one;
                // a concurrent page load inserting the same primary key first
                // makes that INSERT fail. Losing that race says nothing about
                // whether we may run — an unrated run always may — so record the
                // lost claim and carry on rather than failing the cron job.
                LoggerUtils::getAppLogger()->debug('Timer-job run timestamp lost the INSERT race; running anyway', [
                    'forced' => $force,
                    'exception' => $e->getMessage(),
                ]);
            }

            return true;
        }

        $cutoff = (clone $now)->modify("-{$minIntervalMinutes} minutes")->format(self::TIMER_JOBS_TIMESTAMP_FORMAT);

        $existing = ConfigQuery::create()->findOneByName(self::TIMER_JOBS_LAST_RUN_CONFIG);
        if ($existing === null) {
            try {
                $config = new Config();
                $config->setName(self::TIMER_JOBS_LAST_RUN_CONFIG);
                $config->setValue($nowString);
                $config->save();
            } catch (PropelException $e) {
                // Another request inserted the marker between our read and our
                // write; it is running the jobs, so we stand down.
                LoggerUtils::getAppLogger()->debug('Another request claimed the timer-job run first');

                return false;
            }

            return true;
        }

        // Only rows whose recorded run is older than the cutoff are updated, so
        // exactly one concurrent caller sees an affected row.
        $affectedRows = ConfigQuery::create()
            ->filterByName(self::TIMER_JOBS_LAST_RUN_CONFIG)
            ->filterByValue($cutoff, Criteria::LESS_THAN)
            ->update(['Value' => $nowString]);

        return $affectedRows > 0;
    }

    /**
     * Minimum minutes between page-load-triggered timer-job runs (0 disables).
     */
    public static function getTimerJobsMinIntervalMinutes(): int
    {
        return max(0, SystemConfig::getIntValue('iTimerJobsMinIntervalMinutes'));
    }

    /**
     * Hours after which a missing timer-job run is worth warning about
     * (0 disables the warning).
     */
    public static function getTimerJobsStaleHours(): int
    {
        return max(0, SystemConfig::getIntValue('iTimerJobsStaleHours'));
    }

    /**
     * When the timer jobs last ran, or null if they never have.
     *
     * Reads the row directly rather than through the per-request SystemConfig
     * cache, because claimTimerJobsRun() writes it with a direct UPDATE.
     */
    public static function getLastTimerJobsRun(): ?\DateTimeImmutable
    {
        $config = ConfigQuery::create()->findOneByName(self::TIMER_JOBS_LAST_RUN_CONFIG);
        $value = $config?->getValue() ?? '';
        if ($value === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat(
            self::TIMER_JOBS_TIMESTAMP_FORMAT,
            $value,
            DateTimeUtils::getConfiguredTimezone()
        );

        return $parsed === false ? null : $parsed;
    }

    /**
     * Have the timer jobs gone longer than the configured threshold without
     * running? True also when they have never run at all.
     */
    public static function isTimerJobsRunStale(): bool
    {
        $thresholdHours = self::getTimerJobsStaleHours();
        if ($thresholdHours <= 0) {
            return false;
        }

        $lastRun = self::getLastTimerJobsRun();
        if ($lastRun === null) {
            return true;
        }

        $now = new \DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
        $elapsedHours = ($now->getTimestamp() - $lastRun->getTimestamp()) / 3600;

        return $elapsedHours >= $thresholdHours;
    }

    /**
     * Names of the jobs that threw during the most recent runTimerJobs() call.
     *
     * Failures are swallowed so one job cannot stop the others, but the
     * command-line runner still needs to know, so cron can report a bad night.
     *
     * @var string[]
     */
    private static array $lastTimerJobFailures = [];

    /**
     * @return string[] job names that failed during the last runTimerJobs() call
     */
    public static function getLastTimerJobFailures(): array
    {
        return self::$lastTimerJobFailures;
    }

    /**
     * Run a single timer job, logging and swallowing any failure.
     *
     * One job must never be able to stop the jobs that follow it, nor the
     * CRON_RUN hook (issue #9727): before this, an exception in
     * BirthdayEmailService::run() aborted the whole request, so every plugin's
     * scheduled work was silently skipped and the caller got an HTTP 500.
     * This gives the job list the same isolation HookManager already gives
     * individual plugins.
     */
    private static function runTimerJob(string $jobName, callable $job): void
    {
        try {
            $job();
        } catch (\Throwable $e) {
            self::$lastTimerJobFailures[] = $jobName;
            LoggerUtils::getAppLogger()->error('Timer job failed', [
                'job' => $jobName,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    // Returns a file size limit in bytes based on the PHP upload_max_filesize
    // and post_max_size
    public static function getMaxUploadFileSize(bool $humanFormat = true)
    {
        //select maximum upload size
        $max_upload = SystemService::parseSize(ini_get('upload_max_filesize'));
        //select post limit
        $max_post = SystemService::parseSize(ini_get('post_max_size'));
        //select memory limit
        $memory_limit = SystemService::parseSize(ini_get('memory_limit'));
        // return the smallest of them, this defines the real limit
        if ($humanFormat) {
            return SystemService::humanFilesize(min($max_upload, $max_post, $memory_limit));
        } else {
            return min($max_upload, $max_post, $memory_limit);
        }
    }

    private static function parseSize(string $size): float
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($size * 1024 ** stripos('bkmgtpezy', $unit[0]));
        } else {
            return round($size);
        }
    }

    private static function humanFilesize(float $bytes, $decimals = 2): string
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / 1024 ** $factor) . @$size[$factor];
    }
}
