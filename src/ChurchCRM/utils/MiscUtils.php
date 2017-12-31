<?php

namespace ChurchCRM\Utils;
use ChurchCRM\dto\SystemConfig;
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
  
  //
  // Formats an age suffix: age in years, or in months if less than one year old
  //
  public static function FormatAgeSuffix($birthDate, $Flags)
  {
      if ($Flags == 1) {
          return '';
      }

      $ageSuffix = gettext('Unknown');

      $now = new DateTime();
      $age = $now->diff($birthDate);

      if ($age->y < 1) {
          if ($age->m > 1) {
              $ageSuffix = gettext('mos old');
          } else {
              $ageSuffix = gettext('mo old');
          }
      } else {
          if ($age->y > 1) {
              $ageSuffix = gettext('yrs old');
          } else {
              $ageSuffix = gettext('yr old');
          }
      }

      return $ageSuffix;
  }
  
  public static function FormatAge($Month, $Day, $Year, $Flags)
  {
      if (($Flags & 1)) { //||!$_SESSION['bSeePrivacyData']
          return;
      }

      if ($Year > 0) {
          if ($Year == date('Y')) {
              $monthCount = date('m') - $Month;
              if ($Day > date('d')) {
                  $monthCount--;
              }
              if ($monthCount == 1) {
                  return gettext('1 m old');
              } else {
                  return $monthCount.' '.gettext('m old');
              }
          } elseif ($Year == date('Y') - 1) {
              $monthCount = 12 - $Month + date('m');
              if ($Day > date('d')) {
                  $monthCount--;
              }
              if ($monthCount >= 12) {
                  return gettext('1 yr old');
              } elseif ($monthCount == 1) {
                  return gettext('1 m old');
              } else {
                  return $monthCount.' '.gettext('m old');
              }
          } elseif ($Month > date('m') || ($Month == date('m') && $Day > date('d'))) {
              return date('Y') - 1 - $Year.' '.gettext('yrs old');
          } else {
              return date('Y') - $Year.' '.gettext('yrs old');
          }
      } else {
          return gettext('Unknown');
      }
  }

  // Format a BirthDate
  // Optionally, the separator may be specified.  Default is YEAR-MN-DY
  public static function FormatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, $sSeparator, $bFlags)
  {
      if ($bFlags == 1 || $per_BirthYear == '') {  //Person Would Like their Age Hidden or BirthYear is not known.
          $birthYear = '1000';
      } else {
          $birthYear = $per_BirthYear;
      }

      if ($per_BirthMonth > 0 && $per_BirthDay > 0 && $birthYear != 1000) {
          if ($per_BirthMonth < 10) {
              $dBirthMonth = '0'.$per_BirthMonth;
          } else {
              $dBirthMonth = $per_BirthMonth;
          }
          if ($per_BirthDay < 10) {
              $dBirthDay = '0'.$per_BirthDay;
          } else {
              $dBirthDay = $per_BirthDay;
          }

          $dBirthDate = $dBirthMonth.$sSeparator.$dBirthDay;
          if (is_numeric($birthYear)) {
              $dBirthDate = $birthYear.$sSeparator.$dBirthDate;
              if (checkdate($dBirthMonth, $dBirthDay, $birthYear)) {
                  $dBirthDate = FormatDate($dBirthDate);
                  if (mb_substr($dBirthDate, -6, 6) == ', 1000') {
                      $dBirthDate = str_replace(', 1000', '', $dBirthDate);
                  }
              }
          }
      } elseif (is_numeric($birthYear) && $birthYear != 1000) {  //Person Would Like Their Age Hidden
          $dBirthDate = $birthYear;
      } else {
          $dBirthDate = '';
      }

      return $dBirthDate;
  }
  
  public static function BirthDate($year, $month, $day, $hideAge)
  {
      if (!is_null($day) && $day != '' &&
      !is_null($month) && $month != ''
    ) {
          $birthYear = $year;
          if ($hideAge) {
              $birthYear = 1900;
          }

          return date_create($birthYear.'-'.$month.'-'.$day);
      }

      return date_create();
  }
  
}
?>