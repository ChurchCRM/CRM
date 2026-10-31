<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PersonPasskey as BasePersonPasskey;

/**
 * Skeleton subclass for representing a row from the 'person_passkey_ppk' table.
 *
 * A WebAuthn passkey a member added. The credential id is base64url text, looked up by its SHA-256 hash
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class PersonPasskey extends BasePersonPasskey
{

}
