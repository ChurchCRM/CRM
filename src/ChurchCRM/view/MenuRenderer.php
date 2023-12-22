<?php

namespace ChurchCRM\view;

use ChurchCRM\Config\Menu\Menu;
use ChurchCRM\Config\Menu\MenuItem;

class MenuRenderer
{
    public static function renderMenu(): void
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

    private static function renderMenuItem(MenuItem $menuItem): void
    {
        ?>
        <li class="nav-item<?= $menuItem->isActive() ? " active" : ""?>">
            <a href="<?= $menuItem->getURI() ?>" <?= $menuItem->isExternal() ? "target='_blank'" : "" ?> class="nav-link">
                <i class='nav-icon fa <?= $menuItem->getIcon() ?>'></i>
                <p>
                    <?= $menuItem->getName() ?>
                    <?php self::renderMenuCounters($menuItem) ?>
                </p>
            </a>
        </li>
        <?php
    }

    private static function renderSubMenuItem(MenuItem $menuItem): void
    {
        ?>
        <div class="nav-item<?= $menuItem->openMenu() ? " menu-open active" : "" ?>">
            <a href="#" class="nav-link">
                <i class="nav-icon fa <?= $menuItem->getIcon() ?>"></i>
                <p>
                    <b><?= $menuItem->getName() ?></b>
                    <?php self::renderMenuCounters($menuItem) ?>
                    <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
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
        </div>
        <?php
    }


    private static function renderMenuCounters(MenuItem $menuItem): void
    {
        if ($menuItem->hasCounters()) {
            ?>

                <?php foreach ($menuItem->getCounters() as $counter) { ?>
                    <small class='right badge <?= $counter->getCss() ?>'
                           id='<?= $counter->getName() ?>'><?= $counter->getInitValue() ?></small>
                <?php } ?>

            <?php
        }
    }
}
