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

}
