<?php

namespace ChurchCRM;

use ChurchCRM\Base\UserSetting as BaseUserSetting;

/**
 * Skeleton subclass for representing a row from the 'user_settings' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class UserSetting extends BaseUserSetting
{
    public function __construct() {

    }

    public function set($user, $name, $value)
    {
        $this->setUser($user);
        $this->setName($name);
        $this->setValue($value);
    }
}
