<?php

namespace ChurchCRM\Utils;

class InputUtils {
  
  private static $AllowedHTMLTags = '<a><b><i><u><h1><h2><h3><h4><h5><h6>';
  
  public static function LegacyFilterInputArr($arr, $key, $type = 'string', $size = 1)
  {
      if (array_key_exists($key, $arr)) {
          return InputUtils::LegacyFilterInput($arr[$key], $type, $size);
      } else {
          return InputUtils::LegacyFilterInput('', $type, $size);
      }
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
    return date('Y-m-d', strtotime($sInput));
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
}
?>
