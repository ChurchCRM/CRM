<?php

namespace ChurchCRM\Tasks;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;

class PersonGenderDataCheck implements iTask
{
  private function dbHasMissingGenders()
  {
    $personQuery = PersonQuery::create()->filterByGender(0) -> find();
    return  $personQuery->count() > 0;
  }
  
  public function isActive(){
    return $this->dbHasMissingGenders();
  }
  public function isAdmin(){
    return true;
  }
  public function getLink(){
    return SystemURLs::getRootPath() . '/SelectList.php?mode=person';
  }
  public function getTitle(){
    return gettext('Missing Gender Data');
  }
  public function getDesc(){
    return gettext("Missing Gender Data for Some People");
  }

}
