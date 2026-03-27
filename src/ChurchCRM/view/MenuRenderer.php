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

    private static function renderMenuItem(MenuItem $menuItem): void
    {
        $isActive    = $menuItem->isActive();
        $hasCounters = $menuItem->hasCounters();
        ?>
        <li class="nav-item<?= $isActive ? ' active' : '' ?>">
            <div class="d-flex align-items-center">
                <a href="<?= InputUtils::escapeAttribute($menuItem->getURI()) ?>"
                   <?= $menuItem->isExternal() ?"target='_blank'" : '' ?>
                   class="nav-link<?= $isActive ? ' active' : '' ?> flex-fill">
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                        <i class="fa <?= $menuItem->getIcon() ?>"></i>
                    </span>
                    <span class="nav-link-title"><?= InputUtils::escapeHTML($menuItem->getName()) ?></span>
                </a>
                <?php if ($hasCounters): self::renderMenuCounters($menuItem); endif; ?>
            </div>
        </li>
        <?php
    }

    private static function renderSubMenuItem(MenuItem $menuItem): void
    {
        $collapseId  = 'menu-' . ++self::$menuItemCounter;
        $isOpen      = $menuItem->openMenu();
        $hasCounters = $menuItem->hasCounters();
        ?>
        <li class="nav-item">
            <div class="d-flex align-items-center">
                <a class="nav-link flex-fill"
                   href="#<?= $collapseId ?>"
                   data-bs-toggle="collapse"
                   role="button"
                   aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                   aria-controls="<?= $collapseId ?>">
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                        <i class="fa <?= $menuItem->getIcon() ?>"></i>
                    </span>
                    <span class="nav-link-title"><?= InputUtils::escapeHTML($menuItem->getName()) ?></span>
                </a>
                <?php if ($hasCounters): self::renderMenuCounters($menuItem); endif; ?>
                <span class="nav-link-arrow me-2"><i class="fa fa-chevron-down" aria-hidden="true"></i></span>
            </div>
            <div class="collapse<?= $isOpen ? ' show' : '' ?>" id="<?= $collapseId ?>">
                <ul class="navbar-nav ps-3">
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
        </li>
        <?php
    }

    private static function renderMenuCounters(MenuItem $menuItem): void
    {
        foreach ($menuItem->getCounters() as $counter) {
            ?>
            <span class="badge <?= $counter->getCss() ?> me-1"
                  id="<?= $counter->getName() ?>"
                  <?php if ($counter->getTitle()): ?>title="<?= $counter->getTitle() ?>"<?php endif; ?>><?= $counter->getInitValue() ?></span>
            <?php
        }
    }
}
