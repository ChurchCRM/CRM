<?php

namespace ChurchCRM\Utils;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
class MiscUtils {
  
  public static function random_word( $length = 6 ) {
      $cons = array( 'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'z', 'pt', 'gl', 'gr', 'ch', 'ph', 'ps', 'sh', 'st', 'th', 'wh' );
      $cons_cant_start = array( 'ck', 'cm', 'dr', 'ds','ft', 'gh', 'gn', 'kr', 'ks', 'ls', 'lt', 'lr', 'mp', 'mt', 'ms', 'ng', 'ns','rd', 'rg', 'rs', 'rt', 'ss', 'ts', 'tch');
      $vows = array( 'a', 'e', 'i', 'o', 'u', 'y','ee', 'oa', 'oo');
      $current = ( mt_rand( 0, 1 ) == '0' ? 'cons' : 'vows' );
      $word = '';
      while( strlen( $word ) < $length ) {
          if( strlen( $word ) == 2 ) $cons = array_merge( $cons, $cons_cant_start );
          $rnd = ${$current}[ mt_rand( 0, count( ${$current} ) -1 ) ];
          if( strlen( $word . $rnd ) <= $length ) {
              $word .= $rnd;
              $current = ( $current == 'cons' ? 'vows' : 'cons' );
          }
      }
      return $word;
  }
  
  public static function getRandomCache($baseCacheTime,$variability){
    $var = rand(0,$variability);
    $dir = rand(0,1);
    if ($dir) {
      return $baseCacheTime - $var;
    }
    else{
      return $baseCacheTime + $var;
    }
    
  }
  
  public static function getPhotoCacheExpirationTimestamp() {
    $cacheLength = SystemConfig::getValue(iPhotoClientCacheDuration);
    $cacheLength = MiscUtils::getRandomCache($cacheLength,0.5*$cacheLength);
    //echo time() +  $cacheLength;
    //die();
    return time() + $cacheLength ;
  }
  
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
    $sRedirectURL = self::RedirectURL($sRelativeURL);
    header('Location: '.$sRedirectURL);
    exit;
  }
  
}
?>