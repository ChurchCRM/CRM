<?php

namespace ChurchCRM\Service;

use ChurchCRM\FamilyQuery;
use ChurchCRM\PersonQuery;

class DashboardService
{
  public function getAgeStats(){
    $ageStats = [];
    $people = PersonQuery::create()->find();
    foreach($people as $person) {
        $personNumericAge = (int)$person->getNumericAge();
        if ($personNumericAge == 0)
        {
            continue;
        }
        if(!array_key_exists($personNumericAge,$ageStats)){
            $ageStats[$personNumericAge] = 0;
        }
        $ageStats[$personNumericAge]++;
    }
    ksort($ageStats);
    return $ageStats;
  }
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
                LEFT JOIN family_fam ON family_fam.fam_ID = person_per.per_fam_ID
                WHERE lst_ID =1 and family_fam.fam_DateDeactivated is null
                group by per_cls_ID, lst_OptionName order by count desc;';
        $rsClassification = RunQuery($sSQL);
        while ($row = mysqli_fetch_array($rsClassification)) {
            $data[$row['Classification']] = $row['count'];
        }

        return $data;
    }
    public function getGroupStats()
    {
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
        $data = ['groups' => $row['Group_cnt'], 'sundaySchoolClasses' => $row['SundaySchoolClasses'], 'sundaySchoolkids' => $row['SundaySchoolKidsCount']];

        return $data;
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
            ->orderByDateEntered('DESC')
            ->limit($limit)
            ->find();
    }
}
