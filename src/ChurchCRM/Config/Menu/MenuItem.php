<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\dto\SystemURLs;

class MenuItem
{
    private $name;
    private $uri;
    private $securityGroup;
    private $icon;
    private $subItems = [];
    private $counters = [];

    public function __construct($name, $uri, $securityGroup = "bAll", $icon = "")
    {
        $this->name = $name;
        $this->uri = $uri;
        $this->securityGroup = $securityGroup;
        $this->icon = $icon;
    }

    public function addSubMenu(MenuItem $menuItem)
    {
        $menuItem->setIcon("fa-angle-double-right");
        array_push($this->subItems, $menuItem);
    }

    public function addCounter(MenuCounter $counter) {
        array_push($this->counters, $counter);
    }

    public function getURI()
    {
        if (!empty($this->uri)) {
            return SystemURLs::getRootPath() . "/" . $this->uri;
        }
        return '';
    }

    public function setIcon($icon) {
        $this->icon = $icon;
    }

    public function getName()
    {
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

    public function getSubItems() {
        return $this->subItems;
    }

    public function getCounters() {
        return $this->counters;
    }

    public function hasCounters() {
        return !empty($this->counters);
    }

    public function isVisible()
    {
        return $this->securityGroup == "bAll";
    }

}
