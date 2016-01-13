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
            $sSQL = "DELETE FROM `groupprop_" . $iGroupID . "` WHERE `per_ID` = '" . $iPersonID . "'";
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


}

?>
