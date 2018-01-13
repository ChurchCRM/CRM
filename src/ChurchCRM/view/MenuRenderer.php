<?php

namespace ChurchCRM\view;

use ChurchCRM\dto\Menu; 
use ChurchCRM\dto\MenuItem;
use ChurchCRM\dto\SystemURLs;

class MenuRenderer {
 public static function RenderMenu() {
   Menu::init();
   self::RenderDashboard();
   foreach (Menu::getMenu() as $menuItem) {
     self::renderMenuItem($menuItem);
   }
 }
 
 private static function renderMenuItem(\ChurchCRM\dto\MenuItem $menuItem) {
   ?>
    <li>
        <a href="<?= SystemURLs::getRootPath() . '/' . $menuItem->getURI() ?>">
        <i class='fa <?= $menuItem->getIcon() ?>'></i>
        <span>
          <?= gettext($menuItem->getContent()) ?>
         
        </span>
      </a>
    </li>

   <?php
 }
 
 private static function RenderDashboard() {
 ?>
  <li>
    <a href="<?= SystemURLs::getRootPath() ?>/Menu.php">
      <i class="fa fa-dashboard"></i> <span><?= gettext('Dashboard') ?></span>
    </a>
  </li>
 <?php
 }
}
