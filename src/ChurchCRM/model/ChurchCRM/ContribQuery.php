<?php

namespace ChurchCRM;

use ChurchCRM\Base\ContribQuery as BaseContribQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'contrib_con' table.
 *
 * This contains all contribution information
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class ContribQuery extends BaseContribQuery
{
    public function preSelect(\Propel\Runtime\Connection\ConnectionInterface $con)
    {
        // $this->leftJoinPerson();
        // $this->withColumn("per_ID", "per_ID");
        // $this->withColumn("per_FirstName", "FirstName");
        // $this->withColumn("per_LastName", "LastName");
        // $this->withColumn("per_Envelope", "Envelope");
        // $this->leftJoinContribSplit();
        // $this->withColumn("spl_FundId", "spl_FundId");
        // $this->groupById();
        // $this->withColumn('SUM(contrib_split.spl_Amount)', 'totalAmount');
        // parent::preSelect($con);
    }
}
