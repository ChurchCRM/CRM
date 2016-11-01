<?php

namespace ChurchCRM\Service;

use ChurchCRM\PersonQuery;

class GroupService
{

  private $baseURL;

  public function __construct()
  {
    $this->baseURL = $_SESSION['sRootPath'];
  }

  /**
   *  setGroupMemberRole
   * @param  int $groupID group id in which to set the member's role
   * @param  int $personID ID of the person who'se role to set
   * @param  int $roleID Role ID to set to the person
   *  return array containing the group member
   */
  function setGroupMemberRole($groupID, $personID, $roleID)
  {
    requireUserGroupMembership("bManageGroups");
    $sSQL = "UPDATE person2group2role_p2g2r
            SET p2g2r_rle_ID = " . $roleID . "
            WHERE
            p2g2r_per_ID =" . $personID . "
            AND
             p2g2r_grp_ID =" . $groupID;

    $update = RunQuery($sSQL);
    return $this->getGroupMembers($groupID, $personID);
  }

  /**
   *  removeUserFromGroup
   * @param  int $groupID Group ID from which  to remove the user
   * @param  int $personID UserID to remove from the group
   */
  function removeUserFromGroup($groupID, $personID)
  {
    requireUserGroupMembership("bManageGroups");
    $sSQL = "DELETE FROM person2group2role_p2g2r WHERE p2g2r_per_ID = " . $personID . " AND p2g2r_grp_ID = " . $groupID;
    RunQuery($sSQL);

    // Check if this group has special properties
    $sSQL = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $groupID;
    $rsTemp = RunQuery($sSQL);
    $rowTemp = mysql_fetch_row($rsTemp);
    $bHasProp = $rowTemp[0];

    if ($bHasProp == 'true') {
      $sSQL = "DELETE FROM groupprop_" . $groupID . " WHERE per_ID = '" . $personID . "'";
      RunQuery($sSQL);
    }

    // Reset any group specific property fields of type "Person from Group" with this person assigned
    $sSQL = "SELECT grp_ID, prop_Field FROM groupprop_master WHERE type_ID = 9 AND prop_Special = " . $groupID;
    $result = RunQuery($sSQL);
    while ($aRow = mysql_fetch_array($result)) {
      $sSQL = "UPDATE groupprop_" . $aRow['grp_ID'] . " SET " . $aRow['prop_Field'] . " = NULL WHERE " . $aRow['prop_Field'] . " = " . $personID;
      RunQuery($sSQL);
    }

    // Reset any custom person fields of type "Person from Group" with this person assigned
    $sSQL = "SELECT custom_Field FROM person_custom_master WHERE type_ID = 9 AND custom_Special = " . $groupID;
    $result = RunQuery($sSQL);
    while ($aRow = mysql_fetch_array($result)) {
      $sSQL = "UPDATE person_custom SET " . $aRow['custom_Field'] . " = NULL WHERE " . $aRow['custom_Field'] . " = " . $personID;
      RunQuery($sSQL);
    }
  }

  /**
   *  addUserToGroup
   * @param  int $groupID Group ID from which  to remove the user
   * @param  int $personID UserID to remove from the group
   * @param  int $roleID Role ID to set to the person
   */
  function addUserToGroup($iGroupID, $iPersonID, $iRoleID)
  {
    requireUserGroupMembership("bManageGroups");
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
    $result = RunQuery($sSQL, false);
    if ($result) {
      // Check if this group has special properties
      $sSQL = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $iGroupID;
      $rsTemp = RunQuery($sSQL);
      $rowTemp = mysql_fetch_row($rsTemp);
      $bHasProp = $rowTemp[0];

      if ($bHasProp == 'true') {
        $sSQL = "INSERT INTO groupprop_" . $iGroupID . " (per_ID) VALUES ('" . $iPersonID . "')";
        RunQuery($sSQL);
      }
    }

    return $this->getGroupMembers($iGroupID, $iPersonID);
  }

