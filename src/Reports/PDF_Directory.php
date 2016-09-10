<?php
namespace ChurchCRM\Reports;
class PDF_Directory extends ChurchInfoReport {

    // Private properties
    var $_Margin_Left = 16;        // Left Margin
    var $_Margin_Top  = 0;         // Top margin
    var $_Char_Size   = 10;        // Character size
    var $_Column      = 0;
    var $_Font        = 'Times';
    var $_Gutter      = 5;
    var $_LS          = 4;
    var $sFamily;
    var $sLastName;
    var $_ColWidth    = 58;
    var $_Custom;
    var $_NCols       = 3;
    var $_PS          = 'Letter';
    var $sSortBy = "";

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
            $this->Cell($this->w - ($this->_Margin_Left*2),10,$this->sChurchName . " - " . gettext("Directory"),1,0,'C');
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
            $this->Cell(0,10, gettext("Page") . " " . $iPageNumber."    ".date("M d, Y g:i a",time()),0,0,'C');
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
    function __construct($nc=1, $paper='letter', $fs=10, $ls=4) {
      parent::__construct("P", "mm", $paper);
      $this->_Char_Size = $fs;
      $this->_LS = $ls;

      $this->_Column      = 0;
      $this->_Font        = "Times";
      $this->SetMargins(0,0);

      $this->Set_Char_Size($this->_Char_Size);
      $this->SetAutoPageBreak(false);

      $this->_Margin_Left = 13;
      $this->_Margin_Top  = 13;
      $this->_Custom = array();
      $this->_NCols = $nc;
      $this->_ColWidth = 190 / $nc - $this->_Gutter;
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


//      if ($this->GetY() + $h + $numlines * 5 > $this->h - 27)
        if ($this->GetY() + $h + $numlines * $this->_LS > $this->h - 27)
        {
            // Next Column or Page
            if ($this->_Column == $this->_NCols-1)
            {
                $this->_Column = 0;
                $this->SetY(25);
                $this->AddPage();
            }
            else
            {
                $this->_Column++;
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
        $this->SetFont($this->_Font,'B',$this->_Char_Size);
//        $_PosX = $this->_Column == 0 ? $this->_Margin_Left : $this->w - $this->_Margin_Left - $this->_ColWidth;
         $_PosX = ($this->_Column*($this->_ColWidth+$this->_Gutter)) + $this->_Margin_Left;
        $_PosY = $this->GetY();
        $this->SetXY($_PosX, $_PosY);
//        $this->Cell($this->_ColWidth, 5, $sLetter, 1, 1, "C", 1) ;
        $this->Cell($this->_ColWidth, $this->_LS, $sLetter, 1,1,"C",1);
        $this->SetTextColor(0);
        $this->SetFont($this->_Font,'',$this->_Char_Size);
//        $this->SetY($this->GetY() + 5);
	$this->SetY($this->GetY() + $this->_LS);
    }

    // This prints the family name in BOLD
    function Print_Name($sName)
    {
        $this->SetFont($this->_Font,'BU',$this->_Char_Size);
//        $_PosX = $this->_Column == 0 ? $this->_Margin_Left : $this->w - $this->_Margin_Left - $this->_ColWidth;
         $_PosX = ($this->_Column*($this->_ColWidth+$this->_Gutter)) + $this->_Margin_Left;
        $_PosY = $this->GetY();
        $this->SetXY($_PosX, $_PosY);
//        $this->MultiCell($this->_ColWidth, 5, $sName);
	$this->MultiCell($this->_ColWidth, $this->_LS, $sName);
//        $this->SetY($_PosY + $this->NbLines($this->_ColWidth, $sName) * 5);
	$this->SetY($_PosY + $this->NbLines($this->_ColWidth, $sName) * $this->_LS);
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

                	$currentFieldData = displayCustomField($type_ID, $aCustomData[$custom_Field], $custom_Special);

//                    $currentFieldData = trim($aCustomData[$custom_Field]);
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
//            if (strlen($fam_Address1)) { $sFamilyStr .= $fam_Address1 . "\n";  }
//            if (strlen($fam_Address2)) { $sFamilyStr .= $fam_Address2 . "\n";  }
	      if (strlen($fam_Address1)) { $sFamilyStr .= $fam_Address1;}
	      if (strlen($fam_Address2)) { $sFamilyStr .= "  ".$fam_Address2;}
	      $sFamilyStr .= "\n";
            if (strlen($fam_City)) { $sFamilyStr .= $fam_City . ", " . $fam_State . " " . $fam_Zip . "\n";  }
        }

        if ($bDirFamilyPhone && strlen($fam_HomePhone))
            $sFamilyStr .= "   " . gettext('Phone') . ": " . ExpandPhoneNumber($fam_HomePhone, $fam_Country, $bWierd) . "\n";
        if ($bDirFamilyWork && strlen($fam_WorkPhone))
            $sFamilyStr .= "   " . gettext('Work') . ": " . ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $bWierd) . "\n";
        if ($bDirFamilyCell && strlen($fam_CellPhone))
            $sFamilyStr .= "   " . gettext('Cell') . ": " . ExpandPhoneNumber($fam_CellPhone, $fam_Country, $bWierd) . "\n";
        if ($bDirFamilyEmail && strlen($fam_Email))
            $sFamilyStr .= "   " . gettext('Email') . ": " . $fam_Email . "\n";
        if ($bDirWedding && ($fam_WeddingDate > 0))
            $sFamilyStr .= "   " . gettext('Wedding') . ": " . Date("m/d/Y", strtotime($fam_WeddingDate)) . "\n";

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
                $this->sRecordName .= " " .$per_LastName;
            if (strlen ($per_Suffix))
	            $this->sRecordName .= " " . $per_Suffix;
        }
        else {
            $this->sRecordName = $this->sLastName . ", " . $per_FirstName;
            if ($bDifferentLastName)
                $this->sRecordName .= " " . $per_LastName;
            if (strlen ($per_Suffix))
	            $this->sRecordName .= " " . $per_Suffix;
        }

