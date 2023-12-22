<?php

namespace ChurchCRM\Backup;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\Utils\LoggerUtils;

class BackupDownloader
{
    public static function downloadBackup(string $filename): void
    {
        if ($filename === '') {
            $message = 'filename must be non-empty: ' . $filename;
            LoggerUtils::getAppLogger()->error($message);

            throw new \Exception($message, 500);
        }

        $path = SystemURLs::getDocumentRoot() . "/tmp_attach/ChurchCRMBackups/$filename";
        LoggerUtils::getAppLogger()->info('Download requested for :' . $path);
        if (!file_exists($path)) {
            $message = 'Requested download does not exist: ' . $path;
            LoggerUtils::getAppLogger()->error($message);

            throw new \Exception($message, 500);
        }

        if ($fd = fopen($path, 'r')) {
            $fsize = filesize($path);
            $path_parts = pathinfo($path);
            $ext = strtolower($path_parts['extension']);
            switch ($ext) {
                case 'gz':
                    header('Content-type: application/x-gzip');
                    header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                    break;
                case 'tar.gz':
                    header('Content-type: application/x-gzip');
                    header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                    break;
                case 'sql':
                    header('Content-type: text/plain');
                    header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                    break;
                case 'gpg':
                    header('Content-type: application/pgp-encrypted');
                    header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                    break;
                case 'zip':
                    header('Content-type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $path_parts['basename'] . '"');
                    break;
                    // add more headers for other content types here
                default:
                    header('Content-type: application/octet-stream');
                    header('Content-Disposition: filename="' . $path_parts['basename'] . '"');
                    break;
            }
            header("Content-length: $fsize");
            header('Cache-control: private'); //use this to open files directly
            LoggerUtils::getAppLogger()->debug('Headers sent. sending backup file contents');
            while (!feof($fd)) {
                $buffer = fread($fd, 2048);
                echo $buffer;
            }
            LoggerUtils::getAppLogger()->debug('Backup file contents sent');
        }
        fclose($fd);

        FileSystemUtils::recursiveRemoveDirectory(SystemURLs::getDocumentRoot() . '/tmp_attach/', true);
        LoggerUtils::getAppLogger()->debug('Removed backup file from server filesystem');
    }
}
