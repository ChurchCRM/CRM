<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\ArrayUtils;
use ChurchCRM\model\ChurchCRM\Base\Version as BaseVersion;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'version_ver' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Version extends BaseVersion
{
    public function preSave(ConnectionInterface $con = null): bool
    {
        //before we try to save this version object to the database, ensure that
        //the database has the correct columns to accomedate the version data

        $query = 'DESCRIBE version_ver';
        $statement = $con->prepare($query);
        $resultset = $statement->execute();
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if (!ArrayUtils::inArrayRecursive('ver_update_start', $results)) { //the versions table does not contain the ver_update_start column.
            $query = 'ALTER TABLE version_ver CHANGE COLUMN ver_date ver_update_start datetime default NULL;';
            $statement = $con->prepare($query);
            $resultset = $statement->execute();
        }

        if (!ArrayUtils::inArrayRecursive('ver_update_start', $results)) { //the versions table does not contain the ver_update_end column.
            $query = 'ALTER TABLE version_ver ADD COLUMN ver_update_end datetime default NULL AFTER ver_update_start;';
            $statement = $con->prepare($query);
            $resultset = $statement->execute();
        }
        //then save this version

        return true;
    }
}
