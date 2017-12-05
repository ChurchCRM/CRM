<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ChurchCRM\Dashboard;
use ChurchCRM\Dashboard\DashboardItemInterface;
use ChurchCRM\FamilyQuery;

class FamilyDashboardItem implements DashboardItemInterface {
  
  public static function getDashboardItemRenderer() {
    return "#FamilyCount";
  }

  public static function getDashboardItemName() {
    return "Family Count";
  }

  public static function getDashboardItemValue() {
     $familyCount = FamilyQuery::Create()
            ->filterByDateDeactivated()
            ->count();
        $data = ['familyCount' => $familyCount];

        return $data;
  }
  
   /**
     * //Return last edited families. only active families selected
     * @param int $limit
     * @return array|\ChurchCRM\Family[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public static function getUpdatedFamilies($limit = 12)
    {
        return FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->orderByDateLastEdited('DESC')
            ->limit($limit)
            ->find();

    }

    /**
     * Return newly added families. Only active families selected
     * @param int $limit
     * @return array|\ChurchCRM\Family[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public static function getLatestFamilies($limit = 12)
    {

        return FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByDateLastEdited(null)
            ->orderByDateEntered('DESC')
            ->limit($limit)
            ->find();
    }
  
  public static function shouldInclude($PageName) {
    return $PageName=="index.php"; // this ID would be found on all pages.
  }

}