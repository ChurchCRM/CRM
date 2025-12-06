<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\dto\SystemURLs;

class MenuItem
{
    private int $maxNameStr = 25;
    private $name;
    private $uri;
    private $hasPermission;
    private $icon;
    private bool $external = false;
    private array $subItems = [];
    private array $counters = [];

    public function __construct($name, $uri, $hasPermission = true, $icon = '')
    {
        $this->name = $name;
        $this->uri = $uri;
        $this->hasPermission = $hasPermission;
        $this->icon = $icon;
    }

    public function addSubMenu(MenuItem $menuItem): void
    {
        if (empty($menuItem->getIcon())) {
            $menuItem->setIcon('fa-angle-double-right');
        }
        $this->subItems[] = $menuItem;
    }

    public function addCounter(MenuCounter $counter): void
    {
        $this->counters[] = $counter;
    }

    public function getURI()
    {
        //Review SessionVar stuff
        if (!empty($this->uri)) {
            if (filter_var($this->uri, FILTER_VALIDATE_URL) === false) {
                return SystemURLs::getRootPath() . '/' . $this->uri;
            }
            $this->external = true;

            return $this->uri;
        }

        return '';
    }

    public function setIcon($icon): void
    {
        $this->icon = $icon;
    }

    public function getName()
    {
        if (mb_strlen($this->name) > $this->maxNameStr) {
            return mb_substr($this->name, 0, $this->maxNameStr - 3) . ' ...';
        }

        return $this->name;
    }

    public function isExternal(): bool
    {
        return $this->external;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function isMenu(): bool
    {
        return !empty($this->subItems);
    }

    public function getSubItems(): array
    {
        return $this->subItems;
    }

    public function hasVisibleSubMenus(): bool
    {
        foreach ($this->subItems as $item) {
            if ($item->isVisible()) {
                return true;
            }
        }

        return false;
    }

    public function getCounters(): array
    {
        return $this->counters;
    }

    public function hasCounters(): bool
    {
        return !empty($this->counters);
    }

    public function isVisible(): bool
    {
        if ($this->hasPermission && (!empty($this->uri) || $this->hasVisibleSubMenus())) {
            return true;
        }

        return false;
    }

    public function openMenu(): bool
    {
        foreach ($this->subItems as $item) {
            // Check if this child is active
            if ($item->isActive()) {
                return true;
            }
            // Recursively check if any nested submenu has an active item
            if ($item->isMenu() && $item->openMenu()) {
                return true;
            }
        }

        return false;
    }

    public function isActive(): bool
    {
        if (empty($this->uri)) {
            return false;
        }

        $menuUri = $this->getURI();
        $currentUri = $_SERVER['REQUEST_URI'];

        // Parse both URIs
        $currentPath = parse_url($currentUri, PHP_URL_PATH);
        $menuPath = parse_url($menuUri, PHP_URL_PATH);
        $menuQuery = parse_url($menuUri, PHP_URL_QUERY);
        $currentQuery = parse_url($currentUri, PHP_URL_QUERY);

        // Paths must match first
        if ($currentPath !== $menuPath) {
            return false;
        }

        // If menu item has query params, check they all match
        if (!empty($menuQuery)) {
            if (empty($currentQuery)) {
                return false;
            }

            parse_str($menuQuery, $menuParams);
            parse_str($currentQuery, $currentParams);

            // Check if all menu params exist in current params with same values
            foreach ($menuParams as $key => $value) {
                if (!isset($currentParams[$key]) || $currentParams[$key] !== $value) {
                    return false;
                }
            }

            return true;
        }

        // Menu item has NO query params - only match if current URL also has no query params
        // This prevents "/v2/family" from matching "/v2/family?mode=inactive"
        return empty($currentQuery);
    }
}
