<?php

namespace ChurchCRM\view;

use ChurchCRM\Config\Menu\Menu;
use ChurchCRM\Config\Menu\MenuItem;
use ChurchCRM\Utils\InputUtils;

class MenuRenderer
{
    private static int $menuItemCounter = 0;

    public static function renderMenu(): void
    {
        Menu::init();
        foreach (Menu::getMenu() as $menuItem) {
            if ($menuItem->isVisible()) {
                if (!$menuItem->isMenu()) {
                    self::renderMenuItem($menuItem, 0);
                } else {
                    self::renderSubMenuItem($menuItem, 0);
                }
            }
        }
    }

    private static function renderMenuItem(MenuItem $menuItem, int $depth): void
    {
        ?>
        <li class="nav-item">
            <a href="<?= InputUtils::escapeAttribute($menuItem->getURI()) ?>"
               <?= $menuItem->isExternal() ? "target='_blank'" : "" ?>
               class="nav-link<?= $menuItem->isActive() ? " active" : "" ?>">
                <span class="nav-link-icon d-md-none d-lg-inline-block">
                    <i class="fa <?= $menuItem->getIcon() ?>"></i>
                </span>
                <span class="nav-link-title"><?= InputUtils::escapeHTML($menuItem->getName()) ?></span>
                <?php self::renderMenuCounters($menuItem) ?>
            </a>
        </li>
        <?php
    }

    private static function renderSubMenuItem(MenuItem $menuItem, int $depth): void
    {
        $collapseId = 'menu-' . ++self::$menuItemCounter;
        $isOpen     = $menuItem->openMenu();
        ?>
        <li class="nav-item">
            <a class="nav-link<?= $isOpen ? " active" : "" ?>"
               href="#<?= $collapseId ?>"
               data-bs-toggle="collapse"
               role="button"
               aria-expanded="<?= $isOpen ? "true" : "false" ?>"
               aria-controls="<?= $collapseId ?>">
                <span class="nav-link-icon d-md-none d-lg-inline-block">
                    <i class="fa <?= $menuItem->getIcon() ?>"></i>
                </span>
                <span class="nav-link-title"><?= InputUtils::escapeHTML($menuItem->getName()) ?></span>
                <?php self::renderMenuCounters($menuItem) ?>
                <span class="nav-link-arrow"><i class="fa fa-chevron-down" aria-hidden="true"></i></span>
            </a>
            <div class="collapse<?= $isOpen ? " show" : "" ?>" id="<?= $collapseId ?>">
                <ul class="navbar-nav ps-3">
                    <?php foreach ($menuItem->getSubItems() as $menuSubItem) {
                        if ($menuSubItem->isVisible()) {
                            if ($menuSubItem->isMenu()) {
                                self::renderSubMenuItem($menuSubItem, $depth + 1);
                            } else {
                                self::renderMenuItem($menuSubItem, $depth + 1);
                            }
                        }
                    } ?>
                </ul>
            </div>
        </li>
        <?php
    }

    private static function renderMenuCounters(MenuItem $menuItem): void
    {
        if ($menuItem->hasCounters()) {
            foreach ($menuItem->getCounters() as $counter) {
                ?>
                <span class="badge <?= $counter->getCss() ?>"
                      id="<?= $counter->getName() ?>"
                      <?php if ($counter->getTitle()): ?>title="<?= $counter->getTitle() ?>"<?php endif; ?>><?= $counter->getInitValue() ?></span>
                <?php
            }
        }
    }
}
