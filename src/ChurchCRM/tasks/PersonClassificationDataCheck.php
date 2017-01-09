<?php

namespace ChurchCRM\Tasks;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;

class PersonClassificationDataCheck implements iTask
{
  private function dbHasMissingGenders()
  {
    $personQuery = PersonQuery::create()->filterByClsId(0) -> find();
    return  $personQuery->count() > 0;
  }

  public function isActive(){
    return $this->dbHasMissingGenders();
  }
  public function isAdmin(){
    return true;
  }
  public function getLink(){
    return SystemURLs::getRootPath() . '/SelectList.php?Classification=0';
  }
  public function getTitle(){
    return gettext('Missing Classification Data');
  }
  public function getDesc(){
    return gettext("Missing Classification Data for Some People");
  }

}
