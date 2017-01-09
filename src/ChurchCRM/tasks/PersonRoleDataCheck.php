<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;

class PersonRoleDataCheck implements iTask
{

    private $count;

    private function dbHasMissingGenders()
    {
        $personQuery = PersonQuery::create()->filterByFmrId(0)->find();
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
        return SystemURLs::getRootPath() . '/SelectList.php?FamilyRole=0&PersonColumn3=Family+Role';
    }

    public function getTitle()
    {
        return gettext('Missing Role Data' . " (" . $this->count . ")");
    }

    public function getDesc()
    {
        return gettext("Missing Classification Data for Some People");
    }

}
