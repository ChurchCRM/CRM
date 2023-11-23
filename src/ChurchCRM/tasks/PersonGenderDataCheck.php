<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class PersonGenderDataCheck implements TaskInterface
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

    public function isActive(): bool
    {
        return $this->count > 0;
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function getLink(): string
    {
        return SystemURLs::getRootPath() . '/v2/people?Gender=0';
    }

    public function getTitle(): string
    {
        return gettext('Missing Gender Data') . ' (' . $this->count . ')';
    }

    public function getDesc(): string
    {
        return gettext('Missing Gender Data for Some People');
    }
}
