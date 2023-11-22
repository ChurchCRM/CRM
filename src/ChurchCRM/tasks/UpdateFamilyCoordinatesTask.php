<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;

class UpdateFamilyCoordinatesTask
{
    private $count;

    public function __construct()
    {
        $query = FamilyQuery::create()->filterByLatitude('')->find();
        $this->count = $query->count();
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
        return SystemURLs::getRootPath() . '/UpdateAllLatLon.php';
    }

    public function getTitle(): string
    {
        return gettext('Missing Coordinates') . ' (' . $this->count . ')';
    }

    public function getDesc(): string
    {
        return gettext('Family Coordinates Data for Some Families');
    }
}
