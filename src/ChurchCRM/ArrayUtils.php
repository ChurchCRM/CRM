<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ChurchCRM
{
  class ArrayUtils
  {
    public static function in_array_recursive($needle, $haystack) { 
      $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($haystack)); 
      foreach($it AS $element) { 
        if($element == $needle) { 
          return true; 
        }
      }
      return false; 
    }
  }
}