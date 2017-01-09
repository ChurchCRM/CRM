<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;

class PersonGenderDataCheck implements iTask
{
    private $count;

    private function dbHasMissingGenders()
    {
        $personQuery = PersonQuery::create()->filterByGender(0)->find();
        $this->count = $personQuery->count();
        return $this->count > 0;
    }

    public function isActive()
    {
        return $this->dbHasMissingGenders();
    }

    public function isAdmin()
    {
        return true;
    }

    public function getLink()
    {
        return SystemURLs::getRootPath() . '/SelectList.php?Gender=0&PersonColumn3=Gender';
    }

    public function getTitle()
    {
        return gettext('Missing Gender Data' . " (" . $this->count . ")");
    }

    public function getDesc()
    {
        return gettext("Missing Gender Data for Some People");
    }

}
