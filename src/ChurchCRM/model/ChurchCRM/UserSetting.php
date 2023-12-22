<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\UserSetting as BaseUserSetting;

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
    public const UI_STYLE = 'ui.style';
    public const UI_BOXED = 'ui.boxed';
    public const UI_SIDEBAR = 'ui.sidebar';

    public const FINANCE_SHOW_PAYMENTS = 'finance.show.payments';
    public const FINANCE_SHOW_PLEDGES = 'finance.show.pledges';
    public const FINANCE_SHOW_SINCE = 'finance.show.since';

    public function set(User $user, $name, $value): void
    {
        $this->setUser($user);
        $this->setName($name);
        $this->setValue($value);
    }
}
