<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\Functions;
use Propel\Runtime\ActiveQuery\Criteria;

const GENDER_STATS_UNASSIGNED = 0;
const GENDER_STATS_MAN = 1;
const GENDER_STATS_WOMAN = 2;
const GENDER_STATS_BOY = 3;
const GENDER_STATS_GIRL = 4;

class DashboardService
{
    public function getFamilyCount(): array
    {
        $familyCount = FamilyQuery::create()
            ->filterByDateDeactivated()
            ->count();

        return ['familyCount' => $familyCount];
    }

    public function getDashboardStats(): array
    {
        $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');
        if ($sInactiveClassificationIds === '') {
            $sInactiveClassificationIds = '-1';
        }
        $aInactiveClassificationIds = explode(',', $sInactiveClassificationIds);
        $aDirRoleChild = explode(',', SystemConfig::getValue('sDirRoleChild'));

        $personCount = 0;
        $ageStats = [];
        $classificationStats = [];
        $genderStats = [GENDER_STATS_UNASSIGNED => 0, GENDER_STATS_MAN => 0, GENDER_STATS_WOMAN => 0, GENDER_STATS_BOY => 0, GENDER_STATS_GIRL => 0];
        $familyRoleStats = [];

        $people = PersonQuery::Create('per')
            ->filterByClsId($aInactiveClassificationIds, Criteria::NOT_IN)
            ->useFamilyQuery('fam', 'left join')
            ->filterByDateDeactivated(null)
            ->endUse();

        foreach ($people as $person) {
            $personCount++;

            $classification = $person->getClassificationName();
            if (!array_key_exists($classification, $classificationStats)) {
                $classificationStats[$classification]['count'] = 0;
                $classificationStats[$classification]['id'] = $person->getClsId();
            }
            $classificationStats[$classification]['count']++;

            $gender = $person->getGender();
            if ($gender !== GENDER_STATS_UNASSIGNED && in_array($person->getFmrId(), $aDirRoleChild)) {
                $gender = $gender + 2;
            }
            $genderStats[$gender]++;

            $numericAge = (int) $person->getNumericAge();
            if ($numericAge !== 0) {
                if (!array_key_exists($numericAge, $ageStats)) {
                    $ageStats[$numericAge] = 0;
                }
                $ageStats[$numericAge]++;
            }

            $familyRoleGender = $person->getFamilyRoleName() . ' - ' . $person->getGenderName();
            if (!array_key_exists($familyRoleGender, $familyRoleStats)) {
                $familyRoleStats[$familyRoleGender]['count'] = 0;
                $familyRoleStats[$familyRoleGender]['genderId'] = $person->getGender();
                $familyRoleStats[$familyRoleGender]['roleId'] = $person->getFmrId();
            }
            $familyRoleStats[$familyRoleGender]['count']++;
        }
        ksort($genderStats);
        ksort($ageStats);
        array_multisort(
            array_column($classificationStats, 'id'),
            SORT_ASC,
            $classificationStats
        );
        array_multisort(
            array_column($familyRoleStats, 'roleId'),
            SORT_ASC,
            array_column($familyRoleStats, 'genderId'),
            SORT_ASC,
            $familyRoleStats
        );

        return ['personCount' => $personCount, 'classificationStats' => $classificationStats, 'genderStats' => $genderStats, 'ageStats' => $ageStats, 'familyRoleStats' => $familyRoleStats];
    }

    public function getGroupStats(): array
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
        $rsQuickStat = Functions::runQuery($sSQL);
        $row = mysqli_fetch_array($rsQuickStat);

        return ['groups' => $row['Group_cnt'], 'sundaySchoolClasses' => $row['SundaySchoolClasses'], 'sundaySchoolkids' => $row['SundaySchoolKidsCount']];
    }

    /**
     * Return last edited members. Only from active families selected.
     *
     * @param int $limit
     *
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
     * Newly added members. Only from Active families selected.
     *
     * @param int $limit
     *
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
