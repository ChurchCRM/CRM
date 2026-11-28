<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\ImportantDateType as BaseImportantDateType;

/**
 * A church-defined kind of yearly date (baptism, confirmation, dedication...)
 * recorded on people or families.
 */
class ImportantDateType extends BaseImportantDateType
{
    public const APPLIES_TO_PERSON = 'person';
    public const APPLIES_TO_FAMILY = 'family';

    public function appliesToPerson(): bool
    {
        return $this->getAppliesTo() === self::APPLIES_TO_PERSON;
    }
}
