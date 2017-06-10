<?php

namespace ChurchCRM
{
  class ArrayUtils
  {
      public static function in_array_recursive($needle, $haystack)
      {
          $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($haystack));
          foreach ($it as $element) {
              if ($element == $needle) {
                  return true;
              }
          }

          return false;
      }
  }
}
