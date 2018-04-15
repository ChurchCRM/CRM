<?php

namespace ChurchCRM\view;

use ChurchCRM\Config\Menu\Menu;
use ChurchCRM\Config\Menu\MenuItem;

class MenuRenderer
{
    public static function RenderMenu()
    {
        Menu::init();
        foreach (Menu::getMenu() as $menuItem) {
            if ($menuItem->isVisible()) {
                if (!$menuItem->isMenu()) {
                    self::renderMenuItem($menuItem);
                } else {
                    self::renderSubMenuItem($menuItem);
                }
            }
        }
    }

    private static function renderMenuItem(MenuItem $menuItem)
    {
        ?>
        <li <?= $menuItem->isActive()? "class='active'" : ""?>>
            <a href="<?= $menuItem->getURI() ?>" <?= $menuItem->isExternal() ? "target='_blank'" : "" ?>>
                <i class='fa <?= $menuItem->getIcon() ?>'></i>
                <span>
                    <?= $menuItem->getName() ?>
                    <?php self::renderMenuCounters($menuItem) ?>
                </span>
            </a>
        </li>
        <?php
    }

    private static function renderSubMenuItem(MenuItem $menuItem)
    {
        ?>
        <li class="treeview <?= $menuItem->openMenu()? "menu-open active" : "" ?>">
            <a href="#">
                <i class="fa <?= $menuItem->getIcon() ?>"></i>
                <span>
                    <?= $menuItem->getName() ?>
                    <?php self::renderMenuCounters($menuItem) ?>
                </span>
                <i class="fa fa-angle-left pull-right"></i>
            </a>
            <ul class="treeview-menu ">
            <?php foreach ($menuItem->getSubItems() as $menuSubItem) {
                if ($menuSubItem->isVisible()) {
                    if ($menuSubItem->isMenu()) {
                        self::renderSubMenuItem($menuSubItem);
                    } else {
                        self::renderMenuItem($menuSubItem);
                    }
                }
            } ?>
            </ul>
        </li>
        <?php
    }


    private static function renderMenuCounters(MenuItem $menuItem)
    {
        if ($menuItem->hasCounters()) {
            ?>
            <span class='pull-right-container'>
                <?php foreach ($menuItem->getCounters() as $counter) { ?>
                    <small class='label pull-right <?= $counter->getCss() ?>'
                           id='<?= $counter->getName() ?>'><?= $counter->getInitValue() ?></small>
                <?php } ?>
            </span>
            <?php
        }
    }
}
