<?php

namespace ChurchCRM\Service;

class SundaySchoolService
{
    /**
     * @return mixed[][]|array<'id'|'kids'|'name'|'teachers', string>[]
     */
    public function getClassStats(): array
    {
        $sSQL = 'select grp.grp_id, grp.grp_name, lst.lst_OptionName, count(*) as total
              from person_per,group_grp grp, person2group2role_p2g2r person_grp, list_lst lst
            where grp_Type = 4
              and grp.grp_ID = person_grp.p2g2r_grp_ID
              and person_grp.p2g2r_per_ID = per_ID
              and lst.lst_ID = grp.grp_RoleListID
              and lst.lst_OptionID = person_grp.p2g2r_rle_ID
              group by grp.grp_name, lst.lst_OptionName
              order by grp.grp_name, lst.lst_OptionName';
        $rsClassCounts = RunQuery($sSQL);
        $classInfo = [];
        $lastClassId = 0;
        $curClass = [];
        while ($row = mysqli_fetch_assoc($rsClassCounts)) {
            if ($lastClassId != $row['grp_id']) {
                if ($lastClassId != 0) {
                    $classInfo[] = $curClass;
                }
                $curClass = [];
                $curClass['id'] = $row['grp_id'];
                $curClass['name'] = $row['grp_name'];
            }
            if ($row['lst_OptionName'] === 'Teacher') {
                $curClass['teachers'] = $row['total'];
            } elseif ($row['lst_OptionName'] === 'Student') {
                $curClass['kids'] = $row['total'];
            }

            if ($lastClassId != $row['grp_id']) {
                $lastClassId = $row['grp_id'];
            }
        }
        if (!empty($curClass)) {
            $classInfo[] = $curClass;
        }

        return $classInfo;
    }

    /**
     * @return \non-empty-array<\string, \string>[]
     */
    public function getClassByRole(string $groupId, string $role): array
    {
        $sql = 'select person_per.*
              from person_per,group_grp grp, person2group2role_p2g2r person_grp, list_lst lst
            where grp.grp_ID = ' . $groupId . "
              and grp_Type = 4
              and grp.grp_ID = person_grp.p2g2r_grp_ID
              and person_grp.p2g2r_per_ID = per_ID
              and lst.lst_ID = grp.grp_RoleListID
              and lst.lst_OptionID = person_grp.p2g2r_rle_ID
              and lst.lst_OptionName = '" . $role . "'
            order by per_FirstName";
        $rsMembers = RunQuery($sql);
        $members = [];
        while ($row = mysqli_fetch_assoc($rsMembers)) {
            $members[] = $row;
        }

        return $members;
    }

    public function getKidsGender(string $groupId): array
    {
        $kids = $this->getClassByRole($groupId, 'Student');
        $boys = 0;
        $girls = 0;
        $unknown = 0;

        foreach ($kids as $kid) {
            switch ($kid['per_Gender']) {
                case 1:
                    $boys++;
                    break;
                case 2:
                    $girls++;
                    break;
                default:
                    $unknown++;
            }
        }

        return ['Boys' => $boys, 'Girls' => $girls, 'Unknown' => $unknown];
    }

    public function getKidsBirthdayMonth(string $groupId): array
    {
        $kids = $this->getClassByRole($groupId, 'Student');
        $Jan = 0;
        $Feb = 0;
        $Mar = 0;
        $Apr = 0;
        $May = 0;
        $June = 0;
        $July = 0;
        $Aug = 0;
        $Sept = 0;
        $Oct = 0;
        $Nov = 0;
        $Dec = 0;

        foreach ($kids as $kid) {
            switch ($kid['per_BirthMonth']) {
                case 1:
                    $Jan++;
                    break;
                case 2:
                    $Feb++;
                    break;
                case 3:
                    $Mar++;
                    break;
                case 4:
                    $Apr++;
                    break;
                case 5:
                    $May++;
                    break;
                case 6:
                    $June++;
                    break;
                case 7:
                    $July++;
                    break;
                case 8:
                    $Aug++;
                    break;
                case 9:
                    $Sept++;
                    break;
                case 10:
                    $Oct++;
                    break;
                case 11:
                    $Nov++;
                    break;
                case 12:
                    $Dec++;
                    break;
            }
        }

        return ['Jan'   => $Jan,
            'Feb'       => $Feb,
            'Mar'       => $Mar,
            'Apr'       => $Apr,
            'May'       => $May,
            'June'      => $June,
            'July'      => $July,
            'Aug'       => $Aug,
            'Sept'      => $Sept,
            'Oct'       => $Oct,
            'Nov'       => $Nov,
            'Dec'       => $Dec,
        ];
    }

