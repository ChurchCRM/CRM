<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

class InputUtils {
  
  private static $AllowedHTMLTags = '<a><b><i><u><h1><h2><h3><h4><h5><h6><pre><address><img><table><td><tr><ol><li><ul><p><sub><sup><s><hr><span><blockquote><div><small><big><tt><code><kbd><samp><del><ins><cite><q>';
  
  public static function LegacyFilterInputArr($arr, $key, $type = 'string', $size = 1)
  {
      if (array_key_exists($key, $arr)) {
          return InputUtils::LegacyFilterInput($arr[$key], $type, $size);
      } else {
          return InputUtils::LegacyFilterInput('', $type, $size);
      }
  }
  
  public static function translate_special_charset ($string)
  {
    if (empty($string))
      return "";
    
    return (SystemConfig::getValue("sCSVExportCharset") == "UTF-8")?gettext($string):iconv('UTF-8', SystemConfig::getValue("sCSVExportCharset"), gettext($string));
  }    

  public static function FilterString($sInput)
  {
      // or use htmlspecialchars( stripslashes( ))
      $sInput = strip_tags(trim($sInput));
      if (get_magic_quotes_gpc()) {
        $sInput = stripslashes($sInput);
      }
      return $sInput;
  }

  public static function FilterHTML($sInput)
  {
      $sInput = strip_tags(trim($sInput), self::$AllowedHTMLTags);
      if (get_magic_quotes_gpc()) {
        $sInput = stripslashes($sInput);
      }
      return $sInput;
  }

  public static function FilterChar($sInput,$size=1)
  {
     $sInput = mb_substr(trim($sInput), 0, $size);
      if (get_magic_quotes_gpc()) {
        $sInput = stripslashes($sInput);
      }
      
      return $sInput;
  }

  public static function FilterInt($sInput)
  {
     return (int) intval(trim($sInput));
  }

  public static function FilterFloat($sInput)
  {
    return (float) floatval(trim($sInput));
  }

  public static function FilterDate($sInput)
  {
    // Attempts to take a date in any format and convert it to YYYY-MM-DD format
    // Logel Philippe
    if (empty($sInput))
      return "";
    else 
      return date('Y-m-d', strtotime(str_replace("/","-",$sInput)));
  }

  // Sanitizes user input as a security measure
  // Optionally, a filtering type and size may be specified.  By default, strip any tags from a string.
  // Note that a database connection must already be established for the mysqli_real_escape_string function to work.
  public static function LegacyFilterInput($sInput, $type = 'string', $size = 1)
  {
    global $cnInfoCentral;
    if (strlen($sInput) > 0) {
      switch ($type) {
        case 'string':
          return mysqli_real_escape_string($cnInfoCentral, self::FilterString($sInput));
        case 'htmltext':
          return mysqli_real_escape_string($cnInfoCentral, self::FilterHTML($sInput));
        case 'char':
          return mysqli_real_escape_string($cnInfoCentral, self::FilterChar($sInput,$size));
        case 'int':
         return self::FilterInt($sInput);
        case 'float':
          return self::FilterFloat($sInput);
        case 'date':
          return self::FilterDate($sInput);
      }
    } 
    else {
      return '';
    }
  }

    /*
     * this function can clean String to keep only [a-Z][0-9]-_ caracteres
     */
    public static function cleanString($text){
        $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y','%'=>'_' );
        $text = strtr( $text, $unwanted_array );
        $text = str_replace('\'','',$text);
        return $text;
    }
}
?>