  /**
   *  search
   * @param  string $searchTerm the string of text to search
   * @return array containing group objects of all of the gropus which match the search term.
   */
  function search($searchTerm)
  {
    $sSQL = 'SELECT grp_ID FROM group_grp LEFT JOIN list_lst on lst_ID = 3 AND lst_OptionID = grp_Type WHERE grp_Name LIKE \'%' . $searchTerm . '%\' OR  grp_Description LIKE \'%' . $searchTerm . '%\' OR lst_OptionName LIKE \'%' . $searchTerm . '%\'  order by grp_Name LIMIT 15';
    $result = mysql_query($sSQL);
    $return = array();
    while ($row = mysql_fetch_array($result)) {
      array_push($return, $this->getGroups($row['grp_ID']));
    }
    return $return;
  }

  /**
   *  getGroupJSON
   * @param  array $groups array containing group objects
   * @return string represnting the JSON of the given array, with key
   */
  function getGroupJSON($groups)
  {
    if ($groups) {
      return '{"groups": ' . json_encode($groups) . '}';
    } else {
      return false;
    }
  }

  /**
   *  getViewURI
   * @param  int $id ID of the group for which to return the view URI
   * @return string represnting the view page URI for the given group
   */
  function getViewURI($Id)
  {
    return $this->baseURL . "/GroupView.php?GroupID=" . $Id;
  }

  /**
   *  getGroupRoles
   * @param  int $groupID ID of the group
   * @return array represnting the roles of the group
   */
  function getGroupRoles($groupID)
  {
    $groupRoles = array ();
    $sSQL = "SELECT grp_ID, lst_OptionName, lst_OptionID, lst_OptionSequence
              FROM group_grp
              LEFT JOIN list_lst ON
              list_lst.lst_ID = group_grp.grp_RoleListID
              WHERE group_grp.grp_ID = " . $groupID;
    $rsList = RunQuery($sSQL);

    // Validate that this list ID is really for a group roles list. (for security)
    if(mysql_num_rows($rsList) == 0)
    {
      throw new \Exception("invalid request");
    }

    while ($row = mysql_fetch_assoc( $rsList ))
    {
      array_push($groupRoles,$row);
    }
    
    return $groupRoles;
  }

  /**
   *  setGroupRoleName
   * @param  int $groupID ID of the group
   * @param  int $groupRole ID of the  role in the group
   * @param  string $groupRoleName Name of the group role
   */
  function setGroupRoleName($groupID, $groupRoleID, $groupRoleName)
  {
    requireUserGroupMembership("bManageGroups");
    $sSQL = 'UPDATE list_lst
                 INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID
                 SET list_lst.lst_OptionName = "' . $groupRoleName . '"
                 WHERE group_grp.grp_ID = "' . $groupID . '"
                    AND list_lst.lst_OptionID = ' . $groupRoleID;
    RunQuery($sSQL);
  }

  function setGroupRoleOrder($groupID, $groupRoleID, $groupRoleOrder)
  {
    requireUserGroupMembership("bManageGroups");
    $sSQL = 'UPDATE list_lst
                 INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID
                 SET list_lst.lst_OptionSequence = "' . $groupRoleOrder . '"
                 WHERE group_grp.grp_ID = "' . $groupID . '"
                    AND list_lst.lst_OptionID = ' . $groupRoleID;
    RunQuery($sSQL);
  }

  function getGroupDefaultRole($groupID)
  {
    //Look up the default role name
    $sSQL = "SELECT lst_OptionName from list_lst INNER JOIN group_grp on (group_grp.grp_RoleListID = list_lst.lst_ID AND group_grp.grp_DefaultRole = list_lst.lst_OptionID) WHERE group_grp.grp_ID = " . $groupID;
    $aDefaultRole = mysql_fetch_array(RunQuery($sSQL));
    return $aDefaultRole[0];
  }

  function getGroupRoleOrder($groupID, $groupRoleID)
  {
    $sSQL = 'SELECT list_lst.lst_OptionSequence FROM list_lst
                INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID
                 WHERE group_grp.grp_ID = "' . $groupID . '"
                   AND list_lst.lst_OptionID = ' . $groupRoleID;

    $rsPropList = RunQuery($sSQL);
    $rowOrder = mysql_fetch_array($rsPropList);
    return $rowOrder[0];
  }

