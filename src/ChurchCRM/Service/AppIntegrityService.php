<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;

class AppIntegrityService
{
  private static $IntegrityCheckDetails;

  private static function getIntegrityCheckData() {
    $integrityCheckFile = SystemURLs::getDocumentRoot().'/integrityCheck.json';
    if (is_null(AppIntegrityService::$IntegrityCheckDetails))
    {
      if (file_exists($integrityCheckFile)) {
        AppIntegrityService::$IntegrityCheckDetails = json_decode(file_get_contents($integrityCheckFile));
      } else {
          AppIntegrityService::$IntegrityCheckDetails->status = 'failure';
          AppIntegrityService::$IntegrityCheckDetails->message = gettext("integrityCheck.json file missing");
      }
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
    return AppIntegrityService::getIntegrityCheckData()->files;
  }
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
      new Prerequisite('PHP 7.0+', function() { return version_compare(PHP_VERSION, '7.0.0', '>='); }),
      new Prerequisite('PCRE and UTF-8 Support', function() { return function_exists('preg_match') && @preg_match('/^.$/u', 'ñ') && @preg_match('/^\pL$/u', 'ñ'); }),
      new Prerequisite('Multibyte Encoding', function() { return extension_loaded('mbstring'); }),
      new Prerequisite('PHP Phar', function() { return extension_loaded('phar'); }),
      new Prerequisite('PHP Session', function() { return extension_loaded('session'); }),
      new Prerequisite('PHP XML', function() { return extension_loaded('xml'); }),
      new Prerequisite('PHP EXIF', function() { return extension_loaded('exif'); }),
      new Prerequisite('PHP iconv', function() { return extension_loaded('iconv'); }),
      new Prerequisite('Mcrypt', function() { return extension_loaded('mcrypt'); }),
      new Prerequisite('Mod Rewrite', function() { return AppIntegrityService::hasModRewrite(); }),
      new Prerequisite('GD Library for image manipulation', function() { return (extension_loaded('gd') && function_exists('gd_info')); }),
      new Prerequisite('FileInfo Extension for image manipulation', function() { return extension_loaded('fileinfo'); }),
      new Prerequisite('cURL', function() { return function_exists('curl_version'); }),
      new Prerequisite('locale gettext', function() { return function_exists('bindtextdomain'); }),
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

    $check = AppIntegrityService::hasApacheModule('mod_rewrite');

    if (!$check && function_exists('shell_exec')) {
        if (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false) {
            $check = strpos(shell_exec('/usr/local/apache/bin/apachectl -l'), 'mod_rewrite') !== false;
        }
    }

    if ( function_exists('curl_version')) {
        $verbose = fopen('php://temp', 'w+');
        $ch = curl_init();
        $request_url_parser = parse_url($_SERVER['HTTP_REFERER']);
        $request_scheme = isset($request_url_parser['scheme']) ? $request_url_parser['scheme'] : 'http';
        $request_host = isset($request_url_parser['host']) ? $request_url_parser['host'] : 'localhost';
        $request_port = isset($request_url_parser['port']) ? $request_url_parser['port'] : (($request_scheme == 'https')? '443' : '80');
        curl_setopt($ch, CURLOPT_URL, $request_scheme ."://". $_SERVER['HTTP_HOST'] . SystemURLs::getRootPath()."/INVALID");

        if ( gethostbyname($request_host) == '127.0.0.1') {
            $sapi_type = php_sapi_name();

            if ( $sapi_type == 'fpm-fcgi') {
                curl_setopt($ch, CURLOPT_RESOLVE, array(sprintf("%s:%d:%s", $request_host, $request_port, $_SERVER['SERVER_ADDR'])));
            } else if (isset($_SERVER['HTTP_X_REAL_IP'])) {
                curl_setopt($ch, CURLOPT_RESOLVE, array(sprintf("%s:%d:%s", $request_host, $request_port, $_SERVER['REMOTE_ADDR'])));
            }
        }

        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
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

        if ( $check == false) {
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            $logger = LoggerUtils::getAppLogger();
            $logger->warn("Curl information: ".htmlspecialchars($verboseLog));
        }

        fclose($verbose);
      }

      return $check;
  }
}
?>
