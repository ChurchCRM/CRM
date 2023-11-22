<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class PersonRoleDataCheck implements TaskInterface
{
    private $count;

    public function __construct()
    {
        $personQuery = PersonQuery::create()->filterByFmrId(0)
            ->filterById(1, Criteria::NOT_EQUAL)
            ->filterByFamId('', Criteria::NOT_EQUAL)
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
        return SystemURLs::getRootPath() . '/v2/people?FamilyRole=0';
    }

    public function getTitle(): string
    {
        return gettext('Missing Role Data') . ' (' . $this->count . ')';
    }

    public function getDesc(): string
    {
        return gettext('Missing Role Data for Some People');
    }
}
