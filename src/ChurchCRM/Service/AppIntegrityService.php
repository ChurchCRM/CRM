<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\Prerequisite;
use ChurchCRM\Utils\LoggerUtils;

class AppIntegrityService
{
  private static $IntegrityCheckDetails;

  private static function getIntegrityCheckData() {
    $integrityCheckFile = SystemURLs::getDocumentRoot().'/integrityCheck.json';
    if (is_null(AppIntegrityService::$IntegrityCheckDetails))
    {
      LoggerUtils::getAppLogger()->debug('Integrity check results not cached; reloading from file');
      if (file_exists($integrityCheckFile)) {
        LoggerUtils::getAppLogger()->info('Integrity check result file found at: ' . $integrityCheckFile);
        AppIntegrityService::$IntegrityCheckDetails = json_decode(file_get_contents($integrityCheckFile));
        if (is_null(AppIntegrityService::$IntegrityCheckDetails))
        {
          LoggerUtils::getAppLogger()->warning("Error decoding integrity check result file: " . $integrityCheckFile);
          AppIntegrityService::$IntegrityCheckDetails->status = 'failure';
          AppIntegrityService::$IntegrityCheckDetails->message = gettext("Error decoding integrity check result file");
        }
      } else {
        LoggerUtils::getAppLogger()->debug('Integrity check result file not found at: ' . $integrityCheckFile);
        AppIntegrityService::$IntegrityCheckDetails = new \StdClass;
        AppIntegrityService::$IntegrityCheckDetails->status = 'failure';
        AppIntegrityService::$IntegrityCheckDetails->message = gettext("integrityCheck.json file missing");
      }
    }
    else {
      LoggerUtils::getAppLogger()->debug('Integrity check results already cached; not reloading from file');
    }

    return AppIntegrityService::$IntegrityCheckDetails;

  }

  public static function getIntegrityCheckStatus () {
    if (AppIntegrityService::getIntegrityCheckData()->status == "failure")
    {
      return gettext("Failed");
    }
    else {
      return gettext("Passed");
    }
  }

  public static function getIntegrityCheckMessage() {
    if (AppIntegrityService::getIntegrityCheckData()->status != "failure")
    {
      AppIntegrityService::$IntegrityCheckDetails->message = gettext('The previous integrity check passed.  All system file hashes match the expected values.');
    }

    return AppIntegrityService::$IntegrityCheckDetails->message;

  }

  public static function getFilesFailingIntegrityCheck() {
    if (isset(AppIntegrityService::getIntegrityCheckData()->files)) {
    return AppIntegrityService::getIntegrityCheckData()->files;
    }
    else{
      return @[];
    }
  }
  public static function verifyApplicationIntegrity()
  {
    $signatureFile = SystemURLs::getDocumentRoot() . '/signatures.json';
    $signatureFailures = [];
    if (file_exists($signatureFile)) {
      LoggerUtils::getAppLogger()->info('Signature file found at: ' . $signatureFile);
      $signatureData = json_decode(file_get_contents($signatureFile));
      if (is_null($signatureData)){
        LoggerUtils::getAppLogger()->warning('Error decoding signature definition file: ' . $signatureFile);
        return ['status' => 'failure', 'message' => gettext('Error decoding signature definition file')];
      }
      if (sha1(json_encode($signatureData->files, JSON_UNESCAPED_SLASHES)) == $signatureData->sha1) {
        foreach ($signatureData->files as $file) {
          $currentFile = SystemURLs::getDocumentRoot() . '/' . $file->filename;
          if (file_exists($currentFile)) {
            $actualHash = sha1_file($currentFile);
            if ($actualHash != $file->sha1) {
              LoggerUtils::getAppLogger()->warning('File hash mismatch: ' . $file->filename . ". Expected: " . $file->sha1. "; Got: " . $actualHash);
              array_push($signatureFailures, ['filename' => $file->filename, 'status' => 'Hash Mismatch', 'expectedhash' => $file->sha1, 'actualhash' => $actualHash]);
            }
          } else {
            LoggerUtils::getAppLogger()->warning('File Missing: ' . $file->filename);
            array_push($signatureFailures, ['filename' => $file->filename, 'status' => 'File Missing']);
          }
        }
      } else {
        LoggerUtils::getAppLogger()->warning('Signature definition file signature failed validation');
        return ['status' => 'failure', 'message' => gettext('Signature definition file signature failed validation')];
      }
    } else {
      LoggerUtils::getAppLogger()->warning('Signature definition file not found at: ' . $signatureFile);
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
      new Prerequisite('PHP 7.3+ or PHP 7.4+', function() { return version_compare(PHP_VERSION, '7.3.0', '>=') && version_compare(PHP_VERSION, '8.0.0', '<'); }),
      new Prerequisite('PCRE and UTF-8 Support', function() { return function_exists('preg_match') && @preg_match('/^.$/u', 'ñ') && @preg_match('/^\pL$/u', 'ñ'); }),
      new Prerequisite('Multibyte Encoding', function() { return extension_loaded('mbstring'); }),
      new Prerequisite('PHP Phar', function() { return extension_loaded('phar'); }),
      new Prerequisite('PHP Session', function() { return extension_loaded('session'); }),
      new Prerequisite('PHP XML', function() { return extension_loaded('xml'); }),
      new Prerequisite('PHP EXIF', function() { return extension_loaded('exif'); }),
      new Prerequisite('PHP iconv', function() { return extension_loaded('iconv'); }),
      new Prerequisite('Mod Rewrite or Equivalent', function() { return AppIntegrityService::hasModRewrite(); }),
      new Prerequisite('GD Library for image manipulation', function() { return (extension_loaded('gd') && function_exists('gd_info')); }),
      new Prerequisite('FreeType Library', function() { return function_exists('imagettftext'); }),
      new Prerequisite('FileInfo Extension for image manipulation', function() { return extension_loaded('fileinfo'); }),
      new Prerequisite('cURL', function() { return function_exists('curl_version'); }),
      new Prerequisite('locale gettext', function() { return (function_exists('bindtextdomain') && function_exists("gettext")); }),
      new Prerequisite('Include/Config file is writeable', function() { return is_writable(SystemURLs::getDocumentRoot().'/Include/') || is_writable(SystemURLs::getDocumentRoot().'/Include/Config.php'); }),
      new Prerequisite('Images directory is writeable', function() { return AppIntegrityService::testImagesWriteable(); }),
      new Prerequisite('PHP ZipArchive', function() { return extension_loaded('zip'); }),
      new Prerequisite('Mysqli Functions', function() { return function_exists('mysqli_connect'); })
    );

    return $prerequisites;
  }

