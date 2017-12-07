<?php

namespace ChurchCRM\Dashboard;

use ChurchCRM\Dashboard\DashboardItemInterface;
use ChurchCRM\FamilyQuery;

class FamilyDashboardItem implements DashboardItemInterface {

  public static function getDashboardItemName() {
    return "FamilyCount";
  }

  public static function getDashboardItemValue() {

    $data = array('familyCount' => self::getCountFamilies(),
        'LatestFamilies' => self::getLatestFamilies(),
        'UpdatedFamilies' => self::getUpdatedFamilies()
        );
    


    return $data;
  }

  private static function getCountFamilies() {
    return FamilyQuery::Create()
                    ->filterByDateDeactivated()
                    ->count();
  }

  /**
   * //Return last edited families. only active families selected
   * @param int $limit
   * @return array|\ChurchCRM\Family[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
   */
  private static function getUpdatedFamilies($limit = 12) {
    return FamilyQuery::create()
                    ->filterByDateDeactivated(null)
                    ->orderByDateLastEdited('DESC')
                    ->limit($limit)
                    ->find()->toArray();
  }

  /**
   * Return newly added families. Only active families selected
   * @param int $limit
   * @return array|\ChurchCRM\Family[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
   */
  private static function getLatestFamilies($limit = 12) {

    return FamilyQuery::create()
                    ->filterByDateDeactivated(null)
                    ->filterByDateLastEdited(null)
                    ->orderByDateEntered('DESC')
                    ->limit($limit)
                    ->find()->toArray();
  }

  public static function shouldInclude($PageName) {
    return $PageName == true; // this ID would be found on all pages.
  }

}
