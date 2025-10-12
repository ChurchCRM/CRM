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
        
        // Get list of files in source and destination
        $sourceFiles = array_diff(scandir($src), ['.', '..']);
        $destFiles = array_diff(scandir($dest), ['.', '..']);
        
        // Remove files/directories in destination that don't exist in source
        foreach ($destFiles as $file) {
            if (!in_array($file, $sourceFiles)) {
                $destPath = "$dest/$file";
                
                // Skip removal of user data files that should be preserved during upgrade
                if (self::shouldPreserveFile($destPath)) {
                    $logger->info('Preserving user data file during upgrade: ' . $destPath);
                    continue;
                }
                
                if (is_dir($destPath)) {
                    $logger->info('Removing directory not in new release: ' . $destPath);
                    self::recursiveRemoveDirectory($destPath);
                } else {
                    $logger->info('Removing file not in new release: ' . $destPath);
                    unlink($destPath);
                }
            }
        }
        
        // Move files from source to destination
        foreach ($sourceFiles as $file) {
            if (is_dir("$src/$file")) {
                if (!is_dir("$dest/$file")) {
                    mkdir("$dest/$file");
                }
                self::moveDir("$src/$file", "$dest/$file");
            } else {
                rename("$src/$file", "$dest/$file");
            }
        }

        return rmdir($src);
    }
    
    private static function shouldPreserveFile(string $path): bool
    {
        // Preserve user-uploaded images in Family and Person directories
        // Pattern matches: **/Images/Family/**/*.{jpg,jpeg,png} and **/Images/Person/**/*.{jpg,jpeg,png}
        if (preg_match('#/Images/(Family|Person)(/.*)?/[^/]+\.(jpg|jpeg|png)$#i', $path)) {
            return true;
        }
        
        // Preserve configuration files
        if (preg_match('#/Include/Config\.php$#', $path)) {
            return true;
        }
        
        // Preserve composer.lock
        if (preg_match('#/composer\.lock$#', $path)) {
            return true;
        }
        
        return false;
    }
}
