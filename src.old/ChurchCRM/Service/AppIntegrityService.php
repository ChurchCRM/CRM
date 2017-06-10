<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;

class AppIntegrityService
{
  public static function verifyApplicationIntegrity()
  {
    $signatureFile = SystemURLs::getDocumentRoot() . '/signatures.json';
    $signatureFailures = [];
    if (file_exists($signatureFile)) {
      $signatureData = json_decode(file_get_contents($signatureFile));
      if (sha1(json_encode($signatureData->files, JSON_UNESCAPED_SLASHES)) == $signatureData->sha1) {
        foreach ($signatureData->files as $file) {
          $currentFile = SystemURLs::getDocumentRoot() . '/' . $file->filename;
          if (file_exists($currentFile)) {
            $actualHash = sha1_file($currentFile);
            if ($actualHash != $file->sha1) {
              array_push($signatureFailures, ['filename' => $file->filename, 'status' => 'Hash Mismatch', 'expectedhash' => $file->sha1, 'actualhash' => $actualHash]);
            }
          } else {
            array_push($signatureFailures, ['filename' => $file->filename, 'status' => 'File Missing']);
          }
        }
      } else {
        return ['status' => 'failure', 'message' => gettext('Signature definition file signature failed validation')];
      }
    } else {
      return ['status' => 'failure', 'message' => gettext('Signature definition File Missing')];
    }

    if (count($signatureFailures) > 0) {
      return ['status' => 'failure', 'message' => gettext('One or more files failed signature validation'), 'files' => $signatureFailures];
    } else {
      return ['status' => 'success'];
    }
  }
  
  private static function testImagesWriteable()
  {
    return is_writable(SystemURLs::getDocumentRoot().'/Images/') && 
            is_writable(SystemURLs::getDocumentRoot().'/Images/Family') &&
            is_writable(SystemURLs::getDocumentRoot().'/Images/Person');

  }

  public static function getApplicationPrerequisites()
  {
    $prerequisites = array(
      'PHP 7.0+'                                  => version_compare(PHP_VERSION, '7.0.0', '>='),
      'PCRE and UTF-8 Support'                    => function_exists('preg_match') && @preg_match('/^.$/u', 'ñ') && @preg_match('/^\pL$/u', 'ñ'),
      'Multibyte Encoding'                        => extension_loaded('mbstring'),
      'PHP Phar'                                  => extension_loaded('phar'),
      'PHP Session'                               => extension_loaded('session'),
      'PHP XML'                                   => extension_loaded('xml'),
      'PHP EXIF'                                  => extension_loaded('exif'),
      'PHP iconv'                                 => extension_loaded('iconv'),
      'Mcrypt'                                    => extension_loaded('mcrypt'),
      'Mod Rewrite'                               => AppIntegrityService::hasModRewrite('mod_rewrite'),
      'GD Library for image manipulation'         => (extension_loaded('gd') && function_exists('gd_info')),
      'FileInfo Extension for image manipulation' => extension_loaded('fileinfo'),
      'cURL'                                      => function_exists('curl_version'),
      'locale gettext'                            => function_exists('bindtextdomain'),
      'Include/Config file is writeable'          => is_writable(SystemURLs::getDocumentRoot().'/Include/') || is_writable(SystemURLs::getDocumentRoot().'/Include/Config.php'),
      'Images directory is writeable'             => AppIntegrityService::testImagesWriteable()
    );
    return $prerequisites;
  }
  
  public static function getUnmetPrerequisites()
  {
    $unmet = [];
    foreach (AppIntegrityService::getApplicationPrerequisites() as $prerequisite=>$status) {
          if (!$status) {
              array_push($unmet,$prerequisite);
          }
      }
    return $unmet;
  }

  public static function arePrerequisitesMet()
  {
    $prerequisites = AppIntegrityService::getApplicationPrerequisites();
    foreach ($prerequisites as $prerequisiteName => $prerequisiteMet)
    {
      if (!$prerequisiteMet)
      {
        return false;
      }
    }
    return true;
  }

  public static function hasApacheModule($module)
  {
      if (function_exists('apache_get_modules')) {
          return in_array($module, apache_get_modules());
      }

      return false;
  }

  public static function hasModRewrite()
  {
      $check = AppIntegrityService::hasApacheModule('mod_rewrite');

      if (!$check && function_exists('shell_exec')) {
          $check = strpos(shell_exec('/usr/local/apache/bin/apachectl -l'), 'mod_rewrite') !== false;
      }

      return $check;
  }

}

?>
