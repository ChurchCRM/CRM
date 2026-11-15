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
                    self::renderMenuItem($menuItem);
                } else {
                    self::renderSubMenuItem($menuItem);
                }
            }
        }
    }

    private static function renderMenuItem(MenuItem $menuItem, bool $nested = false): void
    {
        $isActive = $menuItem->isActive();
        $classes = ($nested ? 'dropdown-item' : 'nav-link') . ($isActive ? ' active' : '');
        ?>
        <?php if (!$nested): ?><li class="nav-item<?= $isActive ? ' active' : '' ?>"><?php endif; ?>
            <a href="<?= InputUtils::escapeAttribute($menuItem->getURI()) ?>"
               <?= $menuItem->isExternal() ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
               class="<?= $classes ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                <span class="nav-link-icon"><i class="fa <?= $menuItem->getIcon() ?>"></i></span>
                <span class="nav-link-title"><?= InputUtils::escapeHTML($menuItem->getName()) ?></span>
                <?php if ($menuItem->hasCounters()): self::renderMenuCounters($menuItem); endif; ?>
            </a>
        <?php if (!$nested): ?></li><?php endif; ?>
        <?php
    }

    private static function renderSubMenuItem(MenuItem $menuItem, bool $nested = false): void
    {
        $menuId = 'menu-' . ++self::$menuItemCounter;
        $isOpen = $menuItem->openMenu();
        $toggleClass = $nested ? 'dropdown-item dropdown-toggle' : 'nav-link dropdown-toggle';
        ?>
        <?php if (!$nested): ?><li class="nav-item dropdown<?= $isOpen ? ' show' : '' ?>"><?php else: ?><div class="dropdown<?= $isOpen ? ' show' : '' ?>"><?php endif; ?>
            <a class="<?= $toggleClass ?>"
               href="#<?= $menuId ?>"
               data-bs-toggle="dropdown"
               data-bs-auto-close="false"
               role="button"
               aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                <?php if (!$nested): ?>
                <span class="nav-link-icon"><i class="fa <?= $menuItem->getIcon() ?>"></i></span>
                <?php endif; ?>
                <span class="nav-link-title"><?= InputUtils::escapeHTML($menuItem->getName()) ?></span>
                <?php if ($menuItem->hasCounters()): self::renderMenuCounters($menuItem); endif; ?>
            </a>
            <div class="dropdown-menu<?= $isOpen ? ' show' : '' ?>" id="<?= $menuId ?>">
                <?php foreach ($menuItem->getSubItems() as $menuSubItem) {
                    if ($menuSubItem->isVisible()) {
                        if ($menuSubItem->isMenu()) {
                            self::renderSubMenuItem($menuSubItem, true);
                        } else {
                            self::renderMenuItem($menuSubItem, true);
                        }
                    }
                } ?>
            </div>
        <?php if (!$nested): ?></li><?php else: ?></div><?php endif; ?>
        <?php
    }

    private static function renderMenuCounters(MenuItem $menuItem): void
    {
        foreach ($menuItem->getCounters() as $counter) {
            ?>
            <span class="badge menu-counter <?= $counter->getCss() ?> me-1"
                  id="<?= $counter->getName() ?>"
                  <?php if ($counter->getTitle()): ?>title="<?= $counter->getTitle() ?>"<?php endif; ?>><?= $counter->getInitValue() ?></span>
            <?php
        }
    }
}