  function deleteGroupRole($groupID, $groupRoleID)
  {
    requireUserGroupMembership("bManageGroups");
    $sSQL = 'SELECT * FROM list_lst
                INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID
                 WHERE group_grp.grp_ID = "' . $groupID . '"';
    $rsPropList = RunQuery($sSQL);
    $numRows = mysql_num_rows($rsPropList);
    // Make sure we never delete the only option
    if ($numRows > 1) {
      $thisSequence = $this->getGroupRoleOrder($groupID, $groupRoleID);
      $sSQL = 'DELETE list_lst.* FROM list_lst
                    INNER JOIN group_grp
                        ON group_grp.grp_RoleListID = list_lst.lst_ID
                    WHERE group_grp.grp_ID = "' . $groupID . '"
                    AND lst_OptionID = ' . $groupRoleID;

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

      //Shift the remaining rows IDs up by one

      $sSQL = 'UPDATE list_lst
                    INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID
                    SET list_lst.lst_OptionID = list_lst.lst_OptionID -1
                    WHERE group_grp.grp_ID = ' . $groupID . '
                    AND list_lst.lst_OptionID >= ' . $groupRoleID;

      RunQuery($sSQL);

      //Shift up the remaining row Sequences by one

      $sSQL = 'UPDATE list_lst
                    INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID
                    SET list_lst.lst_OptionSequence = list_lst.lst_OptionSequence -1
                    WHERE group_grp.grp_ID =' . $groupID . '
                    AND list_lst.lst_OptionSequence >= ' . $thisSequence;

      //echo $sSQL;

      RunQuery($sSQL);

      return $this->getGroupRoles($groupID);
    } else {
      throw new \Exception ("You cannot delete the only group");
    }
  }

