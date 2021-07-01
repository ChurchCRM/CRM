<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class PersonClassificationDataCheck implements iTask
{
    private $count;

    public function __construct()
    {
        $personQuery = PersonQuery::create()
            ->filterByClsId(0)
            ->filterById(1, Criteria::NOT_EQUAL)
            ->find();
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
        return SystemURLs::getRootPath() . '/v2/people?Classification=0';
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
