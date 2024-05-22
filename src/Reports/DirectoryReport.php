<?php

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\PdfDirectory;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

// Check for Create Directory user permission.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isCreateDirectoryEnabled());

// Get and filter the classifications selected
$aClasses = [];
if (array_key_exists('sDirClassifications', $_POST) && $_POST['sDirClassifications'] != '') {
    foreach ($_POST['sDirClassifications'] as $Cls) {
        $aClasses[] = InputUtils::legacyFilterInput($Cls, 'int');
    }
    $sDirClassifications = implode(',', $aClasses);
} else {
    $sDirClassifications = '';
}
$aHeads = [];
foreach ($_POST['sDirRoleHead'] as $Head) {
    $aHeads[] = InputUtils::legacyFilterInput($Head, 'int');
}
$sDirRoleHeads = implode(',', $aHeads);

$aSpouses = [];
foreach ($_POST['sDirRoleSpouse'] as $Spouse) {
    $aSpouses[] = InputUtils::legacyFilterInput($Spouse, 'int');
}
$sDirRoleSpouses = implode(',', $aSpouses);

$aChildren = [];
foreach ($_POST['sDirRoleChild'] as $Child) {
    $aChildren[] = InputUtils::legacyFilterInput($Child, 'int');
}

//Exclude inactive families
$bExcludeInactive = isset($_POST['bExcludeInactive']);

// Get other settings
$bDirAddress = isset($_POST['bDirAddress']);
$bDirWedding = isset($_POST['bDirWedding']);
$bDirBirthday = isset($_POST['bDirBirthday']);
$bDirFamilyPhone = isset($_POST['bDirFamilyPhone']);
$bDirFamilyWork = isset($_POST['bDirFamilyWork']);
$bDirFamilyCell = isset($_POST['bDirFamilyCell']);
$bDirFamilyEmail = isset($_POST['bDirFamilyEmail']);
$bDirPersonalPhone = isset($_POST['bDirPersonalPhone']);
$bDirPersonalWork = isset($_POST['bDirPersonalWork']);
$bDirPersonalCell = isset($_POST['bDirPersonalCell']);
$bDirPersonalEmail = isset($_POST['bDirPersonalEmail']);
$bDirPersonalWorkEmail = isset($_POST['bDirPersonalWorkEmail']);
$bDirPhoto = isset($_POST['bDirPhoto']);

$sChurchName = InputUtils::legacyFilterInput($_POST['sChurchName']);
$sDirectoryDisclaimer = InputUtils::legacyFilterInput($_POST['sDirectoryDisclaimer']);
$sChurchAddress = InputUtils::legacyFilterInput($_POST['sChurchAddress']);
$sChurchCity = InputUtils::legacyFilterInput($_POST['sChurchCity']);
$sChurchState = InputUtils::legacyFilterInput($_POST['sChurchState']);
$sChurchZip = InputUtils::legacyFilterInput($_POST['sChurchZip']);
$sChurchPhone = InputUtils::legacyFilterInput($_POST['sChurchPhone']);

$bDirUseTitlePage = isset($_POST['bDirUseTitlePage']);

$bNumberofColumns = InputUtils::legacyFilterInput($_POST['NumCols'] ?? '1', 'int');
$sPageSize = InputUtils::legacyFilterInput($_POST['PageSize']);
$bFontSz = InputUtils::legacyFilterInput($_POST['FSize'] ?? '8', 'int');
$bLineSp = $bFontSz / 3;

if ($sPageSize != 'letter' && $sPageSize != 'a4') {
    $sPageSize = 'legal';
}

LoggerUtils::getAppLogger()->debug("ncols = {$bNumberofColumns} page size = {$sPageSize}");

// Instantiate the directory class and build the report
LoggerUtils::getAppLogger()->debug("font sz = {$bFontSz} and line sp = {$bLineSp}");
$pdf = new PdfDirectory($bNumberofColumns, $sPageSize, $bFontSz, $bLineSp);

// Get the list of custom person fields
$sSQL = 'SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysqli_num_rows($rsCustomFields);

if ($numCustomFields > 0) {
    while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_ASSOC)) {
        $pdf->addCustomField(
            $rowCustomField['custom_Order'],
            isset($_POST['bCustom' . $rowCustomField['custom_Order']])
        );
    }
}

$pdf->addPage();

if ($bDirUseTitlePage) {
    $pdf->titlePage();
}

$sClassQualifier = '';
if (strlen($sDirClassifications)) {
    $sClassQualifier = 'AND per_cls_ID in (' . $sDirClassifications . ')';
}

$sWhereExt = '';
if (!empty($_POST['GroupID'])) {
    $sGroupTable = '(person_per, person2group2role_p2g2r)';

    $aGroups = [];
    foreach ($_POST['GroupID'] as $Grp) {
        $aGroups[] = InputUtils::legacyFilterInput($Grp, 'int');
    }
    $sGroupsList = implode(',', $aGroups);

    $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID in (' . $sGroupsList . ')';

    // This is used by per-role queries to remove duplicate rows from people assigned multiple groups.
    $sGroupBy = ' GROUP BY per_ID';
} else {
    $sGroupTable = 'person_per';
    $sGroupsList = '';
    $sWhereExt = '';
    $sGroupBy = '';
}

