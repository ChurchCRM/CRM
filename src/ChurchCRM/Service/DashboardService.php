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
          $gender = 'Unknown';
          break;
        case 1:
          $gender = 'Male';
          break;
        case 2:
          $gender = 'Female';
          break;
        default:
          $gender = 'Other';
      }

            switch ($row['per_fmr_ID']) {
        case 0:
          $role = 'Unknown';
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
          $role = 'Other';
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

    /**
     * //Return last edited families. only active families selected
     * @param int $limit
     * @return array|\ChurchCRM\Family[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getUpdatedFamilies($limit = 12)
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
    public function getLatestFamilies($limit = 12)
    {

        return FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByDateLastEdited(null)
            ->orderByDateEntered('DESC')
            ->limit($limit)
            ->find();
    }

    /**
     * Return last edited members. Only from active families selected
     * @param int $limit
     * @return array|\ChurchCRM\Person[]|mixed|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getUpdatedMembers($limit = 12)
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
    public function getLatestMembers($limit = 12)
    {
        return PersonQuery::create()
            ->leftJoinWithFamily()
            ->where('Family.DateDeactivated is null')
            ->filterByDateLastEdited(null)
            ->orderByDateEntered('DESC')
            ->limit($limit)
            ->find();
    }
}
