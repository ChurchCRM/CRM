<?php

namespace ChurchCRM\Dashboard;

use ChurchCRM\Dashboard\DashboardItemInterface;

class PersonDemographicDashboardItem implements DashboardItemInterface {
  
  public static function getDashboardItemName() {
    return "PersonDemographics";
  }

  public static function getDashboardItemValue() {
     $stats = [];
        $sSQL = 'select count(*) as numb, per_Gender, per_fmr_ID
                from person_per LEFT JOIN family_fam ON family_fam.fam_ID = person_per.per_fam_ID
                where family_fam.fam_DateDeactivated is  null
                group by per_Gender, per_fmr_ID order by per_fmr_ID;';
        $rsGenderAndRole = RunQuery($sSQL);
        while ($row = mysqli_fetch_array($rsGenderAndRole)) {
            switch ($row['per_Gender']) {
        case 0:
          $gender = gettext('Unknown');
          break;
        case 1:
          $gender = gettext('Male');
          break;
        case 2:
          $gender = gettext('Female');
          break;
        default:
          $gender = gettext('Other');
      }

            switch ($row['per_fmr_ID']) {
        case 0:
          $role = gettext('Unknown');
          break;
        case 1:
          $role = gettext('Head of Household');
          break;
        case 2:
          $role = gettext('Spouse');
          break;
        case 3:
          $role = gettext('Child');
          break;
        default:
          $role = gettext('Other');
      }

            array_push($stats, array(
                    "key" => "$role - $gender",
                    "value" => $row['numb'],
                    "gender" => $row['per_Gender'],
                    "role" => $row['per_fmr_ID'])
            );
        }

        return $stats;
  }

  public static function shouldInclude($PageName) {
    return $PageName=="/PeopleDashboard.php"; // this ID would be found on all pages.
  }

}