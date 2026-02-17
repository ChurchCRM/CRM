<?php

namespace ChurchCRM\Utils;

use ChurchCRM\Utils\LoggerUtils;

class FileSystemUtils
{
    public static function recursiveRemoveDirectory($directory, $retainParentFolderAndFiles = false): void
    {
        //sourced from http://stackoverflow.com/questions/11267086/php-unlink-all-files-within-a-directory-and-then-deleting-that-directory
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                self::recursiveRemoveDirectory($file, false);
            } elseif (!$retainParentFolderAndFiles) {
                unlink($file);
            }
        }
        if (!$retainParentFolderAndFiles && is_dir($directory)) {
            rmdir($directory);
        }
    }

    public static function recursiveCopyDirectory($src, $dst): void
    {
        //sourced from http://stackoverflow.com/questions/9835492/move-all-files-and-folders-in-a-folder-to-another
        // Function to Copy folders and files
        if (file_exists($dst)) {
            self::recursiveRemoveDirectory($dst);
        }
        if (is_dir($src)) {
            mkdir($dst);
            $files = scandir($src);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    self::recursiveCopyDirectory("$src/$file", "$dst/$file");
                }
            }
        } elseif (file_exists($src)) {
            copy($src, $dst);
        }
    }

    public static function deleteFiles(string $path, $extArray): void
    {
        foreach ($extArray as $ext) {
            LoggerUtils::getAppLogger()->info('Deleting files: ' . $path . '*.' . $ext);
            foreach (glob($path . '*.' . $ext) as $filename) {
                unlink($filename);
            }
        }
    }

    public static function moveDir(string $src, string $dest): bool
    {
        $logger = LoggerUtils::getAppLogger();

        if (!is_dir($src)) {
            $logger->error('moveDir: Source directory does not exist', [
                'src' => $src,
                'dest' => $dest,
                'srcExists' => file_exists($src),
                'srcIsDir' => is_dir($src),
            ]);

            throw new \Exception(gettext('Source directory does not exist. The upgrade archive may not have extracted correctly. Please check file permissions and disk space.'));
        }
        if (!is_dir($dest)) {
            $logger->error('moveDir: Destination directory does not exist', [
                'src' => $src,
                'dest' => $dest,
                'destExists' => file_exists($dest),
                'destIsDir' => is_dir($dest),
            ]);

            throw new \Exception(gettext('Destination directory does not exist. Please verify ChurchCRM is installed correctly.'));
        }

        $logger->info('Moving files: ' . $src . ' to ' . $dest);
        $files = array_diff(scandir($src), ['.', '..']);
        foreach ($files as $file) {
            if (is_dir("$src/$file")) {
                mkdir("$dest/$file");
                self::moveDir("$src/$file", "$dest/$file");
            } else {
                rename("$src/$file", "$dest/$file");
            }
        }

        return rmdir($src);
    }

    /**
     * Copy a single file, creating destination directory if needed.
     * Returns true on success, false on failure (logs details).
     */
    public static function copyFile(string $src, string $dst): bool
    {
        $logger = LoggerUtils::getAppLogger();

        if (!is_file($src)) {
            $logger->warning('copyFile: source file not found', ['src' => $src]);
            return false;
        }

        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) {
            if (!mkdir($dstDir, 0755, true)) {
                $logger->error('copyFile: failed to create destination directory', ['dir' => $dstDir]);
                return false;
            }
        }

        if (!copy($src, $dst)) {
            $logger->error('copyFile: failed to copy file', ['src' => $src, 'dst' => $dst]);
            return false;
        }

        $logger->info('copyFile: copied file', ['src' => $src, 'dst' => $dst]);
        return true;
    }
}