  function addGroupRole($groupID, $groupRoleName)
  {
    requireUserGroupMembership("bManageGroups");
    if (strlen($groupRoleName) == 0) {
      throw new \Exception ("New field name cannot be blank");
    } else {
      // Check for a duplicate option name
      $sSQL = 'SELECT \'\' FROM list_lst
                INNER JOIN group_grp
                    ON group_grp.grp_RoleListID = list_lst.lst_ID
                 WHERE group_grp.grp_ID = "' . $groupID . '" AND
                 lst_OptionName = "' . $groupRoleName . '"';
      $rsCount = RunQuery($sSQL);
      if (mysql_num_rows($rsCount) > 0) {
        throw new \Exception ("Field " . $groupRoleName . " already exists");
      } else {
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
    return '{"newRole":{"roleID":"' . $newOptionID . '", "roleName":"' . $groupRoleName . '", "sequence":"' . $newOptionSequence . '"}}';
  }

  function setGroupRoleAsDefault($groupID, $roleID)
  {
    requireUserGroupMembership("bManageGroups");
    $sSQL = "UPDATE group_grp SET grp_DefaultRole = " . $roleID . " WHERE grp_ID = " . $groupID;
    RunQuery($sSQL);
  }

  function getGroupTotalMembers($groupID)
  {
    //Get the count of members
    $sSQL = 'SELECT COUNT(*) AS iTotalMembers FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = ' . $groupID;
    $rsTotalMembers = mysql_fetch_array(RunQuery($sSQL));
    return $rsTotalMembers[0];
  }

  function getGroupTypes()
  {
    $groupTypes = array();
    // Get Group Types for the drop-down
    $sSQL = "SELECT * FROM list_lst WHERE lst_ID = 3 ORDER BY lst_OptionSequence";
    $rsGroupTypes = RunQuery($sSQL);
    while ($aRow = mysql_fetch_assoc($rsGroupTypes)) {
      array_push($groupTypes, $aRow);
    }
    return $groupTypes;
  }

  function getGroupRoleTemplateGroups()
  {
    $templateGroups = array();
    $sSQL = "SELECT * FROM group_grp WHERE grp_RoleListID > 0 ORDER BY grp_Name";
    $rsGroupRoleSeed = RunQuery($sSQL);
    while ($aRow = mysql_fetch_assoc($rsGroupRoleSeed)) {
      array_push($templateGroups, $aRow);
    }
    return $templateGroups;
  }

  function setGroupName($groupID, $groupName)
  {
    requireUserGroupMembership("bManageGroups");
  }

  function enableGroupSpecificProperties($groupID)
  {
    requireUserGroupMembership("bManageGroups");
    $sSQL = "UPDATE group_grp SET grp_hasSpecialProps = true
            WHERE grp_ID = " . $groupID;
     RunQuery($sSQL);
    $sSQLp = "CREATE TABLE groupprop_" . $groupID . " (
                        per_ID mediumint(8) unsigned NOT NULL default '0',
                        PRIMARY KEY  (per_ID),
                          UNIQUE KEY per_ID (per_ID)
                        ) ENGINE=MyISAM;";
    RunQuery($sSQLp);
    
    $groupMembers = $this->getGroupMembers($groupID);

    foreach ($groupMembers as $member) {
      $sSQLr = "INSERT INTO groupprop_" . $groupID . " ( per_ID ) VALUES ( '" . $member['per_ID'] . "' );";
      RunQuery($sSQLr);
    }
  }

  function disableGroupSpecificProperties($groupID)
  {
    requireUserGroupMembership("bManageGroups");
    $sSQLp = "DROP TABLE groupprop_" . $groupID;
    RunQuery($sSQLp);

    // need to delete the master index stuff
    $sSQLp = "DELETE FROM groupprop_master WHERE grp_ID = " . $groupID;
    RunQuery($sSQLp);
    
     $sSQL = "UPDATE group_grp SET grp_hasSpecialProps = false
            WHERE grp_ID = " . $groupID;
    
     RunQuery($sSQL);
  }

  function createGroup($groupName)
  {
    requireUserGroupMembership("bManageGroups");
    if (!$groupName) {   //If there's no group name, throw an exception
      throw new \Exception ("Unable to create a group without a name");
    }
    //Get a new Role List ID
    $sSQL = "SELECT MAX(lst_ID) FROM list_lst";
    $aTemp = mysql_fetch_array(RunQuery($sSQL));
    if ($aTemp[0] > 9)
      $newListID = $aTemp[0] + 1;
    else
      $newListID = 10;

    /* if ($groupData->useGroupSpecificProperties)
      $sUseProps = 'true';
      else
      $sUseProps = 'false'; */
    //$sSQL = "INSERT INTO group_grp (grp_Name, grp_Type, grp_Description, grp_hasSpecialProps, grp_DefaultRole, grp_RoleListID) VALUES ('" . $groupData->groupName . "', " . $groupData->groupType . ", '" . $groupData->description . "', '" . $sUseProps . "', '1', " . $newListID . ")";
    $sSQL = "INSERT INTO group_grp (grp_Name, grp_DefaultRole, grp_RoleListID) VALUES ('" . mysql_real_escape_string($groupName) . "', '1', " . $newListID . ")";

    $result = mysql_query($sSQL);
    //Get the key back
    $iGroupID = mysql_insert_id();

    if (false) { // ($cloneGroupRole) && ($seedGroupID>0)
      $sSQL = "SELECT list_lst.* FROM list_lst, group_grp WHERE group_grp.grp_RoleListID = list_lst.lst_ID AND group_grp.grp_id = $seedGroupID ORDER BY list_lst.lst_OptionID";
      $rsRoleSeed = RunQuery($sSQL);
      while ($aRow = mysql_fetch_array($rsRoleSeed)) {
        extract($aRow);
        $useOptionName = mysql_real_escape_string($lst_OptionName);
        $sSQL = "INSERT INTO list_lst VALUES ($newListID, $lst_OptionID, $lst_OptionSequence, '$useOptionName')";
        RunQuery($sSQL);
      }
    } else {
      $sSQL = "INSERT INTO list_lst VALUES ($newListID, 1, 1,'Member')";
      RunQuery($sSQL);
    }

    return $this->getGroups($iGroupID);
  }

  function deleteGroup($iGroupID)
  {
    requireUserGroupMembership("bManageGroups");
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

    while ($aRow = mysql_fetch_row($rsProps)) {
      $sSQL = "DELETE FROM record2property_r2p WHERE r2p_pro_ID = " . $aRow[0] . " AND r2p_record_ID = " . $iGroupID;
      RunQuery($sSQL);
    }

    if ($hasSpecialProps == 'true') {
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

  function getGroups($groupIDs = NULL)
  {
    $whereClause = "";
    if (is_numeric($groupIDs)) {
      $whereClause = "WHERE grp_ID = " . $groupIDs;
    } elseif (is_array($groupIDs)) {
      $whereClause = "WHERE grp_ID in (" . implode(",", $groupIDs) . ")";
    } elseif (is_null($groupIDs)) {
      $whereClause = "";
    }
    $fetch = 'SELECT * FROM group_grp LEFT JOIN list_lst on lst_ID = 3 AND lst_OptionID = grp_Type ' . $whereClause . ' ORDER BY grp_Name ';
    $result = mysql_query($fetch);
    $return = array();
    while ($row = mysql_fetch_array($result)) {
      $totalMembers = $this->getGroupTotalMembers($row['grp_ID']);
      $values['id'] = $row['grp_ID'];
      $values['groupName'] = $row['grp_Name'];
      $values['displayName'] = $row['grp_Name'];
      $values['grp_Type'] = $row['grp_Type'];
      $values['groupType'] = $row['lst_OptionName'];
      $values['grp_DefaultRole'] = $row['grp_DefaultRole'];
      $values['groupDescription'] = $row['grp_Description'];
      $values['uri'] = $this->getViewURI($row['grp_ID']);
      $values['memberCount'] = $totalMembers;
      $values['defaultRole'] = $this->getGroupDefaultRole($row['grp_ID']);
      $values['roles'] = $this->getGroupRoles($row['grp_ID']);
      $values['totalMembers'] = $totalMembers;
      $values['grp_hasSpecialProps'] = $row['grp_hasSpecialProps'] == "true";
      array_push($return, $values);
    }
    if (count($return) == 1) {
      return $return[0];
    } else {
      return $return;
    }
  }

  function getGroupMembers($groupID, $personID = null)
  {
    $whereClause = "";
    if (is_numeric($personID)) {
      $whereClause = " AND p2g2r_per_ID = " . $personID;
    }

    $members = array();
    // Main select query
    $sSQL = "SELECT p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID, lst_OptionName FROM person2group2role_p2g2r

        INNER JOIN group_grp ON
        person2group2role_p2g2r.p2g2r_grp_ID = group_grp.grp_ID

        INNER JOIN list_lst ON
        group_grp.grp_RoleListID = list_lst.lst_ID AND
        person2group2role_p2g2r.p2g2r_rle_ID =  list_lst.lst_OptionID

        WHERE p2g2r_grp_ID =" . $groupID . " " . $whereClause;
    $result = mysql_query($sSQL);
    while ($row = mysql_fetch_assoc($result)) {
      $dbPerson = PersonQuery::create()->findPk($row['p2g2r_per_ID']);
      $person['displayName'] = $dbPerson->getFullName();
      $person['groupRole'] = $row['lst_OptionName'];
      array_push($members, $person);
    }
    return $members;
  }

  function getGroupMembersIds($groupID)
  {
    $members = array();
    // Main select query
    $sSQL = "SELECT p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID, lst_OptionName FROM person2group2role_p2g2r

        INNER JOIN group_grp ON
        person2group2role_p2g2r.p2g2r_grp_ID = group_grp.grp_ID

        INNER JOIN list_lst ON
        group_grp.grp_RoleListID = list_lst.lst_ID AND
        person2group2role_p2g2r.p2g2r_rle_ID =  list_lst.lst_OptionID

        WHERE p2g2r_grp_ID =" . $groupID;
    $result = mysql_query($sSQL);
    while ($row = mysql_fetch_assoc($result)) {
      $person = array( "id" => $row['p2g2r_per_ID']);
      array_push($members, $person);
    }
    return $members;
  }

 
  function copyCartToGroup()
  {
    requireUserGroupMembership("bManageGroups");
    if (array_key_exists("EmptyCart", $_POST) && $_POST["EmptyCart"] && count($_SESSION['aPeopleCart']) > 0) {
      $iCount = 0;
      while ($element = each($_SESSION['aPeopleCart'])) {
        AddUsertoGroup($_SESSION['aPeopleCart'][$element['key']], $iGroupID, $thisGroup['grp_DefaultRole']);
        $iCount += 1;
      }

      $sGlobalMessage = $iCount . " records(s) successfully added to selected Group.";

      Redirect("GroupEditor.php?GroupID=" . $iGroupID . "&Action=EmptyCart");
    } else {
      Redirect("GroupEditor.php?GroupID=$iGroupID");
    }
  }

}

?>
