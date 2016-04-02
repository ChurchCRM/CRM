<?php

/**
 * Created by IntelliJ IDEA.
 * User: georg
 * Date: 1/17/2016
 * Time: 8:02 AM
 */
class DashboardService
{

  function getFamilyCount()
  {
    $sSQL = "select
        (select count(*) from family_fam ) as familyCount
        from dual ;";
    $rsQuickStat = RunQuery($sSQL);
    $row = mysql_fetch_array($rsQuickStat);
    $data = ['familyCount' => $row['familyCount']];
    return $data;
  }

  function getPersonCount()
  {
    $sSQL = "select
        (select count(*) from person_per ) as PersonCount
        from dual ;";
    $rsQuickStat = RunQuery($sSQL);
    $row = mysql_fetch_array($rsQuickStat);
    $data = ['personCount' => $row['PersonCount']];
    return $data;
  }


  function getPersonStats()
  {
    $data = array();
    $sSQL = "select lst_OptionName as Classification, count(*) as count from person_per, list_lst where per_cls_ID = lst_OptionID and lst_ID =1 group by per_cls_ID order by count desc;";
    $rsClassification = RunQuery($sSQL);
    while ($row = mysql_fetch_array($rsClassification)) {
      $data[$row['Classification']] = $row['count'];
    }
    return $data;
  }

  function getDemographic()
  {
    $stats = array();
    $sSQL = "select count(*) as numb, per_Gender, per_fmr_ID from person_per group by per_Gender, per_fmr_ID order by per_fmr_ID;";
    $rsGenderAndRole = RunQuery($sSQL);
    while ($row = mysql_fetch_array($rsGenderAndRole)) {
      switch ($row['per_Gender']) {
        case 0:
          $gender = "Unknown";
          break;
        case 1:
          $gender = "Male";
          break;
        case 2:
          $gender = "Female";
          break;
        default:
          $gender = "Other";
      }

      switch ($row['per_fmr_ID']) {
        case 0:
          $role = "Unknown";
          break;
        case 1:
          $role = "Head of Household";
          break;
        case 2:
          $role = "Spouse";
          break;
        case 3:
          $role = "Child";
          break;
        default:
          $role = "Other";
      }

      $stats["$role - $gender"] = $row['numb'];
    }
    return $stats;
  }

  function getGroupStats()
  {
    $sSQL = "select
        (select count(*) from group_grp) as Groups,
        (select count(*) from group_grp where grp_Type = 4 ) as SundaySchoolClasses,
        (select count(*) from person_per,group_grp grp, person2group2role_p2g2r person_grp  where person_grp.p2g2r_rle_ID = 2 and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = per_ID) as SundaySchoolKidsCount
        from dual ;";
    $rsQuickStat = RunQuery($sSQL);
    $row = mysql_fetch_array($rsQuickStat);
    $data = ['groups' => $row['Groups'], 'sundaySchoolClasses' => $row['SundaySchoolClasses'], 'sundaySchoolkids' => $row['SundaySchoolKidsCount']];
    return $data;
  }
}
