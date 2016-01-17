<?php
/**
 * Created by IntelliJ IDEA.
 * User: georg
 * Date: 1/17/2016
 * Time: 8:02 AM
 */

class DashboardService
{

    function getFamilyStats() {
        $sSQL = "select
        (select count(*) from family_fam ) as familyCount
        from dual ;";
        $rsQuickStat = RunQuery($sSQL);
        $row = mysql_fetch_array($rsQuickStat);
        $data = ['familyCount' => $row['familyCount']];
        return $data;
    }


    function getPersonStats() {
        $sSQL = "select
        (select count(*) from person_per where per_cls_ID = 1  ) as PersonCount
        from dual ;";
        $rsQuickStat = RunQuery($sSQL);
        $row = mysql_fetch_array($rsQuickStat);
        $data = ['personCount' => $row['PersonCount'] ];
        return $data;
    }

    function getDemographic() {
        $stats = array();
        $sSQL = "select count(*) as numb, per_Gender, per_fmr_ID from person_per group by per_Gender, per_fmr_ID ;";
        $rsGenderAndRole = RunQuery($sSQL);
        while ($row = mysql_fetch_array($rsGenderAndRole)) {
            $role = "Unknown";
            $gender = "Unknown";
            if ($row['per_Gender'] == 1) {
                $gender = "male";
            } else if ($row['per_Gender'] == 2) {
                $gender = "female";
            } else {
                $gender = "Other";
            }

            if ($row['per_fmr_ID'] == 1) {
                $role = "Head of Household";
            } else if ($row['per_fmr_ID'] == 2) {
                $role = "Spouse";
            } else if ($row['per_fmr_ID'] == 3) {
                $role = "Child";
            } else {
                $role = "Other";
            }

            $stats["$role - $gender"] = $row['numb'];
        }
        return $stats;
    }

    function getSundaySchoolStats() {
        $sSQL = "select
        (select count(*) from group_grp where grp_Type = 4 ) as SundaySchoolClasses,
        (select count(*) from person_per,group_grp grp, person2group2role_p2g2r person_grp  where person_grp.p2g2r_rle_ID = 2 and per_cls_ID = 1 and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = per_ID) as SundaySchoolKidsCount
        from dual ;";
        $rsQuickStat = RunQuery($sSQL);
        $row = mysql_fetch_array($rsQuickStat);
        $data = ['classes' => $row['SundaySchoolClasses'],  'kids' => $row['SundaySchoolKidsCount'] ];
        return $data;
    }

}