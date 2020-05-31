<?php

namespace ChurchCRM\Dashboard;

use ChurchCRM\Dashboard\DashboardItemInterface;

class GroupsDashboardItem implements DashboardItemInterface {

  public static function getDashboardItemName() {
    return "GroupsDisplay";
  }

  public static function getDashboardItemValue() {
       $sSQL = 'select
        (select count(*) from group_grp) as Group_cnt,
        (select count(*) from group_grp where grp_Type = 4 ) as SundaySchoolClasses,
        (Select count(*) from person_per
          INNER JOIN person2group2role_p2g2r ON p2g2r_per_ID = per_ID
          INNER JOIN group_grp ON grp_ID = p2g2r_grp_ID
          LEFT JOIN family_fam ON fam_ID = per_fam_ID
          where fam_DateDeactivated is  null and
	            p2g2r_rle_ID = 2 and grp_Type = 4) as SundaySchoolKidsCount
        from dual ;
        ';
        $rsQuickStat = RunQuery($sSQL);
        $row = mysqli_fetch_array($rsQuickStat);
        $data = ['groups' => $row['Group_cnt'] - $row['SundaySchoolClasses'], 'sundaySchoolClasses' => $row['SundaySchoolClasses'], 'sundaySchoolkids' => $row['SundaySchoolKidsCount']];

        return $data;
  }

  public static function shouldInclude($PageName) {
      return $PageName == "/Menu.php" || $PageName == "/menu";
  }

}