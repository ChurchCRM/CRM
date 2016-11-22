<?php

namespace ChurchCRM\dto;

use ChurchCRM\Child;
use ChurchCRM\Config;

class SystemConfig
{
  /**
   * @var Config[]
   */
  private static $configs;

  /**
   * @param Config[] $configs
   */
  public static function init($configs)
  {
    SystemConfig::$configs = $configs;
  }

  public static function getValue($name)
  {
    $config = SystemConfig::getRawConfig($name);
    if (!is_null($config)) {
      return $config->getValue();
    }
    return NULL;
  }

  public static function setValue($name, $value)
  {
    $config = SystemConfig::getRawConfig($name);
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
  public static function getRawConfig($name)
  {
    foreach (SystemConfig::$configs as $config) {
      if ($config->getName() == $name) {
        return $config;
      }
    }
    return NULL;
  }
}
