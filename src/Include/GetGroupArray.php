<?php

/*******************************************************************************
 *
 *  filename    : Include/GetGroupArray.php
 *  last change : 2003-08-30
 *  description : Get information about group members into an array
 *
 ******************************************************************************/
/**
 * @return non-empty-array[]
 */
function GetGroupArray(string $iGroupID): array
{
    //Get the Properties assigned to this Group
    $sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
		   FROM record2property_r2p
		   LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
		   LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
		   WHERE pro_Class = 'g' AND r2p_record_ID = " . $iGroupID .
    ' ORDER BY prt_Name, pro_Name';
    $rsAssignedProperties = RunQuery($sSQL);

    // Get the group's role list ID
    $sSQL = 'SELECT grp_RoleListID,grp_hasSpecialProps FROM group_grp WHERE grp_ID =' . $iGroupID;
    $aTemp = mysqli_fetch_array(RunQuery($sSQL));
    $iRoleListID = $aTemp[0];
    $bHasSpecialProps = ($aTemp[1] == 'true');

    // Get the roles
    $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = ' . $iRoleListID . ' ORDER BY lst_OptionSequence';
    $rsRoles = RunQuery($sSQL);
    $numRoles = mysqli_num_rows($rsRoles);

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
