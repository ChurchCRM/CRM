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
            <a href="<?= htmlspecialchars($menuItem->getURI(), ENT_QUOTES, 'UTF-8') ?>" <?= $menuItem->isExternal() ? "target='_blank'" : "" ?> class="nav-link<?= $menuItem->isActive() ? " active" : ""?>">
                <i class='nav-icon fa <?= $menuItem->getIcon() ?>'></i>
                <p>
                    <span><?= htmlspecialchars($menuItem->getName()) ?></span>
                    <span class="right">
                        <?php self::renderMenuCounters($menuItem) ?>
                    </span>
                </p>
            </a>
        </li>
        <?php
    }

    private static function renderSubMenuItem(MenuItem $menuItem): void
    {
        ?>
        <li class="nav-item<?= $menuItem->openMenu() ? " menu-open" : "" ?>">
            <a href="#" class="nav-link<?= $menuItem->openMenu() ? " active" : "" ?>">
                <i class="nav-icon fa <?= $menuItem->getIcon() ?>"></i>
                <p>
                    <span><?= htmlspecialchars($menuItem->getName()) ?></span>
                    <span class="right">
                        <?php self::renderMenuCounters($menuItem) ?>
                        <i class="fa-solid fa-angle-left"></i>
                    </span>
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
        </li>
        <?php
    }

    private static function renderMenuCounters(MenuItem $menuItem): void
    {
        if ($menuItem->hasCounters()) {
            ?>

                <?php foreach ($menuItem->getCounters() as $counter) { ?>
                    <small class='badge <?= $counter->getCss() ?>'
                           id='<?= $counter->getName() ?>'
                           <?php if ($counter->getTitle()): ?>title="<?= $counter->getTitle() ?>"<?php endif; ?>><?= $counter->getInitValue() ?></small>
                <?php } ?>

            <?php
        }
    }
}