  public static function getUnmetPrerequisites()
  {
    return array_filter(AppIntegrityService::getApplicationPrerequisites(), function ($prereq) {
      return ! $prereq->IsPrerequisiteMet();
    });
  }

  public static function arePrerequisitesMet()
  {
    return count(AppIntegrityService::getUnmetPrerequisites()) === 0;
  }

  public static function hasApacheModule($module)
  {
      if (function_exists('apache_get_modules')) {
          LoggerUtils::getAppLogger()->debug("looking for apache module $module using PHP's apache_get_modules");
          return in_array($module, apache_get_modules());
      }
      return false;
  }

  public static function hasModRewrite()
  {
    // mod_rewrite can be tricky to detect properly.
    // First check if it's loaded as an apache module
    // Second check (if supported) if apache cli lists the module
    // Third, finally try calling a known invalid URL on this installation
    //   and check for a header that would only be present if .htaccess was processed.
    //   This header comes from index.php (which is the target of .htaccess for invalid URLs)

    $check = false;
    $logger = LoggerUtils::getAppLogger();

    if (stristr($_SERVER["SERVER_SOFTWARE"],"apache") != false) {
      $logger->debug("PHP is running through Apache; look for mod_rewrite");
      $check = AppIntegrityService::hasApacheModule('mod_rewrite');
      $logger->debug("Apache mod_rewrite check status: $check");
    }
    else {
      $logger->debug("PHP is not running through Apache");
    }

    if ($check == false){
      $logger->debug("Previous rewrite checks failed");
      if ( function_exists('curl_version')) {
          $ch = curl_init();
          $request_url_parser = parse_url($_SERVER['HTTP_REFERER']);
          $request_scheme = isset($request_url_parser['scheme']) ? $request_url_parser['scheme'] : 'http';
          $rewrite_chk_url = $request_scheme ."://". $_SERVER['SERVER_ADDR'] . SystemURLs::getRootPath()."/INVALID";
          $logger->debug("Testing CURL loopback check to: $rewrite_chk_url");
          curl_setopt($ch, CURLOPT_URL, $rewrite_chk_url);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_HEADER, 1);
          curl_setopt($ch, CURLOPT_NOBODY, 1);
          $output = curl_exec($ch);
          curl_close($ch);
          $headers=array();
          $data=explode("\n",$output);
          $headers['status']=$data[0];
          array_shift($data);
          foreach($data as $part){
              if (strpos($part, ":"))
              {
                $middle=explode(":",$part);
                $headers[trim($middle[0])] = trim($middle[1]);
              }
          }
          $check =  $headers['CRM'] == "would redirect";
          $logger->debug("CURL loopback check headers observed: ".($check?'true':'false'));
        }
      }

      return $check;
  }
}
?>
