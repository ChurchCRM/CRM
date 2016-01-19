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
    
    function getGroupJSON($groups)
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
    
    function getGroupRoles($groupID)
    {
        $groupRoles = array();
		$sSQL = "SELECT grp_RoleListID FROM group_grp WHERE grp_ID = " . $groupID;
		$rsTemp = RunQuery($sSQL);

		// Validate that this list ID is really for a group roles list. (for security)
		if (mysql_num_rows($rsTemp) == 0) {
            throw new Exception("invalid request");
		}

		$grp_RoleListID = mysql_fetch_array($rsTemp);
        
        $sSQL = "SELECT lst_OptionName, lst_OptionID, lst_OptionSequence FROM list_lst WHERE lst_ID=$grp_RoleListID[0] ORDER BY lst_OptionSequence";
        $rsList = RunQuery($sSQL);
        while ($row = mysql_fetch_assoc($rsList)) {
            array_push($groupRoles, $row);
        }
		return $groupRoles;

    }
    
    function setGroupRoleName($groupID,$groupRoleID,$groupRoleName)
    {
        $sSQL =  'UPDATE list_lst
                 INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID 
                 SET list_lst.lst_OptionName = "'.$groupRoleName.'"
                 WHERE group_grp.grp_ID = "'.$groupID.'"
                    AND list_lst.lst_OptionID = '.$groupRoleID;
        RunQuery($sSQL);
        
    }
    
    function setGroupRoleOrder($groupID,$groupRoleID,$groupRoleOrder)
    {
        $sSQL =  'UPDATE list_lst
                 INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID 
                 SET list_lst.lst_OptionSequence = "'.$groupRoleOrder.'"
                 WHERE group_grp.grp_ID = "'.$groupID.'"
                    AND list_lst.lst_OptionID = '.$groupRoleID;
        RunQuery($sSQL);
        
    }
    
    function getGroupDefaultRole($groupID)
    {
        //Look up the default role name
        $sSQL = "SELECT lst_OptionName from list_lst INNER JOIN group_grp on (group_grp.grp_RoleListID = list_lst.lst_ID AND group_grp.grp_DefaultRole = list_lst.lst_OptionID) WHERE group_grp.grp_ID = " . $groupID;
        $aDefaultRole = mysql_fetch_array(RunQuery($sSQL));
        return $aDefaultRole[0];       
    }
    function deleteGroupRole($groupID,$groupRoleID)
    {
        $sSQL = 'SELECT \'\' FROM list_lst 
                INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID 
                 WHERE group_grp.grp_ID = "'.$groupID.'"';
		$rsPropList = RunQuery($sSQL);
		$numRows = mysql_num_rows($rsPropList);
        
		// Make sure we never delete the only option
		if ($numRows > 1)
		{
			$sSQL = 'DELETE list_lst.* FROM list_lst 
                    INNER JOIN group_grp
                        ON group_grp.grp_RoleListID = list_lst.lst_ID 
                    WHERE group_grp.grp_ID = "'.$groupID.'"
                    AND lst_OptionID = '.$groupRoleID;
            
			RunQuery($sSQL);
        
            //Shift the remaining rows up by one
			
			$sSQL = 'UPDATE list_lst
                    INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID 
                    SET list_lst.lst_OptionID = list_lst.lst_OptionID -1,
                        list_lst.lst_OptionSequence = list_lst.lst_OptionSequence -1
                    WHERE group_grp.grp_ID ='.$groupID.'
                    AND list_lst.lst_OptionID >= '.$groupRoleID;
            
			RunQuery($sSQL);


			//check if we've deleted the old group default role.  If so, reset default to role ID 1
			// Next, if any group members were using the deleted role, reset their role to the group default.

            // Reset if default role was just removed.
            $sSQL = "UPDATE group_grp SET grp_DefaultRole = 1 WHERE grp_ID = $groupID AND grp_DefaultRole = $groupRoleID";
            RunQuery($sSQL);

            // Get the current default role and Group ID (so we can update the p2g2r table)
            // This seems backwards, but grp_RoleListID is unique, having a 1-1 relationship with grp_ID.
            $sSQL = "SELECT grp_ID,grp_DefaultRole FROM group_grp WHERE grp_ID = $groupID";
            $rsTemp = RunQuery($sSQL);
            $aTemp = mysql_fetch_array($rsTemp);

            $sSQL = "UPDATE person2group2role_p2g2r SET p2g2r_rle_ID = 1 WHERE p2g2r_grp_ID = $groupID AND p2g2r_rle_ID = $groupRoleID";
            RunQuery($sSQL);
			
		}
		else
        {
            throw new Exception("You cannont delete the only group");
        }
    }
    function addGroupRole($groupID,$groupRoleName)
    {
        if (strlen($groupRoleName) == 0)
        {
            throw new Exception ("New field name cannot be blank");
        }
        else
        {
            // Check for a duplicate option name
            $sSQL = 'SELECT \'\' FROM list_lst 
                INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID 
                 WHERE group_grp.grp_ID = "'.$groupID.'" AND
                 lst_OptionName = "' . $groupRoleName .'"';
            $rsCount = RunQuery($sSQL);
            if (mysql_num_rows($rsCount) > 0)
            {
                throw new Exception ("Field ".$groupRoleName." already exists");
            }
            else
            {
                $sSQL = "SELECT grp_RoleListID FROM group_grp WHERE grp_ID = $groupID";
                $rsTemp = RunQuery($sSQL);
                $listIDTemp = mysql_fetch_array($rsTemp);
                $listID = $listIDTemp[0];
                // Get count of the options
                $sSQL = "SELECT '' FROM list_lst WHERE lst_ID = $listID";
                $rsTemp = RunQuery($sSQL);
                $numRows = mysql_num_rows($rsTemp);
                $newOptionSequence = $numRows + 1;

                // Get the new OptionID
                $sSQL = "SELECT MAX(lst_OptionID) FROM list_lst WHERE lst_ID = $listID";
                $rsTemp = RunQuery($sSQL);
                $aTemp = mysql_fetch_array($rsTemp);
                $newOptionID = $aTemp[0] + 1;

                // Insert into the appropriate options table
                $sSQL = "INSERT INTO list_lst (lst_ID, lst_OptionID, lst_OptionName, lst_OptionSequence)
                        VALUES (" . $listID . "," . $newOptionID . ",'" . $groupRoleName . "'," . $newOptionSequence . ")";

                RunQuery($sSQL);
                $iNewNameError = 0;
            }
        }
        return '{"newRole":{"roleID":"'.$newOptionID.'", "roleName":"'.$groupRoleName.'", "sequence":"'.$newOptionSequence.'"}}';
    }
    
    function getGroupTotalMembers($groupID)
    {
        //Get the count of members
        $sSQL = 'SELECT COUNT(*) AS iTotalMembers FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = ' . $groupID;
        $rsTotalMembers = mysql_fetch_array(RunQuery($sSQL));
        return $rsTotalMembers[0];
        
    }
    
    function getGroupByID($groupID)
    {        
        $fetch = 'SELECT * FROM group_grp WHERE grp_ID = '.$groupID;
        $result = mysql_query($fetch);
        if (mysql_num_rows($result) == 0) {
            throw new Exception("no such group");
		}

        $group = mysql_fetch_assoc($result);
        $group['defaultRole'] = $this->getGroupDefaultRole($groupID);
        $group['uri'] = $this->getViewURI($groupID);
        $group['roles']=$this->getGroupRoles($groupID);
        $group['totalMembers']=$this->getGroupTotalMembers($groupID);

        return $group;
        
    }
    
    function getGroupTypes()
    {
        $groupTypes = array();
        // Get Group Types for the drop-down
        $sSQL = "SELECT * FROM list_lst WHERE lst_ID = 3 ORDER BY lst_OptionSequence";
        $rsGroupTypes = RunQuery($sSQL);
        while ($aRow = mysql_fetch_assoc($rsGroupTypes))
        {
           array_push($groupTypes,$aRow);
        }
        return $groupTypes;
    }
    
    function setGroupRoleAsDefault($groupID,$roleID)
    {
        $sSQL = "UPDATE group_grp SET grp_DefaultRole = ".$roleID." WHERE grp_ID = ".$groupID;
		RunQuery($sSQL);
    }

    function getGroupRoleTemplateGroups()
    {
        $templateGroups = array();
        $sSQL = "SELECT * FROM group_grp WHERE grp_RoleListID > 0 ORDER BY grp_Name";
        $rsGroupRoleSeed = RunQuery($sSQL);
        while ($aRow = mysql_fetch_assoc($rsGroupRoleSeed))
        {
           array_push($templateGroups,$aRow);
        }
        return $templateGroups;
    }

    function setGroupName($groupID,$groupName)
    {
        
        
    }
    
    function updateGroup($groupID,$groupData)
    {

        //Assign everything locally
        $thisGroup['grp_Name'] = $groupData->groupName;
        $thisGroup['grp_type'] = $groupData->groupType;
        $thisGroup['grp_Description'] = $groupData->description;
        $bUseGroupProps = FilterInputArr($_POST,"UseGroupProps");
        $cloneGroupRole = FilterInputArr($_POST,"cloneGroupRole",'int');
        $seedGroupID = FilterInputArr($_POST,"seedGroupID",'int');

        //Did they enter a Name?
        if (strlen($thisGroup['grp_Name']) < 1)
        {
            throw new Exception("You must enter a name");
        }

        $sSQL = "UPDATE group_grp SET grp_Name='" . $thisGroup['grp_Name'] . "', grp_Type='" . $thisGroup['grp_type'] . "', grp_Description='" . $thisGroup['grp_Description'] . "'";
        
        $sSQL .= " WHERE grp_ID = " . $groupID;
        // execute the SQL
        RunQuery($sSQL);
        return '{"success":"true"}';
    }

    function copyCartToGroup()
    {
        if (array_key_exists ("EmptyCart", $_POST) && $_POST["EmptyCart"] && count($_SESSION['aPeopleCart']) > 0)
        {
            $iCount = 0;
            while ($element = each($_SESSION['aPeopleCart'])) {
                $groupService->AddUsertoGroup($_SESSION['aPeopleCart'][$element['key']],$iGroupID,$thisGroup['grp_DefaultRole']);
                $iCount += 1;
            }

            $sGlobalMessage = $iCount . " records(s) successfully added to selected Group.";

            Redirect("GroupEditor.php?GroupID=" . $iGroupID . "&Action=EmptyCart");
        }
        else
        {
            Redirect("GroupEditor.php?GroupID=$iGroupID");
        }
    }
   
    function enableGroupSpecificProperties()
    {
         $sSQLtest = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $iGroupID;
        $rstest = RunQuery($sSQLtest);
        $aRow = mysql_fetch_array($rstest);

        $bCreateGroupProps = ($aRow[0] == 'false') && $bUseGroupProps;
        $bDeleteGroupProps = ($aRow[0] == 'true') && !$bUseGroupProps;

            if ($bCreateGroupProps)
                $sSQL .= ", grp_hasSpecialProps = 'true'";

            if ($bDeleteGroupProps)
            {
                $sSQL .= ", grp_hasSpecialProps = 'false'";
                $sSQLp = "DROP TABLE groupprop_" . $iGroupID;
                RunQuery($sSQLp);

                // need to delete the master index stuff
                $sSQLp = "DELETE FROM groupprop_master WHERE grp_ID = " . $iGroupID;
                RunQuery($sSQLp);
            }
            
        // Create a table for group-specific properties
        if ( $bCreateGroupProps )
        {
            $sSQLp = "CREATE TABLE groupprop_" . $iGroupID . " (
                        per_ID mediumint(8) unsigned NOT NULL default '0',
                        PRIMARY KEY  (per_ID),
                          UNIQUE KEY per_ID (per_ID)
                        ) ENGINE=MyISAM;";
            RunQuery($sSQLp);

            // If this is an existing group, add rows in this table for each member
            if ( !$bGetKeyBack )
            {
                $sSQL = "SELECT per_ID FROM person_per INNER JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID WHERE p2g2r_grp_ID = " . $iGroupID . " ORDER BY per_ID";
                $rsGroupMembers = RunQuery($sSQL);

                while ($aRow = mysql_fetch_array($rsGroupMembers))
                {
                    $sSQLr = "INSERT INTO groupprop_" . $iGroupID . " ( `per_ID` ) VALUES ( '" . $aRow[0] . "' );";
                    RunQuery($sSQLr);
                }
            }
        }



    }
    
    function addNewGroup()
    {
        if (strlen($iGroupID) < 1)
        {
            //Get a new Role List ID
            $sSQL = "SELECT MAX(lst_ID) FROM list_lst";
            $aTemp = mysql_fetch_array(RunQuery($sSQL));
            if ($aTemp[0] > 9)
                $newListID = $aTemp[0] + 1;
            else
                $newListID = 10;

            if ($bUseGroupProps)
                $sUseProps = 'true';
            else
                $sUseProps = 'false';
            $sSQL = "INSERT INTO group_grp (grp_Name, grp_Type, grp_Description, grp_hasSpecialProps, grp_DefaultRole, grp_RoleListID) VALUES ('" . $thisGroup['grp_Name'] . "', " . $thisGroup['grp_type'] . ", '" . $thisGroup['grp_Description'] . "', '" . $sUseProps . "', '1', " . $newListID . ")";

            $bGetKeyBack = True;
            $bCreateGroupProps = $bUseGroupProps;
        }
        
        //If the user added a new record, we need to key back to the route to the GroupView page
        if ($bGetKeyBack)
        {
            //Get the key back
            $iGroupID = mysql_insert_id($cnInfoCentral);

            if (($cloneGroupRole) && ($seedGroupID>0)) {
                $sSQL = "SELECT list_lst.* FROM list_lst, group_grp WHERE group_grp.grp_RoleListID = list_lst.lst_ID AND group_grp.grp_id = $seedGroupID ORDER BY list_lst.lst_OptionID";
                $rsRoleSeed = RunQuery($sSQL);
                while ($aRow = mysql_fetch_array($rsRoleSeed))
                {
                    extract ($aRow);
                    $useOptionName = mysql_real_escape_string($lst_OptionName);
                    $sSQL = "INSERT INTO list_lst VALUES ($newListID, $lst_OptionID, $lst_OptionSequence, '$useOptionName')";
                    RunQuery($sSQL);
                }
            } else 
            {
                $sSQL = "INSERT INTO list_lst VALUES ($newListID, 1, 1,'Member')";
                RunQuery($sSQL);
            }
        }
       
    }
}

?>
