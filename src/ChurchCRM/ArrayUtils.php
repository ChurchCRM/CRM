<?php

namespace ChurchCRM;

class ArrayUtils
{
    public static function inArrayRecursive($needle, $haystack)
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
