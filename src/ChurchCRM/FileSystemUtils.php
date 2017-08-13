<?php

namespace ChurchCRM
{
  class FileSystemUtils
  {
      public static function recursiveRemoveDirectory($directory, $retainParentFolderAndFiles=false)
      {
          //sourced from http://stackoverflow.com/questions/11267086/php-unlink-all-files-within-a-directory-and-then-deleting-that-directory
          foreach (glob("{$directory}/*") as $file) {
              if (is_dir($file)) {
                  self::recursiveRemoveDirectory($file, false);
              } elseif (!$retainParentFolderAndFiles) {
                  unlink($file);
              }
          }
          if (!$retainParentFolderAndFiles) {
              rmdir($directory);
          }
      }

      public static function recursiveCopyDirectory($src, $dst)
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
  }
}
