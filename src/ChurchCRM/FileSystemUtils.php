<?php

namespace ChurchCRM;

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
            $msg = 'provided src path is not a directory: ' . $src;
            $logger->error($msg);

            throw new \Exception($msg);
        }
        if (!is_dir($dest)) {
            $msg = 'provided dest path is not a directory: ' . $dest;
            $logger->error($msg);

            throw new \Exception($msg);
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
}
