<?php

namespace ChurchCRM\Utils;

class InputUtils {
  
  public static function LegacyFilterInputArr($arr, $key, $type = 'string', $size = 1)
  {
      if (array_key_exists($key, $arr)) {
          return InputUtils::LegacyFilterInput($arr[$key], $type, $size);
      } else {
          return InputUtils::LegacyFilterInput('', $type, $size);
      }
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
          // or use htmlspecialchars( stripslashes( ))
          $sInput = strip_tags(trim($sInput));
          if (get_magic_quotes_gpc()) {
              $sInput = stripslashes($sInput);
          }
          $sInput = mysqli_real_escape_string($cnInfoCentral, $sInput);

          return $sInput;
        case 'htmltext':
          $sInput = strip_tags(trim($sInput), '<a><b><i><u><h1><h2><h3><h4><h5><h6>');
          if (get_magic_quotes_gpc()) {
              $sInput = stripslashes($sInput);
          }
          $sInput = mysqli_real_escape_string($cnInfoCentral, $sInput);

          return $sInput;
        case 'char':
          $sInput = mb_substr(trim($sInput), 0, $size);
          if (get_magic_quotes_gpc()) {
              $sInput = stripslashes($sInput);
          }
          $sInput = mysqli_real_escape_string($cnInfoCentral, $sInput);

          return $sInput;
        case 'int':
          return (int) intval(trim($sInput));
        case 'float':
          return (float) floatval(trim($sInput));
        case 'date':
          // Attempts to take a date in any format and convert it to YYYY-MM-DD format
          return date('Y-m-d', strtotime($sInput));
      }
      } else {
          return '';
      }
  }
}
?>