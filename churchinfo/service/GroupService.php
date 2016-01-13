<?php


class GroupService {


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

}

?>
