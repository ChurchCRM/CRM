<?php

namespace ChurchCRM\dto;

use ChurchCRM\Child;
use ChurchCRM\Config;

class SystemConfig
{
  /**
   * @var Config[]
   */
  private $configs;

  /**
   * @param Config[] $configs
   */
  function init($configs)
  {
    $this->configs = $configs;
  }

  function getValue($name)
  {
    $config = $this->getRawConfig($name);
    if (!is_null($config)) {
      return $config->getValue();
    }
    return NULL;
  }

    function setValue($name, $value)
  {
    $config = $this->getRawConfig($name);
    if (!is_null($config)) {
      $config->setValue($value);
      $config->save();
    }
  }

  /**
   * @param $name
   * @return Config
   *
   */
  function getRawConfig($name)
  {
    foreach ($this->configs as $config) {
      if ($config->getName() == $name) {
        return $config;
      }
    }
    return NULL;
  }
}
