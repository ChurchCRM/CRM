<?php

namespace ChurchCRM\Dashboard;
use ChurchCRM\Dashboard\DashboardItemInterface;
use ChurchCRM\PersonQuery;

class PersonDashboardItem implements DashboardItemInterface {

  public static function getDashboardItemName() {
    return "PersonCount";
  }

  public static function getDashboardItemValue() {
     $personCount = PersonQuery::Create('per')
            ->useFamilyQuery('fam','left join')
                ->filterByDateDeactivated(null)
            ->endUse()
            ->count();
        $data = ['personCount' => $personCount];

        return $data;
  }
   /**
     * Return last edited members. Only from active families selected
     * @param int $limit
     * @return array|\ChurchCRM\Person[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public static function getUpdatedMembers($limit = 12)
    {
        return PersonQuery::create()
            ->leftJoinWithFamily()
            ->where('Family.DateDeactivated is null')
            ->orderByDateLastEdited('DESC')
            ->limit($limit)
            ->find();
    }

    /**
     * Newly added members. Only from Active families selected
     * @param int $limit
     * @return array|\ChurchCRM\Person[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public static function getLatestMembers($limit = 12)
    {
        return PersonQuery::create()
            ->leftJoinWithFamily()
            ->where('Family.DateDeactivated is null')
            ->filterByDateLastEdited(null)
            ->orderByDateEntered('DESC')
            ->limit($limit)
            ->find();
    }
  

  public static function shouldInclude($PageName) {
      return $PageName == "/Menu.php" || $PageName == "/menu";
  }

}