//Exclude inactive families
if ($bExcludeInactive) {
    $sWhereExt .= ' AND fam_DateDeactivated is null';
}

if (array_key_exists('cartdir', $_POST)) {
    $sWhereExt .= 'AND per_ID IN (' . convertCartToString($_SESSION['aPeopleCart']) . ')';
}

$mysqlinfo = mysqli_get_server_info($cnInfoCentral);
$mysqltmp = explode('.', $mysqlinfo);
$mysqlversion = $mysqltmp[0];
if (count($mysqltmp) > 1) {
    $mysqlsubversion = $mysqltmp[1];
} else {
    $mysqlsubversion = 0;
}
if ($mysqlversion >= 4) {
    // This query is similar to that of the CSV export with family roll-up.
    // Here we want to gather all unique families, and those that are not attached to a family.
    $sSQL = "(SELECT *, 0 AS memberCount, per_LastName AS SortMe FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID = 0 $sWhereExt $sClassQualifier )
        UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 $sWhereExt $sClassQualifier  GROUP BY per_fam_ID HAVING memberCount = 1)
        UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 $sWhereExt $sClassQualifier  GROUP BY per_fam_ID HAVING memberCount > 1)
        ORDER BY SortMe";
} elseif ($mysqlversion == 3 && $mysqlsubversion >= 22) {
    // If UNION not supported use this query with temporary table.  Prior to version 3.22 no IF EXISTS statement.
    $sSQL = 'DROP TABLE IF EXISTS tmp;';
    $rsRecords = mysqli_query($cnInfoCentral, $sSQL) || exit(mysqli_error($cnInfoCentral));
    $sSQL = "CREATE TABLE tmp TYPE = InnoDB SELECT *, 0 AS memberCount, per_LastName AS SortMe FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID = 0 $sWhereExt $sClassQualifier ;";
    $rsRecords = mysqli_query($cnInfoCentral, $sSQL) || exit(mysqli_error($cnInfoCentral));
    $sSQL = "INSERT INTO tmp SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 $sWhereExt $sClassQualifier GROUP BY per_fam_ID HAVING memberCount = 1;";
    $rsRecords = mysqli_query($cnInfoCentral, $sSQL) || exit(mysqli_error($cnInfoCentral));
    $sSQL = "INSERT INTO tmp SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 $sWhereExt $sClassQualifier GROUP BY per_fam_ID HAVING memberCount > 1;";
    $rsRecords = mysqli_query($cnInfoCentral, $sSQL) || exit(mysqli_error($cnInfoCentral));
    $sSQL = 'SELECT DISTINCT * FROM tmp ORDER BY SortMe';
} else {
    exit(gettext('This option requires at least version 3.22 of MySQL!  Hit browser back button to return to ChurchCRM.'));
}

$rsRecords = RunQuery($sSQL);

// This is used for the headings for the letter changes.
// Start out with something that isn't a letter to force the first one to work
$sLastLetter = '0';

