<?php

namespace ChurchCRM\Service;

use ChurchCRM\FamilyQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\ListOptionQuery;
class DashboardService
{
    public function getFamilyCount()
    {
        $familyCount = FamilyQuery::Create()
            ->filterByDateDeactivated()
            ->count();
        $data = ['familyCount' => $familyCount];

        return $data;
    }

    public function getPersonCount()
    {
        $personCount = PersonQuery::Create('per')
            ->useFamilyQuery('fam','left join')
                ->filterByDateDeactivated(null)
            ->endUse()
            ->count();
        $data = ['personCount' => $personCount];

        return $data;
    }

    public function getPersonStats()
    {
        $data = [];
        $sSQL = 'select lst_OptionName as Classification, count(*) as count
                from person_per INNER JOIN list_lst ON  per_cls_ID = lst_OptionID
                INNER JOIN family_fam ON family_fam.fam_ID = person_per.per_fam_ID
                WHERE lst_ID =1 and family_fam.fam_DateDeactivated is null
                group by per_cls_ID, lst_OptionName order by count desc;';
        $rsClassification = RunQuery($sSQL);
        while ($row = mysqli_fetch_array($rsClassification)) {
            $data[$row['Classification']] = $row['count'];
        }

        return $data;
    }

    public function getDemographic()
    {
        $stats = [];
        $sSQL = 'select count(*) as numb, per_Gender, per_fmr_ID
                from person_per JOIN family_fam ON family_fam.fam_ID = person_per.per_fam_ID
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
          $role = 'Head of Household';
          break;
        case 2:
          $role = 'Spouse';
          break;
        case 3:
          $role = 'Child';
          break;
        default:
          $role = gettext('Other');
      }

            $stats["$role - $gender"] = $row['numb'];
        }

        return $stats;
    }

    public function getGroupStats()
    {
        $sSQL = 'select
        (select count(*) from group_grp) as Groups,
        (select count(*) from group_grp where grp_Type = 4 ) as SundaySchoolClasses,
        (select count(*) from person_per,group_grp grp, person2group2role_p2g2r person_grp, family_fam  where fam_ID =per_fam_ID and fam_DateDeactivated is  null and person_grp.p2g2r_rle_ID = 2 and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = per_ID) as SundaySchoolKidsCount
        from dual ;
        ';
        $rsQuickStat = RunQuery($sSQL);
        $row = mysqli_fetch_array($rsQuickStat);
        $data = ['groups' => $row['Groups'], 'sundaySchoolClasses' => $row['SundaySchoolClasses'], 'sundaySchoolkids' => $row['SundaySchoolKidsCount']];

        return $data;
    }
}
