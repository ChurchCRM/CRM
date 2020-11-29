<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class PersonGenderDataCheck implements iTask
{
    private $count;

    public function __construct()
    {
        $personQuery = PersonQuery::create()
            ->filterByGender(0)
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
        return SystemURLs::getRootPath() . '/v2/people?Gender=0';
    }

    public function getTitle()
    {
        return gettext('Missing Gender Data') . " (" . $this->count . ")";
    }

    public function getDesc()
    {
        return gettext("Missing Gender Data for Some People");
    }

}