while ($aRow = mysqli_fetch_array($rsRecords)) {
    $OutStr = '';
    extract($aRow);

    $pdf->sSortBy = $SortMe;

    $isFamily = false;

    if ($memberCount > 1) {
        // Here we have a family record
        $iFamilyID = $per_fam_ID;
        $isFamily = true;

        $pdf->sRecordName = '';
        $pdf->sLastName = $per_LastName;
        $OutStr .= $pdf->sGetFamilyString($aRow);
        $bNoRecordName = true;

        // Find the Head of Household
        $sSQL = "SELECT * FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID
            WHERE per_fam_ID = " . $iFamilyID . "
            AND per_fmr_ID in ($sDirRoleHeads) $sWhereExt $sClassQualifier $sGroupBy";
        $rsPerson = RunQuery($sSQL);

        if (mysqli_num_rows($rsPerson) > 0) {
            $aHead = mysqli_fetch_array($rsPerson);
            $OutStr .= $pdf->sGetHeadString($rsCustomFields, $aHead);
            $bNoRecordName = false;
        }

        // Find the Spouse of Household
        $sSQL = "SELECT * FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID
            WHERE per_fam_ID = " . $iFamilyID . "
            AND per_fmr_ID in ($sDirRoleSpouses) $sWhereExt $sClassQualifier $sGroupBy";
        $rsPerson = RunQuery($sSQL);

        if (mysqli_num_rows($rsPerson) > 0) {
            $aSpouse = mysqli_fetch_array($rsPerson);
            $OutStr .= $pdf->sGetHeadString($rsCustomFields, $aSpouse);
            $bNoRecordName = false;
        }

        // In case there was no head or spouse, just set record name to family name
        if ($bNoRecordName) {
            $pdf->sRecordName = $fam_Name;
        }

        // Find the other members of a family
        $sSQL = "SELECT * FROM $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID
            WHERE per_fam_ID = " . $iFamilyID . " AND !(per_fmr_ID in ($sDirRoleHeads))
            AND !(per_fmr_ID in ($sDirRoleSpouses))  $sWhereExt $sClassQualifier $sGroupBy ORDER BY per_BirthYear,per_FirstName";
        $rsPerson = RunQuery($sSQL);

        while ($aRow = mysqli_fetch_array($rsPerson)) {
            $OutStr .= $pdf->sGetMemberString($aRow);
            $OutStr .= $pdf->sGetCustomString($rsCustomFields, $aRow);
        }
    } else {
        if (strlen($per_LastName)) {
            $pdf->sLastName = $per_LastName;
        } else {
            $pdf->sLastName = $fam_Name;
        }
        $pdf->sRecordName = $pdf->sLastName . ', ' . $per_FirstName;
        if (strlen($per_Suffix)) {
            $pdf->sRecordName .= ' ' . $per_Suffix;
        }

        if ($bDirBirthday && $per_BirthMonth && $per_BirthDay) {
            $pdf->sRecordName .= ' ' . MiscUtils::formatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, '/', $per_Flags);
        }

        SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, false);
        $sAddress2 = SelectWhichInfo($per_Address2, $fam_Address2, false);
        $sCity = SelectWhichInfo($per_City, $fam_City, false);
        $sState = SelectWhichInfo($per_State, $fam_State, false);
        $sZip = SelectWhichInfo($per_Zip, $fam_Zip, false);
        $sHomePhone = SelectWhichInfo($per_HomePhone, $fam_HomePhone, false);
        $sWorkPhone = SelectWhichInfo($per_WorkPhone, $fam_WorkPhone, false);
        $sCellPhone = SelectWhichInfo($per_CellPhone, $fam_CellPhone, false);
        $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);

        if ($bDirAddress) {
            if (strlen($sAddress1)) {
                $OutStr .= $sAddress1;
            }
            if (strlen($sAddress2)) {
                $OutStr .= '   ' . $sAddress2;
            }
            $OutStr .= "\n";
            if (strlen($sCity)) {
                $OutStr .= $sCity . ', ' . $sState . ' ' . $sZip . "\n";
            }
        }
        if (($bDirFamilyPhone || $bDirPersonalPhone) && strlen($sHomePhone)) {
            $TempStr = ExpandPhoneNumber($sHomePhone, SystemConfig::getValue('sDefaultCountry'), $bWierd);
            $OutStr .= '   ' . gettext('Phone') . ': ' . $TempStr . "\n";
        }
        if (($bDirFamilyWork || $bDirPersonalWork) && strlen($sWorkPhone)) {
            $TempStr = ExpandPhoneNumber($sWorkPhone, SystemConfig::getValue('sDefaultCountry'), $bWierd);
            $OutStr .= '   ' . gettext('Work') . ': ' . $TempStr . "\n";
        }
        if (($bDirFamilyCell || $bDirPersonalCell) && strlen($sCellPhone)) {
            $TempStr = ExpandPhoneNumber($sCellPhone, SystemConfig::getValue('sDefaultCountry'), $bWierd);
            $OutStr .= '   ' . gettext('Cell') . ': ' . $TempStr . "\n";
        }
        if (($bDirFamilyEmail || $bDirPersonalEmail) && strlen($sEmail)) {
            $OutStr .= '   ' . gettext('Email') . ': ' . $sEmail . "\n";
        }
        if ($bDirPersonalWorkEmail && strlen($per_WorkEmail)) {
            $OutStr .= '   ' . gettext('Work/Other Email') . ': ' . $per_WorkEmail .= "\n";
        }

        // Custom Fields
        $OutStr .= $pdf->sGetCustomString($rsCustomFields, $aRow);
    }

    // Count the number of lines in the output string
    if (strlen($OutStr)) {
        $numlines = $pdf->nbLines($pdf->_ColWidth, $OutStr);
    } else {
        $numlines = 0;
    }

    if ($numlines > 0) {
        if (strtoupper($sLastLetter) != strtoupper(mb_substr($pdf->sSortBy, 0, 1))) {
            $pdf->checkLines($numlines + 2, null);
            $sLastLetter = strtoupper(mb_substr($pdf->sSortBy, 0, 1));
            $pdf->addHeader($sLastLetter);
        }

        // If photo include pass the ID, otherwise 0 equates to no family/person
        $fid = 0;
        $pid = 0;
        if ($bDirPhoto) {
            if ($isFamily) {
                $fid = $fam_ID;
            } else {
                $pid = $per_ID;
            }
        }
        $pdf->addRecord($pdf->sRecordName, $OutStr, $numlines, $fid, $pid);
    }
}

if ($mysqlversion == 3 && $mysqlsubversion >= 22) {
    $sSQL = 'DROP TABLE IF EXISTS tmp;';
    mysqli_query($cnInfoCentral, $sSQL);
}

if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
    $pdf->Output('Directory-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
