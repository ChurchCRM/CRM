<?php


class GroupService 
{

    private $baseURL;

    public function __construct() {
        $this->baseURL = $_SESSION['sURLPath'];
    }

    function removeUserFromGroup($iPersonID, $iGroupID)
    {
        $sSQL = "DELETE FROM person2group2role_p2g2r WHERE p2g2r_per_ID = " . $iPersonID . " AND p2g2r_grp_ID = " . $iGroupID;
        RunQuery($sSQL);

        // Check if this group has special properties
        $sSQL = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $iGroupID;
        $rsTemp = RunQuery($sSQL);
        $rowTemp = mysql_fetch_row($rsTemp);
        $bHasProp = $rowTemp[0];

        if ($bHasProp == 'true')
        {
            $sSQL = "DELETE FROM groupprop_" . $iGroupID . " WHERE per_ID = '" . $iPersonID . "'";
            RunQuery($sSQL);
        }

        // Reset any group specific property fields of type "Person from Group" with this person assigned
        $sSQL = "SELECT grp_ID, prop_Field FROM groupprop_master WHERE type_ID = 9 AND prop_Special = " . $iGroupID;
        $result = RunQuery($sSQL);
        while ($aRow = mysql_fetch_array($result))
        {
            $sSQL = "UPDATE groupprop_" . $aRow['grp_ID'] . " SET " . $aRow['prop_Field'] . " = NULL WHERE " . $aRow['prop_Field'] . " = " . $iPersonID;
            RunQuery($sSQL);
        }

        // Reset any custom person fields of type "Person from Group" with this person assigned
        $sSQL = "SELECT custom_Field FROM person_custom_master WHERE type_ID = 9 AND custom_Special = " . $iGroupID;
        $result = RunQuery($sSQL);
        while ($aRow = mysql_fetch_array($result))
        {
            $sSQL = "UPDATE person_custom SET " . $aRow['custom_Field'] . " = NULL WHERE " . $aRow['custom_Field'] . " = " . $iPersonID;
            RunQuery($sSQL);
        }
    }

    function addUserToGroup($iPersonID, $iGroupID, $iRoleID)
    {
    //
// Adds a person to a group with specified role.
// Returns false if the operation fails. (such as person already in group)
//
    global $cnInfoCentral;

    // Was a RoleID passed in?
    if ($iRoleID == 0) {
        // No, get the Default Role for this Group
        $sSQL = "SELECT grp_DefaultRole FROM group_grp WHERE grp_ID = " . $iGroupID;
        $rsRoleID = RunQuery($sSQL);
        $Row = mysql_fetch_row($rsRoleID);
        $iRoleID = $Row[0];
    }

    $sSQL = "INSERT INTO person2group2role_p2g2r (p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID) VALUES (" . $iPersonID . ", " . $iGroupID . ", " . $iRoleID . ")";
    $result = RunQuery($sSQL,false);

    if ($result)
    {
        // Check if this group has special properties
        $sSQL = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $iGroupID;
        $rsTemp = RunQuery($sSQL);
        $rowTemp = mysql_fetch_row($rsTemp);
        $bHasProp = $rowTemp[0];

        if ($bHasProp == 'true')
        {
            $sSQL = "INSERT INTO groupprop_" . $iGroupID . " (per_ID) VALUES ('" . $iPersonID . "')";
            RunQuery($sSQL);
        }
    }

    return $result;
}
    
    function search($searchTerm)
    {
        
        $fetch = 'SELECT grp_ID, grp_Type, grp_Name, grp_Description,  lst_OptionName FROM group_grp LEFT JOIN list_lst on lst_ID = 3 AND lst_OptionID = grp_Type WHERE grp_Name LIKE \'%' . $searchTerm . '%\' OR  grp_Description LIKE \'%' . $searchTerm . '%\' OR lst_OptionName LIKE \'%'.$searchTerm.'%\'  order by grp_Name LIMIT 15';
        $result = mysql_query($fetch);

        $return = array();
        while ($row = mysql_fetch_array($result)) {
            $values['id'] = $row['grp_ID'];
            $values['groupName'] = $row['grp_Name'];
            $values['displayName'] = $row['grp_Name'];
            $values['groupType'] = $row['lst_OptionName'];
            $values['groupDescription'] = $row['grp_Description'];
            $values['uri'] = $this->getViewURI($row['grp_ID']);

            array_push($return, $values);
        }

        return $return;
        
    }
    
    function getGroupsJSON($groups)
    {
        if ($groups)
        {
            return '{"groups": ' . json_encode($groups) . '}';
        }
        else
        {
              return false;
        }
    }
    
    function getViewURI($Id)
    {
        return $this->baseURL ."/GroupView.php?GroupID=".$Id;
    }

    function deleteGroup($iGroupID)
    {
        $sSQL = "SELECT grp_hasSpecialProps, grp_RoleListID FROM group_grp WHERE grp_ID =" . $iGroupID;
        $rsTemp = RunQuery($sSQL);
        $aTemp = mysql_fetch_array($rsTemp);
        $hasSpecialProps = $aTemp[0];
        $iRoleListID = $aTemp[1];

            //Delete all Members of this group
            $sSQL = "DELETE FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = " . $iGroupID;
            RunQuery($sSQL);

            //Delete all Roles for this Group
            $sSQL = "DELETE FROM list_lst WHERE lst_ID = " . $iRoleListID;
            RunQuery($sSQL);

            // Remove group property data
            $sSQL = "SELECT pro_ID FROM property_pro WHERE pro_Class='g'";
            $rsProps = RunQuery($sSQL);

            while($aRow = mysql_fetch_row($rsProps)) {
                $sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $aRow[0] . " AND r2p_record_ID = " . $iGroupID;
                RunQuery($sSQL);
            }

            if ($hasSpecialProps == 'true')
            {
                // Drop the group-specific properties table and all references in the master index
                $sSQL = "DROP TABLE groupprop_" . $iGroupID;
                RunQuery($sSQL);

                $sSQL = "DELETE FROM groupprop_master WHERE grp_ID = " . $iGroupID;
                RunQuery($sSQL);
            }

            //Delete the Group
            $sSQL = "DELETE FROM group_grp WHERE grp_ID = " . $iGroupID;
            RunQuery($sSQL);

    }
    
}

?>
