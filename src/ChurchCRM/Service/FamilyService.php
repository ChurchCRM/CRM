<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\FamilyQuery;

class FamilyService
{
    /**
     * Get count of families missing coordinate data.
     *
     * @return int Count of families with empty Latitude field
     */
    public function getMissingCoordinatesCount(): int
    {
        return FamilyQuery::create()
            ->filterByLatitude('')
            ->count();
    }
}
