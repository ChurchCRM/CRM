<?php

namespace ChurchCRM\Dashboard;

interface DashboardItemInterface {
  
  //must be all one word - no spaces
  public static function getDashboardItemName(); 
  
  // must return the JavaScript necessary to update this dashboard item on the page.  Will be passed the object returned by this functions "getDashboardItemValue()" function
  public static function getDashboardItemRenderer(); 
  
  //when provided with the page name of the user context, return true or false if this item should be loaded / provided in AJAX updates.
  public static function shouldInclude($PageName);
  
  // return a PHP array with all of the values to be passed to the renderer
  public static function getDashboardItemValue();
}
