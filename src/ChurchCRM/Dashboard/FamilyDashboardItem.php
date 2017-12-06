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
    return "
      document.getElementById('familyCountDashboard').innerText = data.familyCount;
      latestFamiliesTable = $('#latestFamiliesDashboardItem').DataTable({
        retrieve: true,
        responsive: true,
        paging: false,
        ordering: false,
        searching: false,
        scrollX: false,
        info: false,
        'columns': [
          {
            data:'Name',
            render: function ( data, type, row, meta ) {
              return '<a href='+window.CRM.root+'/FamilyView.php?FamilyID='+row.Id+'>'+data+'</a>';
            }
          },
          {data:'Address1'},
          {
            data:'DateEntered',
            render: function ( data, type, row, meta ) {
              return moment(data).format('MM-DD-YYYY hh:mm');
            }
          }
        ]
      });
      latestFamiliesTable.clear();
      latestFamiliesTable.rows.add(data.LatestFamilies);
      latestFamiliesTable.draw(true);
      
      updatedFamiliesTable = $('#updatedFamiliesDashboardItem').DataTable({
        retrieve: true,
        responsive: true,
        paging: false,
        ordering: false,
        searching: false,
        scrollX: false,
        info: false,
        'columns': [
          {
            data:'Name',
            render: function ( data, type, row, meta ) {
              return '<a href='+window.CRM.root+'/FamilyView.php?FamilyID='+row.Id+'>'+data+'</a>';
            }
          },
          {data:'Address1'},
          {
            data:'DateLastEdited',
            render: function ( data, type, row, meta ) {
              return moment(data).format('MM-DD-YYYY hh:mm');
            }
          }
        ]
      });
      updatedFamiliesTable.clear();
      updatedFamiliesTable.rows.add(data.UpdatedFamilies);
      updatedFamiliesTable.draw(true);
     ";
  }

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
