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
        $menuItem->setIcon("fa-angle-double-right");
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
            return SystemURLs::getRootPath() . "/" . $this->uri;
        }
        return '';
    }

    public function setIcon($icon)
    {
        $this->icon = $icon;
    }

    public function getName()
    {
        if (strlen($this->name) > $this->maxNameStr) {
            return substr($this->name, 0, $this->maxNameStr - 3) . " ...";
        }
        return $this->name;
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
        return $this->hasPermission;
    }

}
