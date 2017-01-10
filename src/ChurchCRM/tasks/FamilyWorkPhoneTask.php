<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;


class FamilyWorkPhoneTask implements iTask
{

  public function isActive()
  {
    return FamilyQuery::create()->filterByWorkPhone("", Criteria::ALT_NOT_EQUAL)->find()->count() > 0;
  }

  public function isAdmin()
  {
    return false;
  }

  public function getLink()
  {
    return SystemURLs::getRootPath() . '/QueryView.php?QueryID=1';
  }

  public function getTitle()
  {
    return gettext('Family Work Phone');
  }

  public function getDesc()
  {
    return gettext('In Church CRM 2.6.0 we will no longer support a work phone for a family');
  }

}
