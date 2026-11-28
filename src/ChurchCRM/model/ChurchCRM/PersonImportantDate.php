<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PersonImportantDate as BasePersonImportantDate;
use ChurchCRM\Utils\AnnualDate;

/**
 * One important date on a person: month, day and optional year.
 */
class PersonImportantDate extends BasePersonImportantDate
{
    public function getAnnualDate(): AnnualDate
    {
        return new AnnualDate((int) $this->getMonth(), (int) $this->getDay(), $this->getYear() === null ? null : (int) $this->getYear());
    }
}
