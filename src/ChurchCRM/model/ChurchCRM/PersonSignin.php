<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PersonSignin as BasePersonSignin;

/**
 * Skeleton subclass for representing a row from the 'person_signin_psi' table.
 *
 * Member sign-in state for one person: Disable sign-in, session version, proven and unconfirmed email, sign-in times and the calendar feed
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class PersonSignin extends BasePersonSignin
{
    /**
     * Ends every portal session of this person on its next request (member
     * sign-in design §5). Save the row afterwards.
     */
    public function endAllSessions(): self
    {
        return $this->setSessionVersion((int) $this->getSessionVersion() + 1);
    }
}
