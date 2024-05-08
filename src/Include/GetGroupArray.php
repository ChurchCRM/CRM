<?php

/**
 * @return non-empty-array[]
 */
function GetGroupArray(string $iGroupID): array
{
    // Get the group's role list ID
    $sSQL = 'SELECT grp_RoleListID,grp_hasSpecialProps FROM group_grp WHERE grp_ID =' . $iGroupID;
    $aTemp = mysqli_fetch_array(RunQuery($sSQL));
    $iRoleListID = $aTemp[0];

    // Get the members of the groups along with their family data
    $sSQL = "SELECT per_ID, per_FirstName, per_MiddleName, per_LastName, per_Title,
                   per_Suffix, per_Address1, per_Address2, per_City, per_State,
                   per_Zip, per_HomePhone, per_Country, per_Email, per_BirthMonth, per_BirthDay, per_BirthYear,
                   fam_ID, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, fam_HomePhone,
                   fam_Email, lst_OptionName
               FROM person_per
               LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
               LEFT JOIN list_lst ON p2g2r_rle_ID = lst_OptionID AND lst_ID = $iRoleListID
               LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
               LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
           WHERE p2g2r_grp_ID = " . $iGroupID . ' ORDER BY per_LastName, per_FirstName';
    $rsGroupMembers = RunQuery($sSQL);

    $ret = [];
    while ($aGroupMember = mysqli_fetch_array($rsGroupMembers)) {
        $ret[] = $aGroupMember;
    }

    return $ret;
}
