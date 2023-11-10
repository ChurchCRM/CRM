<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery as BaseListOptionQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'list_lst' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ListOptionQuery extends BaseListOptionQuery
{
    public function getFamilyRoles()
    {
        return $this
            ->filterById(2)
            ->orderByOptionSequence()
            ->find();
    }
}