        $sHeadStr .= $per_FirstName;
        if ($bDifferentLastName)
            $sHeadStr .= " " . $per_LastName;
        if (strlen ($per_Suffix))
            $sHeadStr .= " " . $per_Suffix;

        $iTempLen = strlen($sHeadStr);

        if ($bDirBirthday && $per_BirthMonth && $per_BirthDay)
        {
            $sHeadStr .= sprintf(" (%d/%d", $per_BirthMonth, $per_BirthDay);
            if ($per_BirthYear  && ! $per_Flags)
                $sHeadStr .= sprintf("/%d)\n", $per_BirthYear);
            else
                $sHeadStr .= ")\n";
        }
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
        if (strlen ($per_Suffix))
            $sMemberStr .= " " . $per_Suffix;

        if ($bDirBirthday && $per_BirthMonth && $per_BirthDay)
        {
            $sMemberStr .= sprintf(" (%d/%d", $per_BirthMonth, $per_BirthDay);
            if ($per_BirthYear  && ! $per_Flags)
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

        $this->Print_Name(iconv("UTF-8","ISO-8859-1",$sName));

//        $_PosX = $this->_Column == 0 ? $this->_Margin_Left : $this->w - $this->_Margin_Left - $this->_ColWidth;
         $_PosX = ($this->_Column*($this->_ColWidth+$this->_Gutter)) + $this->_Margin_Left;
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

//        $this->MultiCell($this->_ColWidth, 5, $text, 0, 'L');

        $this->MultiCell($this->_ColWidth, $this->_LS, iconv("UTF-8","ISO-8859-1",$text), 0, 'L');
//        $this->SetY($this->GetY() + 5);
        $this->SetY($this->GetY() + $this->_LS);
    }
}

?>