<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemURLs;

class RedirectUtils {
  public static function RedirectURL($sRelativeURL){
    // Test if file exists before redirecting.  May need to remove
    // query string first.
    $iQueryString = strpos($sRelativeURL, '?');
    if ($iQueryString) {
        $sPathExtension = mb_substr($sRelativeURL, 0, $iQueryString);
    } else {
        $sPathExtension = $sRelativeURL;
    }

    // The idea here is to get the file path into this form:
    //     $sFullPath = $sDocumentRoot . $sRootPath . $sPathExtension
    // The Redirect URL is then in this form:
    //     $sRedirectURL = $sRootPath . $sPathExtension
    $sFullPath = str_replace('\\', '/', SystemURLs::getDocumentRoot().'/'.$sPathExtension);

    // With the query string removed we can test if file exists
    if (file_exists($sFullPath) && is_readable($sFullPath)) {
        return SystemURLs::getRootPath().'/'.$sRelativeURL;
    } else {
        $sErrorMessage = 'Fatal Error: Cannot access file: '.$sFullPath."<br>\n"
      ."\$sPathExtension = $sPathExtension<br>\n"
      ."\$sDocumentRoot = ".SystemURLs::getDocumentRoot()."<br>\n"
      .'$sRootPath = ' .SystemURLs::getRootPath()."<br>\n";

        die($sErrorMessage);
    }
  }

  public static function Redirect($sRelativeURL) {
    // Convert a relative URL into an absolute URL and redirect the browser there.
    $sRedirectURL = self::RedirectURL($sRelativeURL);
    header('Location: '.$sRedirectURL);
    exit;
  }
}
