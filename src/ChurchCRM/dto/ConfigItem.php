<?php

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\Config;

class ConfigItem
{
    private $id;
    private $name;
    private $value;
    private $type;
    private $default;
    private $tooltip;
    private $url;
    private $data;
    private $dbConfigItem;
    private $section = null;
    private $category = null;

    public function __construct($id, $name, $type, $default, $tooltip = '', $url = '', $data = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->default = $default;
        $this->tooltip = $tooltip;
        $this->data = $data;
        $this->url = $url;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setDBConfigObject($dbConfigItem): void
    {
        $this->dbConfigItem = $dbConfigItem;
        $this->value = $dbConfigItem->getValue();
    }

    public function getDBConfigObject()
    {
        return $this->dbConfigItem;
    }

    public function getValue()
    {
        if (isset($this->value)) {
            return $this->value;
        } else {
            return $this->default;
        }
    }

    public function getBooleanValue(): bool
    {
        return boolval($this->getValue());
    }

    public function setValue($value): void
    {
        if ($value == $this->getDefault()) {
          //if the value is being set to the default value
            if (isset($this->dbConfigItem)) { //and the item exists
            //delete the item
                $this->dbConfigItem->delete();
            }
        } else {
            //if the value is being set to a non-default value
            if (!isset($this->dbConfigItem)) {
                //create the item if it doesn't exist
                $this->dbConfigItem = new Config();
                $this->dbConfigItem->setId($this->getId());
                $this->dbConfigItem->setName($this->getName());
            }
            //set the values, and save it
            $this->dbConfigItem->setValue($value);
            $this->dbConfigItem->save();
            $this->value = $value;
        }
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getTooltip()
    {
        return $this->tooltip;
    }

    public function getSection()
    {
        return $this->section;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function getData()
    {
        return $this->data;
    }
}
