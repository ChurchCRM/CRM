<?php

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\PdfGroupDirectory;
use ChurchCRM\Utils\InputUtils;

$bOnlyCartMembers = $_POST['OnlyCart'];
$iGroupID = InputUtils::legacyFilterInput($_POST['GroupID'], 'int');
$iMode = InputUtils::legacyFilterInput($_POST['ReportModel'], 'int');

if ($iMode == 1) {
    $iRoleID = InputUtils::legacyFilterInput($_POST['GroupRole'], 'int');
} else {
    $iRoleID = 0;
}

// Get the group name
$sSQL = 'SELECT grp_Name, grp_RoleListID FROM group_grp WHERE grp_ID = ' . $iGroupID;
$rsGroupName = RunQuery($sSQL);
$aRow = mysqli_fetch_array($rsGroupName);
$sGroupName = $aRow[0];
$iRoleListID = $aRow[1];

// Get the selected role name
if ($iRoleID > 0) {
    $sSQL = 'SELECT lst_OptionName FROM list_lst WHERE lst_ID = ' . $iRoleListID . ' AND lst_OptionID = ' . $iRoleID;
    $rsTemp = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsTemp);
    $sRoleName = $aRow[0];
} elseif (isset($_POST['GroupRoleEnable'])) {
    $sSQL = 'SELECT lst_OptionName,lst_OptionID FROM list_lst WHERE lst_ID = ' . $iRoleListID;
    $rsTemp = RunQuery($sSQL);

    while ($aRow = mysqli_fetch_array($rsTemp)) {
        $aRoleNames[$aRow[1]] = $aRow[0];
    }
}

$pdf = new PdfGroupDirectory();

// See if this group has special properties.
$sSQL = 'SELECT * FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';
$rsProps = RunQuery($sSQL);
$bHasProps = (mysqli_num_rows($rsProps) > 0);

$sSQL = 'SELECT * FROM person_per
			LEFT JOIN family_fam ON per_fam_ID = fam_ID ';

if ($bHasProps) {
    $sSQL .= 'LEFT JOIN groupprop_' . $iGroupID . ' ON groupprop_' . $iGroupID . '.per_ID = person_per.per_ID ';
}

$sSQL .= 'LEFT JOIN person2group2role_p2g2r ON p2g2r_per_ID = person_per.per_ID
			WHERE p2g2r_grp_ID = ' . $iGroupID;

if ($iRoleID > 0) {
    $sSQL .= ' AND p2g2r_rle_ID = ' . $iRoleID;
}

if ($bOnlyCartMembers && count($_SESSION['aPeopleCart']) > 0) {
    $sSQL .= ' AND person_per.per_ID IN (' . convertCartToString($_SESSION['aPeopleCart']) . ')';
}

$sSQL .= ' ORDER BY per_LastName';

$rsRecords = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsRecords)) {
    $OutStr = '';

    $pdf->sFamily = FormatFullName($aRow['per_Title'], $aRow['per_FirstName'], $aRow['per_MiddleName'], $aRow['per_LastName'], $aRow['per_Suffix'], 3);

    SelectWhichAddress($sAddress1, $sAddress2, $aRow['per_Address1'], $aRow['per_Address2'], $aRow['fam_Address1'], $aRow['fam_Address2'], false);

    $sCity = SelectWhichInfo($aRow['per_City'], $aRow['fam_City'], false);
    $sState = SelectWhichInfo($aRow['per_State'], $aRow['fam_State'], false);
    $sZip = SelectWhichInfo($aRow['per_Zip'], $aRow['fam_Zip'], false);
    $sHomePhone = SelectWhichInfo($aRow['per_HomePhone'], $aRow['fam_HomePhone'], false);
    $sWorkPhone = SelectWhichInfo($aRow['per_WorkPhone'], $aRow['fam_WorkPhone'], false);
    $sCellPhone = SelectWhichInfo($aRow['per_CellPhone'], $aRow['fam_CellPhone'], false);
    $sEmail = SelectWhichInfo($aRow['per_Email'], $aRow['fam_Email'], false);

    if (isset($_POST['GroupRoleEnable'])) {
        $OutStr = gettext('Role') . ': ' . $aRoleNames[$aRow['p2g2r_rle_ID']] . "\n";
    }

    if (isset($_POST['AddressEnable'])) {
        if (strlen($sAddress1)) {
            $OutStr .= $sAddress1 . "\n";
        }
        if (strlen($sAddress2)) {
            $OutStr .= $sAddress2 . "\n";
        }
        if (strlen($sCity)) {
            $OutStr .= $sCity . ', ' . $sState . ' ' . $sZip . "\n";
        }
    }

    if (isset($_POST['HomePhoneEnable']) && strlen($sHomePhone)) {
        $TempStr = ExpandPhoneNumber($sHomePhone, SystemConfig::getValue('sDefaultCountry'), $bWierd);
        $OutStr .= '  ' . gettext('Phone') . ': ' . $TempStr . "\n";
    }

    if (isset($_POST['WorkPhoneEnable']) && strlen($sWorkPhone)) {
        $TempStr = ExpandPhoneNumber($sWorkPhone, SystemConfig::getValue('sDefaultCountry'), $bWierd);
        $OutStr .= '  ' . gettext('Work') . ': ' . $TempStr . "\n";
    }

    if (isset($_POST['CellPhoneEnable']) && strlen($sCellPhone)) {
        $TempStr = ExpandPhoneNumber($sCellPhone, SystemConfig::getValue('sDefaultCountry'), $bWierd);
        $OutStr .= '  ' . gettext('Cell') . ': ' . $TempStr . "\n";
    }

    if (isset($_POST['EmailEnable']) && strlen($sEmail)) {
        $OutStr .= '  ' . gettext('Email') . ': ' . $sEmail . "\n";
    }

    if (isset($_POST['OtherEmailEnable']) && strlen($aRow['per_WorkEmail'])) {
        $OutStr .= '  ' . gettext('Other Email') . ': ' . $aRow['per_WorkEmail'] .= "\n";
    }

    if ($bHasProps) {
        while ($aPropRow = mysqli_fetch_array($rsProps)) {
            if (isset($_POST[$aPropRow['prop_Field'] . 'enable'])) {
                $currentData = trim($aRow[$aPropRow['prop_Field']]);
                $OutStr .= $aPropRow['prop_Name'] . ': ' . displayCustomField($aPropRow['type_ID'], $currentData, $aPropRow['prop_Special']) . "\n";
            }
        }
        mysqli_data_seek($rsProps, 0);
    }

    // Count the number of lines in the output string
    $numlines = 1;
    $offset = 0;
    while ($result = strpos($OutStr, "\n", $offset)) {
        $offset = $result + 1;
        $numlines++;
    }

    $pdf->addRecord($pdf->sFamily, $OutStr, $numlines);
}

if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
    $pdf->Output('GroupDirectory-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