    /**
     * @return \non-empty-array<\string, \string>[]
     */
    public function getKidsFullDetails(string $groupId): array
    {
        // Get all the groups
        $sSQL = 'select grp.grp_Name sundayschoolClass, kid.per_ID kidId, kid.per_Gender kidGender,
                kid.per_FirstName firstName, kid.per_Email kidEmail, kid.per_LastName LastName,
                  kid.per_BirthDay birthDay,  kid.per_BirthMonth birthMonth, kid.per_BirthYear birthYear,
                  kid.per_CellPhone mobilePhone, kid.per_Flags flags,

                fam.fam_HomePhone homePhone,fam.fam_id,

                dad.per_ID dadId, dad.per_FirstName dadFirstName, dad.per_LastName dadLastName,
                  dad.per_CellPhone dadCellPhone, dad.per_Email dadEmail,
                mom.per_ID momId, mom.per_FirstName momFirstName, mom.per_LastName momLastName, mom.per_CellPhone momCellPhone, mom.per_Email momEmail,
                fam.fam_Email famEmail, fam.fam_Address1 Address1, fam.fam_Address2 Address2, fam.fam_City city, fam.fam_State state, fam.fam_Zip zip

              from list_lst lst, person_per kid, family_fam fam
                left Join person_per dad on fam.fam_id = dad.per_fam_id and dad.per_Gender = 1 and ( dad.per_fmr_ID = 1 or dad.per_fmr_ID = 2)
                left join person_per mom on fam.fam_id = mom.per_fam_id and mom.per_Gender = 2 and (mom.per_fmr_ID = 1 or mom.per_fmr_ID = 2),`group_grp` grp, `person2group2role_p2g2r` person_grp

            where kid.per_fam_id = fam.fam_ID and grp.grp_ID = ' . $groupId . "
              and fam.fam_DateDeactivated is null
              and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = kid.per_ID
              and lst.lst_OptionID = person_grp.p2g2r_rle_ID and lst.lst_ID = grp.grp_RoleListID and lst.lst_OptionName = 'Student'

            order by grp.grp_Name, fam.fam_Name";

        $rsKids = RunQuery($sSQL);
        $kids = [];
        while ($row = mysqli_fetch_assoc($rsKids)) {
            $kids[] = $row;
        }

        return $kids;
    }

    /**
     * @return non-empty-array[]
     */
    public function getKidsWithoutClasses(): array
    {
        $sSQL = <<<'SQL'
select
    kid.per_ID kidId,
    kid.per_FirstName firstName,
    kid.per_LastName LastName,
    kid.per_BirthDay birthDay,
    kid.per_BirthMonth birthMonth,
    kid.per_BirthYear birthYear,
    kid.per_CellPhone mobilePhone,
    kid.per_Flags flags,
    fam.fam_Address1 Address1,
    fam.fam_Address2 Address2,
    fam.fam_City city,
    fam.fam_State state,
    fam.fam_Zip zip
from person_per kid, family_fam fam
where
    per_fam_id = fam.fam_ID and
    per_cls_ID in (1,2) and
    kid.per_fmr_ID = 3 and
    fam.fam_DateDeactivated is null and
    per_ID not in
        (
            select
                per_id
            from person_per,group_grp grp, person2group2role_p2g2r person_grp
            where
                person_grp.p2g2r_rle_ID = 2 and
                grp_Type = 4 and
                grp.grp_ID = person_grp.p2g2r_grp_ID and
                person_grp.p2g2r_per_ID = kid.per_ID
        )
SQL;
        $rsKidsMissing = RunQuery($sSQL);
        $kids = [];
        while ($row = mysqli_fetch_array($rsKidsMissing)) {
            $kids[] = $row;
        }

        return $kids;
    }
}
