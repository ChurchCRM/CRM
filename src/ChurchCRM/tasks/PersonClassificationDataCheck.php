<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;

class PersonClassificationDataCheck implements iTask
{
    private $count;

    public function __construct()
    {
        $personQuery = PersonQuery::create()->filterByClsId(0)->find();
        $this->count = $personQuery->count();
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
        return SystemURLs::getRootPath() . '/SelectList.php?mode=person&Classification=0&PersonColumn3=Classification';
    }

    public function getTitle()
    {
        return gettext('Missing Classification Data') . " (" . $this->count . ")";
    }

    public function getDesc()
    {
        return gettext("Missing Classification Data for Some People");
    }

}
