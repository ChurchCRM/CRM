<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\FamilyEmailMove as BaseFamilyEmailMove;

/**
 * Skeleton subclass for representing a row from the 'family_email_move_fem' table.
 *
 * One row per family email the 7.8.3 upgrade moved to people (member sign-in design 10.1). fem_per_ID is the person who has the address afterwards: the head or spouse who received it, or the person who already held it. fem_NeedsConfirm marks an address moved into a family of two or more living, active people, which may not be used to sign in until staff confirm it. Dropped together with fam_Email.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class FamilyEmailMove extends BaseFamilyEmailMove
{

}
