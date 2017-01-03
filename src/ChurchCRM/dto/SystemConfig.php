<?php

namespace ChurchCRM\dto;

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
      self::$configs = $configs;
  }

    public static function getValue($name)
    {
        $config = self::getRawConfig($name);
        if (!is_null($config)) {
            return $config->getValue();
        }
    }

    public static function getBooleanValue($name)
    {
        $config = self::getRawConfig($name);
        if (!is_null($config)) {
            return $config->getBooleanValue();
        }

        return false;
    }

    public static function setValue($name, $value)
    {
        $config = self::getRawConfig($name);
        if (!is_null($config)) {
            $config->setValue($value);
            $config->save();
        }
    }

    public static function setValueById($Id, $value)
    {
        $config = self::getRawConfigById($Id);
        if (!is_null($config)) {
            $config->setValue($value);
            $config->save();
        }
    }

  /**
   * @param $name
   *
   * @return Config
   */
  public static function getRawConfig($name)
  {
      foreach (self::$configs as $config) {
          if ($config->getName() == $name) {
              return $config;
          }
      }
  }

    public static function getRawConfigById($Id)
    {
        foreach (self::$configs as $config) {
            if ($config->getId() == $Id) {
                return $config;
            }
        }
    }

    public static function getConfigSteps()
    {
        $steps = [
      'Step1' => gettext('Church Information'),
      'Step2' => gettext('User setup'),
      'Step3' => gettext('Email Setup'),
      'Step4' => gettext('Member Setup'),
      'Step5' => gettext('System Settings'),
      'Step6' => gettext('Map Settings'),
      'Step7' => gettext('Report Settings'),
      'Step9' => gettext('Localization'),
      'Step8' => gettext('Other Settings'),
    ];

        return $steps;
    }
}
