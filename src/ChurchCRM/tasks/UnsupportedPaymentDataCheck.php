<?php

namespace ChurchCRM\Tasks;

use \ChurchCRM\Service\SystemService;
use Propel\Runtime\Propel;
use ChurchCRM\dto\SystemURLs;

class UnsupportedPaymentDataCheck implements iTask
{
    private $count;

    public function __construct()
    {
      $this->count = 0;
      if(SystemService::getDBTableExists("autopayment_aut")){
        $connection = Propel::getConnection();
        $query = 'Select * from autopayment_aut';
        $statement = $connection->prepare($query);
        $statement->execute();
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $this->count = count($results);
      }
    }

    public function isActive()
    {
        return $this->count > 0;
    }

    public function isAdmin()
    {
        return false;
    }

    public function getLink()
    {
        return SystemURLs::getSupportURL(array_pop(explode('\\', __CLASS__)));
    }

    public function getTitle()
    {
        return gettext('Unsupported Stored Payment Data Detected') . " (" . $this->count . ")";
    }

    public function getDesc()
    {
        return gettext("Support for stored payment data has been removed. This data will soon be removed from your database");
    }

}
