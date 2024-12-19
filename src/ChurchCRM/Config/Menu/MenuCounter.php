<?php

namespace ChurchCRM\Config\Menu;

class MenuCounter
{
    private $name;
    private $css;
    private $initValue;

    public function __construct($name, $css, $initValue = 0)
    {
        $this->name = $name;
        $this->css = $css;
        $this->initValue = $initValue;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * @return int
     */
    public function getInitValue()
    {
        return $this->initValue;
    }
}
