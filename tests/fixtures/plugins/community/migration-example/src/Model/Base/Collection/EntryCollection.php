<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\Plugins\MigrationExample\Model\Base\Collection;

use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Formatter\ObjectFormatter;

/**
 * Custom collection for Entry.
 *
 * @extends \Propel\Runtime\Collection\ObjectCollection<\ChurchCRM\Plugins\MigrationExample\Model\Base\Entry>
 */
class EntryCollection extends ObjectCollection
{
    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->setModel('\ChurchCRM\Plugins\MigrationExample\Model\Entry');
        $this->setFormatter(new ObjectFormatter());
    }

}
