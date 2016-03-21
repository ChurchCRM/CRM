<?php

class SundaySchoolService
{

  function getClassByRole($groupId, $role)
  {
    $sql = "select person_per.*
              from person_per,`group_grp` grp, `person2group2role_p2g2r` person_grp, list_lst lst
            where grp.grp_ID = " . $groupId . "
              and grp_Type = 4
              and grp.grp_ID = person_grp.p2g2r_grp_ID
              and person_grp.p2g2r_per_ID = per_ID
              and lst.lst_ID = grp.grp_RoleListID
              and lst.lst_OptionID = person_grp.p2g2r_rle_ID
              and lst.lst_OptionName = '" . $role . "'
            order by per_FirstName";
    $rsMembers = RunQuery($sql);
    $members = array();
    while ($row = mysql_fetch_assoc($rsMembers)) {
      array_push($members, $row);
    }
    return $members;
  }

  function getKidsGender($groupId)
  {
    $kids = $this->getClassByRole($groupId, "Student");
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
    return array('Boys' => $boys, 'Girls' => $girls, 'Unknown' => $unknown);
  }

  function getKidsBirthdayMonth($groupId)
  {
    $kids = $this->getClassByRole($groupId, "Student");
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
    return array('Jan' => $Jan,
      'Feb' => $Feb,
      'Mar' => $Mar,
      'Apr' => $Apr,
      'May' => $May,
      'June' => $June,
      'July' => $July,
      'Aug' => $Aug,
      'Sept' => $Sept,
      'Oct' => $Oct,
      'Nov' => $Nov,
      'Dec' => $Dec
    );
  }

  function getKidsFullDetails($groupId)
  {
    // Get all the groups
    $sSQL = "select grp.grp_Name sundayschoolClass, kid.per_ID kidId, kid.per_Gender kidGender, kid.per_FirstName firstName, kid.per_Email kidEmail, kid.per_LastName LastName, kid.per_BirthDay birthDay,  kid.per_BirthMonth birthMonth, kid.per_BirthYear birthYear, kid.per_CellPhone mobilePhone,
                fam.fam_HomePhone homePhone,

                dad.per_ID dadId, dad.per_FirstName dadFirstName, dad.per_LastName dadLastName, dad.per_CellPhone dadCellPhone, dad.per_Email dadEmail,
                mom.per_ID momId, mom.per_FirstName momFirstName, mom.per_LastName momLastName, mom.per_CellPhone momCellPhone, mom.per_Email momEmail,
                fam.fam_Email famEmail, fam.fam_Address1 Address1, fam.fam_Address2 Address2, fam.fam_City city, fam.fam_State state, fam.fam_Zip zip

              from list_lst lst, person_per kid, family_fam fam
                left Join person_per dad on fam.fam_id = dad.per_fam_id and dad.per_Gender = 1 and dad.per_fmr_ID = 1
                left join person_per mom on fam.fam_id = mom.per_fam_id and mom.per_Gender = 2 and mom.per_fmr_ID = 2,`group_grp` grp, `person2group2role_p2g2r` person_grp

            where kid.per_fam_id = fam.fam_ID and grp.grp_ID = " . $groupId . "
              and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = kid.per_ID
              and lst.lst_OptionID = person_grp.p2g2r_rle_ID and lst.lst_ID = grp.grp_RoleListID and lst.lst_OptionName = 'Student'

            order by grp.grp_Name, fam.fam_Name";

    $rsKids = RunQuery($sSQL);
    $kids = array();
    while ($row = mysql_fetch_assoc($rsKids)) {
      array_push($kids, $row);
    }
    return $kids;
  }

}
