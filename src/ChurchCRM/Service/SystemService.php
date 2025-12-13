<?php

namespace ChurchCRM\Service;

use ChurchCRM\Backup\BackupJob;
use ChurchCRM\Backup\BackupType;
use ChurchCRM\dto\Prerequisite;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\LoggerUtils;
use PDO;
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

        return 'Missing Prerequisites: ' . json_encode(array_values($unmetNames), JSON_THROW_ON_ERROR);
    }

    private static function isTimerThresholdExceeded(string $LastTime, int $ThresholdHours): bool
    {
        if (empty($LastTime)) {
            return true;
        }
        $tz = new \DateTimeZone(SystemConfig::getValue('sTimeZone'));
        $now = new \DateTime('now', $tz);  //get the current time
        $previous = \DateTime::createFromFormat(SystemConfig::getValue('sDateFilenameFormat'), $LastTime, $tz); // get a DateTime object for the last time a backup was done.
        if ($previous === false) {
            return true;
        }
        $diff = abs($now->getTimestamp() - $previous->getTimestamp()) / 60 / 60;

        return $diff >= $ThresholdHours;
    }

    public static function runTimerJobs(): void
    {
        LoggerUtils::getAppLogger()->debug('Starting background job processing');
        //start the external backup timer job
        if (SystemConfig::getBooleanValue('bEnableExternalBackupTarget') && SystemConfig::getValue('sExternalBackupAutoInterval') > 0) {  //if remote backups are enabled, and the interval is greater than zero
            try {
                if (self::isTimerThresholdExceeded(SystemConfig::getValue('sLastBackupTimeStamp'), SystemConfig::getValue('sExternalBackupAutoInterval'))) {
                    // if there was no previous backup, or if the interval suggests we do a backup now.
                    LoggerUtils::getAppLogger()->info('Starting a backup job.  Last backup run: ' . SystemConfig::getValue('sLastBackupTimeStamp'));
                    $BaseName = preg_replace('/[^a-zA-Z0-9\-_]/', '', SystemConfig::getValue('sChurchName')) . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));
                    $Backup = new BackupJob($BaseName, BackupType::FULL_BACKUP, false, '');
                    $Backup->execute();
                    $Backup->copyToWebDAV(SystemConfig::getValue('sExternalBackupEndpoint'), SystemConfig::getValue('sExternalBackupUsername'), SystemConfig::getValue('sExternalBackupPassword'));
                    $now = new \DateTime();  // update the LastBackupTimeStamp.
                    SystemConfig::setValue('sLastBackupTimeStamp', $now->format(SystemConfig::getValue('sDateFilenameFormat')));
                    LoggerUtils::getAppLogger()->info('Backup job successful');
                } else {
                    LoggerUtils::getAppLogger()->info('Not starting a backup job.  Last backup run: ' . SystemConfig::getValue('sLastBackupTimeStamp') . '.');
                }
            } catch (\Exception $exc) {
                // an error in the auto-backup shouldn't prevent the page from loading...
                LoggerUtils::getAppLogger()->warning('Failure executing backup job: ' . $exc->getMessage());
            }
        }

        LoggerUtils::getAppLogger()->debug('Finished background job processing');
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
