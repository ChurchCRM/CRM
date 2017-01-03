<?php

namespace ChurchCRM;

use ChurchCRM\Base\Config as BaseConfig;

/**
 * Skeleton subclass for representing a row from the 'config_cfg' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Config extends BaseConfig
{
    public function getBooleanValue()
    {
        return boolval($this->getValue());
    }

    public function getValue()
    {
        if (is_null($this->cfg_value)) {
            return $this->cfg_default;
        }

        return $this->cfg_value;
    }
}
