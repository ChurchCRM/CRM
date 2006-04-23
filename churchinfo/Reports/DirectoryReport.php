<?php
/*******************************************************************************
*
*  filename    : Reports/DirectoryReport.php
*  last change : 2003-08-30
*  description : Creates a Member directory
*
*  http://www.churchdb.org/
*  Copyright 2003  Jason York, 2004-2005 Michael Wilt, Richard Bondi
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
    Redirect("Menu.php");
    exit;
}

class PDF_Directory extends ChurchInfoReport {

    // Private properties
    var $_Margin_Left = 16;         // Left Margin
    var $_Margin_Top  = 0;         // Top margin 
    var $_Char_Size   = 12;        // Character size
    var $_Column      = 0;
    var $_Font        = "Times";
    var $sFamily;
    var $sLastName;
    var $_ColWidth    = 80;
    var $_Custom;

    function Header()
    {
        global $bDirUseTitlePage;

        if (($this->PageNo() > 1) || ($bDirUseTitlePage == false))
        {
            //Select Arial bold 15
            $this->SetFont($this->_Font,'B',15);
            //Line break
            $this->Ln(7);
            //Move to the right
            $this->SetX($this->_Margin_Left);
            //Framed title
            $this->Cell($this->w - ($this->_Margin_Left*2),10,$this->sChurchName . " - " . gettext("Member Directory"),1,0,'C');
            $this->SetY(25);
        }
    }

    function Footer()
    {
        global $bDirUseTitlePage;

        if (($this->PageNo() > 1) || ($bDirUseTitlePage == false))
        {
            //Go to 1.7 cm from bottom
            $this->SetY(-17);
            //Select Arial italic 8
            $this->SetFont($this->_Font,'I',8);
            //Print centered page number
            $iPageNumber = $this->PageNo();
            if ($bDirUseTitlePage)
                $iPageNumber--;
            $this->Cell(0,10, gettext("Page") . " " . $iPageNumber,0,0,'C');
        }
    }

    function TitlePage()
    {
        global $sChurchName;
        global $sDirectoryDisclaimer;
        global $sChurchAddress;
        global $sChurchCity;
        global $sChurchState;
        global $sChurchZip;
        global $sChurchPhone;

        //Select Arial bold 15
        $this->SetFont($this->_Font,'B',15);

        if (is_readable($this->bDirLetterHead))
            $this->Image($this->bDirLetterHead,10,5,190);

        //Line break
        $this->Ln(5);
        //Move to the right
        $this->MultiCell(197,10,"\n\n\n". $sChurchName . "\n\n" . gettext("Directory") . "\n\n",0,'C');
        $this->Ln(5);
        $today = date("F j, Y");
        $this->MultiCell(197,10,$today . "\n\n",0,'C');

        $sContact = sprintf("%s\n%s, %s  %s\n\n%s\n\n", $sChurchAddress, $sChurchCity, $sChurchState, $sChurchZip, $sChurchPhone);
        $this->MultiCell(197,10,$sContact,0,'C');
        $this->Cell(10);
        $this->MultiCell(197,10,$sDirectoryDisclaimer,0,'C');
        $this->AddPage();
    }


    // Sets the character size
    // This changes the line height too
    function Set_Char_Size($pt) {
        if ($pt > 3) {
            $this->_Char_Size = $pt;
            $this->SetFont($this->_Font,'',$this->_Char_Size);
        }
    }

    // Constructor
    function PDF_Directory() {
        parent::FPDF("P", "mm", $this->paperFormat);

        $this->_Column      = 0;
        $this->_Font        = "Times";
        $this->SetMargins(0,0);
        $this->Open();
        $this->Set_Char_Size(12);
        $this->SetAutoPageBreak(false);

        $this->_Margin_Left = 16;
        $this->_Margin_Top  = 16;
        $this->_Custom = array();
    }

    function AddCustomField($order, $use){
        $this->_Custom[$order] = $use;
    }
    
    function NbLines($w,$txt){
        //Computes the number of lines a MultiCell of width w will take
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
            $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',$txt);
        $nb=strlen($s);
        if($nb>0 and $s[$nb-1]=="\n")
            $nb--;
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $nl=1;
        while($i<$nb)
        {
            $c=$s[$i];
            if($c=="\n")
            {
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep=$i;
            $l+=$cw[$c];
            if($l>$wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                    $i=$sep+1;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }

    function Check_Lines($numlines, $fid, $pid)
    {
        // Need to determine if we will extend beyoned 17mm from the bottom of
        // the page.
        
        $h = 0; // check image height.  id will be zero if not included
           $famimg = "../Images/Family/".$fid.".jpg";
        if (file_exists($famimg)) 
        {
            $s = getimagesize($famimg);
            $h = ($this->_ColWidth / $s[0]) * $s[1];
        }

           $persimg = "../Images/Person/".$pid.".jpg";
        if (file_exists($persimg)) 
        {
            $s = getimagesize($persimg);
            $h = ($this->_ColWidth / $s[0]) * $s[1];
        }


        if ($this->GetY() + $h + $numlines * 5 > $this->h - 27)
        {
            // Next Column or Page
            if ($this->_Column == 1)
            {
                $this->_Column = 0;
                $this->SetY(25);
                $this->AddPage();
            }
            else
            {
                $this->_Column = 1;
                $this->SetY(25);
            }
        }
    }

    // This function prints out the heading when a letter
    // changes.
    function Add_Header($sLetter)
    {
        $this->Check_Lines(2, 0, 0);
        $this->SetTextColor(255);
        $this->SetFont($this->_Font,'B',12);
        $_PosX = $this->_Column == 0 ? $this->_Margin_Left : $this->w - $this->_Margin_Left - $this->_ColWidth;
        $_PosY = $this->GetY();
        $this->SetXY($_PosX, $_PosY);
        $this->Cell($this->_ColWidth, 5, $sLetter, 1, 1, "C", 1) ;
        $this->SetTextColor(0);
        $this->SetFont($this->_Font,'',$this->_Char_Size);
        $this->SetY($this->GetY() + 5);
    }

    // This prints the family name in BOLD
    function Print_Name($sName)
    {
        $this->SetFont($this->_Font,'B',12);
        $_PosX = $this->_Column == 0 ? $this->_Margin_Left : $this->w - $this->_Margin_Left - $this->_ColWidth;
        $_PosY = $this->GetY();
        $this->SetXY($_PosX, $_PosY);
        $this->MultiCell($this->_ColWidth, 5, $sName);
        $this->SetY($_PosY + $this->NbLines($this->_ColWidth, $sName) * 5);
        $this->SetFont($this->_Font,'',$this->_Char_Size);
    }

    function sGetCustomString($rsCustomFields, $aRow){
        $numCustomFields = mysql_num_rows($rsCustomFields);
        if ($numCustomFields > 0) {
            extract($aRow);
            $sSQL = "SELECT * FROM person_custom WHERE per_ID = " . $per_ID;
            $rsCustomData = RunQuery($sSQL);
            $aCustomData = mysql_fetch_array($rsCustomData, MYSQL_BOTH);
            $numCustomData = mysql_num_rows($rsCustomData);
            mysql_data_seek($rsCustomFields,0);
            $OutStr = ""; 
            while ( $rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_BOTH) ){
                extract($rowCustomField);
                $sCustom = "bCustom".$custom_Order;
                if($this->_Custom[$custom_Order]){
                    $currentFieldData = trim($aCustomData[$custom_Field]);
                    if($currentFieldData != ""){
                        $OutStr .= "   " . $custom_Name . ": " . $currentFieldData .= "\n";
                    }
                }
            }
            return $OutStr;
        }else{
            return "";
        }
        
    }

    // This function formats the string for the family info
    function sGetFamilyString( $aRow )
    {
        global $bDirFamilyPhone;
        global $bDirFamilyWork;
        global $bDirFamilyCell;
        global $bDirFamilyEmail;
        global $bDirWedding;
        global $bDirAddress;

        extract($aRow);

        $sFamilyStr = "";

        if ($bDirAddress)
        {
            if (strlen($fam_Address1)) { $sFamilyStr .= $fam_Address1 . "\n";  }
            if (strlen($fam_Address2)) { $sFamilyStr .= $fam_Address2 . "\n";  }
            if (strlen($fam_City)) { $sFamilyStr .= $fam_City . ", " . $fam_State . " " . $fam_Zip . "\n";  }
        }

        if ($bDirFamilyPhone && strlen($fam_HomePhone))
            $sFamilyStr .= "   " . gettext("Phone") . ": " . ExpandPhoneNumber($fam_HomePhone, $fam_Country, $bWierd) . "\n";
        if ($bDirFamilyWork && strlen($fam_WorkPhone))
            $sFamilyStr .= "   " . gettext("Work") . ": " . ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $bWierd) . "\n";
        if ($bDirFamilyCell && strlen($fam_CellPhone))
            $sFamilyStr .= "   " . gettext("Cell") . ": " . ExpandPhoneNumber($fam_CellPhone, $fam_Country, $bWierd) . "\n";
        if ($bDirFamilyEmail && strlen($fam_Email))
            $sFamilyStr .= "   " . gettext("Email") . ": " . $fam_Email . "\n";
        if ($bDirWedding && ($fam_WeddingDate > 0))
            $sFamilyStr .= "   " . gettext("Wedding") . ": " . Date("m/d/Y", mysql_to_epoch($fam_WeddingDate)) . "\n";

        return $sFamilyStr;
    }

    // This function formats the string for the head of household.
    // NOTE: This is used for the Head AND Spouse (called twice)
    function sGetHeadString($rsCustomFields, $aHead )
    {
        global $bDirBirthday;
        global $bDirPersonalPhone;
        global $bDirPersonalWork;
        global $bDirPersonalCell;
        global $bDirPersonalEmail;
        global $bDirPersonalWorkEmail;

        extract($aHead);

        $sHeadStr = "";

        if ( strlen($per_LastName) && (strtolower($per_LastName) != strtolower($this->sLastName)) )
            $bDifferentLastName = true;
        else
            $bDifferentLastName = false;

        // First time build with last name, second time append spouse name.
        if (strlen($this->sRecordName)) {
            $this->sRecordName .= " " . gettext("and") . " " . $per_FirstName;
            if ($bDifferentLastName)
                $this->sRecordName .= " (" . $per_LastName . ")";
        }
        else {
            $this->sRecordName = $this->sLastName . ", " . $per_FirstName;
            if ($bDifferentLastName)
                $this->sRecordName .= " (" . $per_LastName . ")";
        }

        $sHeadStr .= $per_FirstName;
        if ($bDifferentLastName)
            $sHeadStr .= " " . $per_LastName;
        $iTempLen = strlen($sHeadStr);

        if ($bDirBirthday && $per_BirthMonth && $per_BirthDay)
            $sHeadStr .= sprintf(" (%d/%d)\n", $per_BirthMonth, $per_BirthDay);
        else
        {
            $sHeadStr .= "\n";
            $iTempLen = strlen($sHeadStr);
        }

        $sCountry = SelectWhichInfo($per_Country,$fam_Country,false);

        if ($bDirPersonalPhone && strlen($per_HomePhone)) {
            $TempStr = ExpandPhoneNumber($per_HomePhone, $sCountry, $bWierd);
            $sHeadStr .= "   " . gettext("Phone") . ": " . $TempStr .= "\n";
        }
        if ($bDirPersonalWork && strlen($per_WorkPhone)) {
            $TempStr = ExpandPhoneNumber($per_WorkPhone, $sCountry, $bWierd);
            $sHeadStr .= "   " . gettext("Work") . ": " . $TempStr .= "\n";
        }
        if ($bDirPersonalCell && strlen($per_CellPhone)) {
            $TempStr = ExpandPhoneNumber($per_CellPhone, $sCountry, $bWierd);
            $sHeadStr .= "   " . gettext("Cell") . ": " . $TempStr .= "\n";
        }
        if ($bDirPersonalEmail && strlen($per_Email))
            $sHeadStr .= "   " . gettext("Email") . ": " . $per_Email .= "\n";
        if ($bDirPersonalWorkEmail && strlen($per_WorkEmail))
            $sHeadStr .= "   " . gettext("Work/Other Email") . ": " . $per_WorkEmail .= "\n";
            
        $sHeadStr .= $this->sGetCustomString($rsCustomFields, $aHead);

        // If there is no additional information for either head or spouse, there is no
        // need to print the name in the sublist, they are already are in the heading.
        if (strlen($sHeadStr) == $iTempLen)
            return "";
        else
            return $sHeadStr;
    }

    // This function formats the string for other family member records
    function sGetMemberString( $aRow )
    {
        global $bDirPersonalPhone;
        global $bDirPersonalWork;
        global $bDirPersonalCell;
        global $bDirPersonalEmail;
        global $bDirPersonalWorkEmail;
        global $bDirBirthday;
        global $aChildren;

        extract($aRow);

        $sMemberStr = $per_FirstName;

        // Check to see if family member has different last name
        if ( strlen($per_LastName) && ($per_LastName != $this->sLastName) )
            $sMemberStr .= " " . $per_LastName;

        if ($bDirBirthday && $per_BirthMonth && $per_BirthDay)
        {
            $sMemberStr .= sprintf(" (%d/%d", $per_BirthMonth, $per_BirthDay);
            if ($per_BirthYear && in_array($per_fmr_ID, $aChildren))
                $sMemberStr .= sprintf("/%d)\n", $per_BirthYear);
            else
                $sMemberStr .= ")\n";
        }
        else
        {
            $sMemberStr .= "\n";
        }

        $sCountry = SelectWhichInfo($per_Country,$fam_Country,false);

        if ($bDirPersonalPhone && strlen($per_HomePhone)) {
            $TempStr = ExpandPhoneNumber($per_HomePhone, $sCountry, $bWierd);
            $sMemberStr .= "   " . gettext("Phone") . ": " . $TempStr .= "\n";
        }
        if ($bDirPersonalWork && strlen($per_WorkPhone)) {
            $TempStr = ExpandPhoneNumber($per_WorkPhone, $sCountry, $bWierd);
            $sMemberStr .= "   " . gettext("Work") . ": " . $TempStr .= "\n";
        }
        if ($bDirPersonalCell && strlen($per_CellPhone)) {
            $TempStr = ExpandPhoneNumber($per_CellPhone, $sCountry, $bWierd);
            $sMemberStr .= "   " . gettext("Cell") . ": " . $TempStr .= "\n";
        }
        if ($bDirPersonalEmail && strlen($per_Email))
            $sMemberStr .= "   " . gettext("Email") . ": " . $per_Email .= "\n";
        if ($bDirPersonalWorkEmail && strlen($per_WorkEmail))
            $sMemberStr .= "   " . gettext("Work/Other Email") . ": " . $per_WorkEmail .= "\n";

        return $sMemberStr;
    }

    // Number of lines is only for the $text parameter
    function Add_Record($sName, $text, $numlines, $fid, $pid)
    {
        
        $this->Check_Lines($numlines, $fid, $pid);

        $this->Print_Name($sName);

        $_PosX = $this->_Column == 0 ? $this->_Margin_Left : $this->w - $this->_Margin_Left - $this->_ColWidth;
        $_PosY = $this->GetY();

        $this->SetXY($_PosX, $_PosY);
        
        $dirimg = "";
        $famimg = "../Images/Family/".$fid.".jpg";
        if (file_exists($famimg)) $dirimg = $famimg;
        
        $perimg = "../Images/Person/".$pid.".jpg";
        if (file_exists($perimg)) $dirimg = $perimg;


        if ($dirimg != "") 
        {
            $s = getimagesize($dirimg);
            $h = ($this->_ColWidth / $s[0]) * $s[1];
            $_PosY += 2;
            $this->Image($dirimg, $_PosX, $_PosY, $this->_ColWidth);
            $this->SetXY($_PosX, $_PosY + $h + 2);
        }
        
        $this->MultiCell($this->_ColWidth, 5, $text, 0, 'L');
        $this->SetY($this->GetY() + 5);
    }
}

// Get and filter the classifications selected
$count = 0;
if($_POST["sDirClassifications"] != "")
{
    foreach ($_POST["sDirClassifications"] as $Cls)
    {
        $aClasses[$count++] = FilterInput($Cls,'int');
    }
    $sDirClassifications = implode(",",$aClasses);
}
else
{
    $sDirClassifications = "";
}
$count = 0;
foreach ($_POST["sDirRoleHead"] as $Head)
{
    $aHeads[$count++] = FilterInput($Head,'int');
}
$sDirRoleHeads = implode(",",$aHeads);

$count = 0;
foreach ($_POST["sDirRoleSpouse"] as $Spouse)
{
    $aSpouses[$count++] = FilterInput($Spouse,'int');
}
$sDirRoleSpouses = implode(",",$aSpouses);

$count = 0;
foreach ($_POST["sDirRoleChild"] as $Child)
{
    $aChildren[$count++] = FilterInput($Child,'int');
}

// Get other settings
$bDirAddress = isset($_POST["bDirAddress"]);
$bDirWedding = isset($_POST["bDirWedding"]);
$bDirBirthday = isset($_POST["bDirBirthday"]);
$bDirFamilyPhone = isset($_POST["bDirFamilyPhone"]);
$bDirFamilyWork = isset($_POST["bDirFamilyWork"]);
$bDirFamilyCell = isset($_POST["bDirFamilyCell"]);
$bDirFamilyEmail = isset($_POST["bDirFamilyEmail"]);
$bDirPersonalPhone = isset($_POST["bDirPersonalPhone"]);
$bDirPersonalWork = isset($_POST["bDirPersonalWork"]);
$bDirPersonalCell = isset($_POST["bDirPersonalCell"]);
$bDirPersonalEmail = isset($_POST["bDirPersonalEmail"]);
$bDirPersonalWorkEmail = isset($_POST["bDirPersonalWorkEmail"]);
$bDirPhoto = isset($_POST["bDirPhoto"]);

$sChurchName = FilterInput($_POST["sChurchName"]);
$sDirectoryDisclaimer = FilterInput($_POST["sDirectoryDisclaimer"]);
$sChurchAddress = FilterInput($_POST["sChurchAddress"]);
$sChurchCity = FilterInput($_POST["sChurchCity"]);
$sChurchState = FilterInput($_POST["sChurchState"]);
$sChurchZip = FilterInput($_POST["sChurchZip"]);
$sChurchPhone = FilterInput($_POST["sChurchPhone"]);

$bDirUseTitlePage = isset($_POST["bDirUseTitlePage"]);


// Instantiate the directory class and build the report.
$pdf = new PDF_Directory();

// Get the list of custom person fields
$sSQL = "SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order";
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysql_num_rows($rsCustomFields);

if ($numCustomFields > 0) {
    while ( $rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_ASSOC) ){ 
        $pdf->AddCustomField($rowCustomField['custom_Order']
                            , isset($_POST["bCustom".$rowCustomField['custom_Order']])
                            );
    }
}

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
    while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
        $pdf->$cfg_name = $cfg_value;
    }
}

$pdf->AddPage();

if ($bDirUseTitlePage) $pdf->TitlePage();

if (strlen($sDirClassifications)) $sClassQualifier = "AND per_cls_ID in (" . $sDirClassifications . ")";

if (!empty($_POST["GroupID"]))
{
    $sGroupTable = ", person2group2role_p2g2r";

    $count = 0;
    foreach ($_POST["GroupID"] as $Grp)
    {
        $aGroups[$count++] = FilterInput($Grp,'int');
    }
    $sGroupsList = implode(",",$aGroups);

    $sWhereExt .= "AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID in (" . $sGroupsList . ")";

    // This is used by per-role queries to remove duplicate rows from people assigned multiple groups.
    $sGroupBy = " GROUP BY per_ID";
}

if ($_POST['cartdir'] != null)
{
    $sWhereExt .= "AND per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ")";
}

$mysqlinfo = mysql_get_server_info();
$mysqltmp = explode(".", $mysqlinfo);
$mysqlversion = $mysqltmp[0];
if(count($mysqltmp[1] > 1)) 
    $mysqlsubversion = $mysqltmp[1]; 
    else $mysqlsubversion = 0;
if($mysqlversion >= 4){
    // This query is similar to that of the CSV export with family roll-up.
    // Here we want to gather all unique families, and those that are not attached to a family.
    $sSQL = "(SELECT *, 0 AS memberCount, per_LastName AS SortMe FROM person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID = 0 $sWhereExt $sClassQualifier )
        UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 $sWhereExt $sClassQualifier  GROUP BY per_fam_ID HAVING memberCount = 1)
        UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 $sWhereExt $sClassQualifier  GROUP BY per_fam_ID HAVING memberCount > 1)
        ORDER BY SortMe";
}else if($mysqlversion == 3 && $mysqlsubversion >= 22){
    // If UNION not supported use this query with temporary table.  Prior to version 3.22 no IF EXISTS statement.
    $sSQL = "DROP TABLE IF EXISTS tmp;";
    $rsRecords = mysql_query($sSQL) or die(mysql_error());
    $sSQL = "CREATE TABLE tmp TYPE = MyISAM SELECT *, 0 AS memberCount, per_LastName AS SortMe FROM person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID = 0 $sWhereExt $sClassQualifier ;"; 
    $rsRecords = mysql_query($sSQL) or die(mysql_error());
    $sSQL = "INSERT INTO tmp SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 $sWhereExt $sClassQualifier GROUP BY per_fam_ID HAVING memberCount = 1;"; 
    $rsRecords = mysql_query($sSQL) or die(mysql_error());
    $sSQL = "INSERT INTO tmp SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID > 0 $sWhereExt $sClassQualifier GROUP BY per_fam_ID HAVING memberCount > 1;";
    $rsRecords = mysql_query($sSQL) or die(mysql_error());
    $sSQL = "SELECT DISTINCT * FROM tmp ORDER BY SortMe";

}else{
    die(gettext("This option requires at least version 3.22 of MySQL!  Hit browser back button to return to ChurchInfo."));
}

$rsRecords = RunQuery($sSQL);

// This is used for the headings for the letter changes.
// Start out with something that isn't a letter to force the first one to work
$sLastLetter = "0";

while ($aRow = mysql_fetch_array($rsRecords))
{
    $OutStr = "";
    extract($aRow);
    
    $isFamily = false;

    if ($memberCount > 1) // Here we have a family record.
    {
        $iFamilyID = $per_fam_ID;
        $isFamily = true;

        $pdf->sRecordName = "";
        $pdf->sLastName = $fam_Name;
        $OutStr .= $pdf->sGetFamilyString($aRow);
        $bNoRecordName = true;

        // Find the Head of Household
        $sSQL = "SELECT * from person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID 
            WHERE per_fam_ID = " . $iFamilyID . " 
            AND per_fmr_ID in ($sDirRoleHeads) $sWhereExt $sClassQualifier $sGroupBy";
        $rsPerson = RunQuery($sSQL);

        if (mysql_num_rows($rsPerson) > 0)
        {
            $aHead = mysql_fetch_array($rsPerson);
            $OutStr .= $pdf->sGetHeadString($rsCustomFields, $aHead);
            $bNoRecordName = false;
        }

        // Find the Spouse of Household
        $sSQL = "SELECT * from person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID 
            WHERE per_fam_ID = " . $iFamilyID . " 
            AND per_fmr_ID in ($sDirRoleSpouses) $sWhereExt $sClassQualifier $sGroupBy";
        $rsPerson = RunQuery($sSQL);

        if (mysql_num_rows($rsPerson) > 0)
        {
            $aSpouse = mysql_fetch_array($rsPerson);
            $OutStr .= $pdf->sGetHeadString($rsCustomFields, $aSpouse);
            $bNoRecordName = false;
        }

        // In case there was no head or spouse, just set record name to family name
        if ($bNoRecordName)
            $pdf->sRecordName = $fam_Name;

        // Find the other members of a family
        $sSQL = "SELECT * from person_per $sGroupTable LEFT JOIN family_fam ON per_fam_ID = fam_ID
            WHERE per_fam_ID = " . $iFamilyID . " AND !(per_fmr_ID in ($sDirRoleHeads))
            AND !(per_fmr_ID in ($sDirRoleSpouses))  $sWhereExt $sClassQualifier $sGroupBy";
        $rsPerson = RunQuery($sSQL);

        while ($aRow = mysql_fetch_array($rsPerson))
        {
           $OutStr .= $pdf->sGetMemberString($aRow);
           $OutStr .= $pdf->sGetCustomString($rsCustomFields, $aRow);
        }
    }
    else
    {
        if (strlen($fam_Name))
            $pdf->sLastName = $fam_Name;
        else
            $pdf->sLastName = $per_LastName;
        $pdf->sRecordName = $pdf->sLastName . ", " . $per_FirstName;

        if ($bDirBirthday && $per_BirthMonth && $per_BirthDay)
            $pdf->sRecordName .= sprintf(" (%d/%d)", $per_BirthMonth, $per_BirthDay);

        SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, false);
        $sAddress2 = SelectWhichInfo($per_Address2, $fam_Address2, false);
        $sCity = SelectWhichInfo($per_City, $fam_City, false);
        $sState = SelectWhichInfo($per_State, $fam_State, false);
        $sZip = SelectWhichInfo($per_Zip, $fam_Zip, false);
        $sHomePhone = SelectWhichInfo($per_HomePhone, $fam_HomePhone, false);
        $sWorkPhone = SelectWhichInfo($per_WorkPhone, $fam_WorkPhone, false);
        $sCellPhone = SelectWhichInfo($per_CellPhone, $fam_CellPhone, false);
        $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);

        if ($bDirAddress)
        {
            if (strlen($sAddress1)) { $OutStr .= $sAddress1 . "\n";  }
            if (strlen($sAddress2)) { $OutStr .= $sAddress2 . "\n";  }
            if (strlen($sCity)) { $OutStr .= $sCity . ", " . $sState . " " . $sZip . "\n";  }
        }
        if (($bDirFamilyPhone || $bDirPersonalPhone) && strlen($sHomePhone)) {
            $TempStr = ExpandPhoneNumber($sHomePhone, $sDefaultCountry, $bWierd);
            $OutStr .= "   " . gettext("Phone") . ": " . $TempStr . "\n";
        }
        if (($bDirFamilyWork || $bDirPersonalWork) && strlen($sWorkPhone)) {
            $TempStr = ExpandPhoneNumber($sWorkPhone, $sDefaultCountry, $bWierd);
            $OutStr .= "   " . gettext("Work") . ": " . $TempStr . "\n";
        }
        if (($bDirFamilyCell || $bDirPersonalCell) && strlen($sCellPhone)) {
            $TempStr = ExpandPhoneNumber($sCellPhone, $sDefaultCountry, $bWierd);
            $OutStr .= "   " . gettext("Cell") . ": " . $TempStr . "\n";
        }
        if (($bDirFamilyEmail || $bDirPersonalEmail) && strlen($sEmail))
            $OutStr .= "   " . gettext("Email") . ": " . $sEmail . "\n";
        if ($bDirPersonalWorkEmail && strlen($per_WorkEmail))
            $OutStr .= "   " . gettext("Work/Other Email") . ": " . $per_WorkEmail .= "\n";
            
         // Custom Fields   
        $OutStr .= $pdf->sGetCustomString($rsCustomFields, $aRow);
        
    }

    // Count the number of lines in the output string
    if (strlen($OutStr))
        $numlines = $pdf->NbLines($pdf->_ColWidth,$OutStr) ; 
    else
        $numlines = 0;

    if ($numlines > 0)
    {
        if (strtoupper($sLastLetter) != strtoupper(substr($pdf->sRecordName,0,1)))
        {
            $pdf->Check_Lines($numlines+2, 0, 0);
            $sLastLetter = strtoupper(substr($pdf->sRecordName,0,1));
            $pdf->Add_Header($sLastLetter);
        }
        
        // if photo include pass the id, otherwise 0 equates to no family/pers
        $fid = 0; $pid = 0;
        if ($bDirPhoto) 
        {
            if ($isFamily) 
                $fid = $fam_ID; 
            else 
                $pid = $per_ID;
        }
        $pdf->Add_Record($pdf->sRecordName, $OutStr, $numlines, $fid, $pid);  // another hack: added +1
    }
}

if($mysqlversion == 3 && $mysqlsubversion >= 22){
    $sSQL = "DROP TABLE IF EXISTS tmp;";
    mysql_query($sSQL,$cnInfoCentral);
}
    
if ($iPDFOutputType == 1)
    $pdf->Output("Directory-" . date("Ymd-Gis") . ".pdf", true);
else
    $pdf->Output();    
?>
