<?php

namespace ChurchCRM;

use ChurchCRM\Base\FileAssociation as BaseFileAssociation;

/**
 * Skeleton subclass for representing a row from the 'file_associations' table.
 *
 * This is a join-table to link files with other types
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class FileAssociation extends BaseFileAssociation
{

    public function postDelete(\Propel\Runtime\Connection\ConnectionInterface $con = null)
    {
        $otherAssociations = FileAssociationQuery::create()
          ->filterByFile($this->getFile())
          ->find();
        if (count($otherAssociations) == 0 ) {
           $this->getFile()->delete();
        }
        return true;
    }
}
