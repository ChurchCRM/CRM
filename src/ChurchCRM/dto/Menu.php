<?php

namespace ChurchCRM\dto;

use ChurchCRM\Config;
use ChurchCRM\dto\MenuItem;

class Menu
{
    /**
   * @var Config[]
   */
  private static $menuItems;
  private static $rootMenu;


  private static function getPeopleMenu() {
    $peopleMenu = new MenuItem("people","People","People","",'','bAll',0,'fa-users');
    $peopleMenu->addSubMenu(new MenuItem("person dashboard", "Dashboard", "Dashboard", "PeopleDashboard.php", "", "", "bAddRecords",2,""));
    return $peopleMenu;
  }
  
  private static function buildMenuItems()
  {
     return array(
        "Calendar" => new MenuItem("calendar","Calendar","Calendar","calendar.php",'','bAll',0,'fa-calendar'),
        "People" => self::getPeopleMenu()
    );
  }

  /**
   * @param Config[] $configs
   */
  public static function init($configs=null)
  {
      self::$menuItems = self::buildMenuItems();
      if (!empty($menuItems)) {
        self::scrapeDBMenuItems($menuItems);
      }
  }
  
  public static function getMenu() {
    return self::$menuItems;
  }


  private static function scrapeDBMenuItems($menuItems)
  {
    foreach ($menuItems as $menuItem)
    {
      if ( isset( self::$menuItems[$menuItem->getName()]))
      {
        //if the current config set defined by code contains the current config retreived from the db, then cache it
        self::$menuItems[$menuItem->getName()]->setDBConfigObject($menuItem);
      }
      else
      {
        //there's a config item in the DB that doesn't exist in the current code.
        //delete it
        $menuItem->delete();
      }
    }
  }
}
