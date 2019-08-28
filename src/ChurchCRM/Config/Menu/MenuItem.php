<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\dto\SystemURLs;

class MenuItem
{
    private $maxNameStr = 25;
    private $name;
    private $uri;
    private $hasPermission;
    private $icon;
    private $external = false;
    private $subItems = [];
    private $counters = [];

    public function __construct($name, $uri, $hasPermission = true, $icon = "")
    {
        $this->name = $name;
        $this->uri = $uri;
        $this->hasPermission = $hasPermission;
        $this->icon = $icon;
    }

    public function addSubMenu(MenuItem $menuItem)
    {
        if (empty($menuItem->getIcon())) {
            $menuItem->setIcon("fa-angle-double-right");
        }
        array_push($this->subItems, $menuItem);
    }

    public function addCounter(MenuCounter $counter)
    {
        array_push($this->counters, $counter);
    }

    public function getURI()
    {
        //Review SessionVar stuff
        if (!empty($this->uri)) {
            if (filter_var($this->uri, FILTER_VALIDATE_URL) === FALSE) {
                return SystemURLs::getRootPath() . "/" . $this->uri;
            }
            $this->external = true;
            return $this->uri;
        }
        return '';
    }

    public function setIcon($icon)
    {
        $this->icon = $icon;
    }

    public function getName()
    {
        if (mb_strlen($this->name) > $this->maxNameStr) {
            return mb_substr($this->name, 0, $this->maxNameStr - 3) . " ...";
        }
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isExternal()
    {
        return $this->external;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function isMenu()
    {
        return !empty($this->subItems);
    }

    public function getSubItems()
    {
        return $this->subItems;
    }

    public function hasVisibleSubMenus()
    {
        foreach ($this->subItems as $item) {
            if ($item->isVisible()) {
                return true;
            }
        }
        return false;
    }

    public function getCounters()
    {
        return $this->counters;
    }

    public function hasCounters()
    {
        return !empty($this->counters);
    }

    public function isVisible()
    {
        if ($this->hasPermission && (!empty($this->uri) || $this->hasVisibleSubMenus())) {
            return true;
        }
        return false;
    }

    public function openMenu() {
        foreach ($this->subItems as $item) {
            if ($item->isActive()) {
                return true;
            }
        }
        return false;
    }

    public function isActive()
    {
        if (empty($this->uri)) {
            return false;
        }
        return $_SERVER["REQUEST_URI"] == $this->getURI();
    }

}
