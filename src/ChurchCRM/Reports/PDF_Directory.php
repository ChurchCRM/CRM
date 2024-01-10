<?php

namespace ChurchCRM\Reports;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\MiscUtils;

class PdfDirectory extends ChurchInfoReport
{
    // Private properties
    public $_Margin_Left = 13;        // Left Margin
    public $_Margin_Top = 13;         // Top margin
    public $_Char_Size = 10;        // Character size
    public $_Column = 0;
    public $_Font = 'Times';
    public $_Gutter = 5;
    public $_LS = 4;
    public $sFamily;
    public string $sRecordName;
    public $sLastName;
    public $_ColWidth = 58;
    public $_Custom = [];
    public $_NCols = 3;
    public $_PS = 'Letter';
    public $sSortBy = '';
    private string $sChurchNameEncoded;
    private string $sChurchAddressEncoded;
    private string $sChurchCityEncoded;
    private string $sChurchStateEncoded;

    public function __construct($nc = 1, $paper = 'letter', $fs = 10, $ls = 4)
    {
        parent::__construct('P', 'mm', $paper);
        $this->_Char_Size = $fs;
        $this->_LS = $ls;
        $this->SetMargins(0, 0);

        $this->setCharSize($this->_Char_Size);
        $this->SetAutoPageBreak(false);
        $this->_NCols = $nc;
        $this->_ColWidth = 190 / $nc - $this->_Gutter;

        $this->sChurchNameEncoded = iconv('UTF-8', 'ISO-8859-1', SystemConfig::getValue('sChurchName'));
        $this->sChurchAddressEncoded = iconv('UTF-8', 'ISO-8859-1', SystemConfig::getValue('sChurchAddress'));
        $this->sChurchCityEncoded = iconv('UTF-8', 'ISO-8859-1', SystemConfig::getValue('sChurchCity'));
        $this->sChurchStateEncoded = iconv('UTF-8', 'ISO-8859-1', SystemConfig::getValue('sChurchState'));
    }

    public function header(): void
    {
        global $bDirUseTitlePage;

        if (($this->PageNo() > 1) || ($bDirUseTitlePage == false)) {
            //Select Arial bold 15
            $this->SetFont($this->_Font, 'B', 15);
            //Line break
            $this->Ln(7);
            //Move to the right
            $this->SetX($this->_Margin_Left);
            //Framed title
            $this->Cell(
                $this->w - ($this->_Margin_Left * 2),
                10,
                $this->sChurchNameEncoded . ' - ' . gettext('Directory'),
                1,
                0,
                'C'
            );
            $this->SetY(25);
        }
    }

    public function footer(): void
    {
        global $bDirUseTitlePage;

        if (($this->PageNo() > 1) || ($bDirUseTitlePage == false)) {
            //Go to 1.7 cm from bottom
            $this->SetY(-17);
            //Select Arial italic 8
            $this->SetFont($this->_Font, 'I', 8);
            //Print centered page number
            $iPageNumber = $this->PageNo();
            if ($bDirUseTitlePage) {
                $iPageNumber--;
            }
            $this->Cell(0, 10, gettext('Page') . ' ' . $iPageNumber . '    ' . date(SystemConfig::getValue('sDateTimeFormat'), time()), 0, 0, 'C');  // in 2.6.0, create a new config for time formatting also
        }
    }

    public function titlePage(): void
    {
        global $sDirectoryDisclaimer;
        //Select Arial bold 15
        $this->SetFont($this->_Font, 'B', 15);

        if (is_readable(SystemConfig::getValue('bDirLetterHead'))) {
            $this->Image(SystemConfig::getValue('bDirLetterHead'), 10, 5, 190);
        }

        //Line break
        $this->Ln(5);
        //Move to the right
        $this->MultiCell(
            197,
            10,
            "\n\n\n" . $this->sChurchNameEncoded . "\n\n" . gettext('Directory') . "\n\n",
            0,
            'C'
        );
        $this->Ln(5);
        $today = date(SystemConfig::getValue('sDateFormatLong'));
        $this->MultiCell(197, 10, $today . "\n\n", 0, 'C');

        $sContact = sprintf(
            "%s\n%s, %s  %s\n\n%s\n\n",
            $this->sChurchAddressEncoded,
            $this->sChurchCityEncoded,
            $this->sChurchStateEncoded,
            SystemConfig::getValue('sChurchZip'),
            SystemConfig::getValue('sChurchPhone')
        );
        $this->MultiCell(197, 10, $sContact, 0, 'C');
        $this->Cell(10);
        $sDirectoryDisclaimer = iconv('UTF-8', 'ISO-8859-1', $sDirectoryDisclaimer);
        $this->MultiCell(197, 10, $sDirectoryDisclaimer, 0, 'C');
        $this->addPage();
    }

    // Sets the character size
    // This changes the line height too
    public function setCharSize($pt): void
    {
        if ($pt > 3) {
            $this->_Char_Size = $pt;
            $this->SetFont($this->_Font, '', $this->_Char_Size);
        }
    }

    public function addCustomField($order, $use): void
    {
        $this->_Custom[$order] = $use;
    }

    public function nbLines($w, $txt): int
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }

        return $nl;
    }

    public function checkLines($numlines, $img): void
    {
        // Need to determine if we will extend beyoned 17mm from the bottom of
        // the page.

        $h = 0; // check image height.  id will be zero if not included

        if (file_exists($img)) {
            $s = getimagesize($img);
            $h = ($this->_ColWidth / $s[0]) * $s[1];
        }

//      if ($this->GetY() + $h + $numlines * 5 > $this->h - 27)
        if ($this->GetY() + $h + $numlines * $this->_LS > $this->h - 27) {
            // Next Column or Page
            if ($this->_Column == $this->_NCols - 1) {
                $this->_Column = 0;
                $this->SetY(25);
                $this->addPage();
            } else {
                $this->_Column++;
                $this->SetY(25);
            }
        }
    }

    // This function prints out the heading when a letter
    // changes.
    public function addHeader($sLetter): void
    {
        $this->checkLines(2, null);
        $this->SetTextColor(255);
        $this->SetFont($this->_Font, 'B', $this->_Char_Size);
//        $_PosX = $this->_Column == 0 ? $this->_Margin_Left : $this->w - $this->_Margin_Left - $this->_ColWidth;
        $_PosX = ($this->_Column * ($this->_ColWidth + $this->_Gutter)) + $this->_Margin_Left;
        $_PosY = $this->GetY();
        $this->SetXY($_PosX, $_PosY);
//        $this->Cell($this->_ColWidth, 5, $sLetter, 1, 1, "C", 1) ;
        $this->Cell($this->_ColWidth, $this->_LS, $sLetter, 1, 1, 'C', 1);
        $this->SetTextColor(0);
        $this->SetFont($this->_Font, '', $this->_Char_Size);
//        $this->SetY($this->GetY() + 5);
        $this->SetY($this->GetY() + $this->_LS);
    }

    // This prints the family name in BOLD
    public function printName($sName): void
    {
        $this->SetFont($this->_Font, 'BU', $this->_Char_Size);
//        $_PosX = $this->_Column == 0 ? $this->_Margin_Left : $this->w - $this->_Margin_Left - $this->_ColWidth;
        $_PosX = ($this->_Column * ($this->_ColWidth + $this->_Gutter)) + $this->_Margin_Left;
        $_PosY = $this->GetY();
        $this->SetXY($_PosX, $_PosY);
//        $this->MultiCell($this->_ColWidth, 5, $sName);
        $this->MultiCell($this->_ColWidth, $this->_LS, $sName);
//        $this->SetY($_PosY + $this->nbLines($this->_ColWidth, $sName) * 5);
        $this->SetY($_PosY + $this->nbLines($this->_ColWidth, $sName) * $this->_LS);
        $this->SetFont($this->_Font, '', $this->_Char_Size);
    }

    public function sGetCustomString($rsCustomFields, $aRow): string
    {
        $numCustomFields = mysqli_num_rows($rsCustomFields);
        if ($numCustomFields > 0) {
            extract($aRow);
            $sSQL = 'SELECT * FROM person_custom WHERE per_ID = ' . $per_ID;
            $rsCustomData = RunQuery($sSQL);
            $aCustomData = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH);
            $numCustomData = mysqli_num_rows($rsCustomData);
            mysqli_data_seek($rsCustomFields, 0);
            $OutStr = '';
            while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                extract($rowCustomField);
                $sCustom = 'bCustom' . $custom_Order;
                if ($this->_Custom[$custom_Order]) {
                    $currentFieldData = displayCustomField($type_ID, $aCustomData[$custom_Field], $custom_Special);

//                    $currentFieldData = trim($aCustomData[$custom_Field]);
                    if ($currentFieldData != '') {
                        $OutStr .= '   ' . $custom_Name . ': ' . $currentFieldData .= "\n";
                    }
                }
            }

            return $OutStr;
        } else {
            return '';
        }
    }

    public function getBirthdayString($bDirBirthday, $per_BirthMonth, $per_BirthDay, $per_BirthYear, $per_Flags): string
    {
        if ($bDirBirthday && $per_BirthDay > 0 && $per_BirthMonth > 0) {
            return MiscUtils::formatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, '/', $per_Flags);
        }

        return '';
    }

    // This function formats the string for the family info
    public function sGetFamilyString($aRow): string
    {
        global $bDirFamilyPhone;
        global $bDirFamilyWork;
        global $bDirFamilyCell;
        global $bDirFamilyEmail;
        global $bDirWedding;
        global $bDirAddress;

        extract($aRow);

        $sFamilyStr = '';

        if ($bDirAddress) {
            //            if (strlen($fam_Address1)) { $sFamilyStr .= $fam_Address1 . "\n";  }
//            if (strlen($fam_Address2)) { $sFamilyStr .= $fam_Address2 . "\n";  }
            if (strlen($fam_Address1)) {
                $sFamilyStr .= $fam_Address1;
            }
            if (strlen($fam_Address2)) {
                $sFamilyStr .= '  ' . $fam_Address2;
            }
            $sFamilyStr .= "\n";
            if (strlen($fam_City)) {
                $sFamilyStr .= $fam_City . ', ' . $fam_State . ' ' . $fam_Zip . "\n";
            }
        }

        if ($bDirFamilyPhone && strlen($fam_HomePhone)) {
            $sFamilyStr .= '   ' . gettext('Phone') . ': ' . ExpandPhoneNumber($fam_HomePhone, $fam_Country, $bWierd) . "\n";
        }
        if ($bDirFamilyWork && strlen($fam_WorkPhone)) {
            $sFamilyStr .= '   ' . gettext('Work') . ': ' . ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $bWierd) . "\n";
        }
        if ($bDirFamilyCell && strlen($fam_CellPhone)) {
            $sFamilyStr .= '   ' . gettext('Cell') . ': ' . ExpandPhoneNumber($fam_CellPhone, $fam_Country, $bWierd) . "\n";
        }
        if ($bDirFamilyEmail && strlen($fam_Email)) {
            $sFamilyStr .= '   ' . gettext('Email') . ': ' . $fam_Email . "\n";
        }
        if ($bDirWedding && ($fam_WeddingDate > 0)) {
            $sFamilyStr .= '   ' . gettext('Wedding') . ': ' . date(SystemConfig::getValue('sDateFormatShort'), strtotime($fam_WeddingDate)) . "\n";
        }

        return $sFamilyStr;
    }

    // This function formats the string for the head of household.
    // NOTE: This is used for the Head AND Spouse (called twice)
    public function sGetHeadString($rsCustomFields, $aHead)
    {
        global $bDirBirthday;
        global $bDirPersonalPhone;
        global $bDirPersonalWork;
        global $bDirPersonalCell;
        global $bDirPersonalEmail;
        global $bDirPersonalWorkEmail;

        extract($aHead);

        $sHeadStr = '';

        if (strlen($per_LastName) && (strtolower($per_LastName) != strtolower($this->sLastName))) {
            $bDifferentLastName = true;
        } else {
            $bDifferentLastName = false;
        }

        // First time build with last name, second time append spouse name.
        if (strlen($this->sRecordName)) {
            $this->sRecordName .= ' ' . gettext('and') . ' ' . $per_FirstName;
            if ($bDifferentLastName) {
                $this->sRecordName .= ' ' . $per_LastName;
            }
            if (strlen($per_Suffix)) {
                $this->sRecordName .= ' ' . $per_Suffix;
            }
        } else {
            $this->sRecordName = $this->sLastName . ', ' . $per_FirstName;
            if ($bDifferentLastName) {
                $this->sRecordName .= ' ' . $per_LastName;
            }
            if (strlen($per_Suffix)) {
                $this->sRecordName .= ' ' . $per_Suffix;
            }
        }

        $sHeadStr .= $per_FirstName;
        if ($bDifferentLastName) {
            $sHeadStr .= ' ' . $per_LastName;
        }
        if (strlen($per_Suffix)) {
            $sHeadStr .= ' ' . $per_Suffix;
        }

        $iTempLen = strlen($sHeadStr);

        $sHeadStr .= ' ' . $this->getBirthdayString($bDirBirthday, $per_BirthMonth, $per_BirthDay, $per_BirthYear, $per_Flags) . "\n";

        $sCountry = SelectWhichInfo($per_Country, $fam_Country, false);

        if ($bDirPersonalPhone && strlen($per_HomePhone)) {
            $TempStr = ExpandPhoneNumber($per_HomePhone, $sCountry, $bWierd);
            $sHeadStr .= '   ' . gettext('Phone') . ': ' . $TempStr .= "\n";
        }
        if ($bDirPersonalWork && strlen($per_WorkPhone)) {
            $TempStr = ExpandPhoneNumber($per_WorkPhone, $sCountry, $bWierd);
            $sHeadStr .= '   ' . gettext('Work') . ': ' . $TempStr .= "\n";
        }
        if ($bDirPersonalCell && strlen($per_CellPhone)) {
            $TempStr = ExpandPhoneNumber($per_CellPhone, $sCountry, $bWierd);
            $sHeadStr .= '   ' . gettext('Cell') . ': ' . $TempStr .= "\n";
        }
        if ($bDirPersonalEmail && strlen($per_Email)) {
            $sHeadStr .= '   ' . gettext('Email') . ': ' . $per_Email .= "\n";
        }
        if ($bDirPersonalWorkEmail && strlen($per_WorkEmail)) {
            $sHeadStr .= '   ' . gettext('Work/Other Email') . ': ' . $per_WorkEmail .= "\n";
        }

        $sHeadStr .= $this->sGetCustomString($rsCustomFields, $aHead);

        // If there is no additional information for either head or spouse, there is no
        // need to print the name in the sublist, they are already are in the heading.
        if (strlen($sHeadStr) == $iTempLen) {
            return '';
        } else {
            return $sHeadStr;
        }
    }

    // This function formats the string for other family member records
    public function sGetMemberString($aRow): string
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
        if (strlen($per_LastName) && ($per_LastName != $this->sLastName)) {
            $sMemberStr .= ' ' . $per_LastName;
        }
        if (strlen($per_Suffix)) {
            $sMemberStr .= ' ' . $per_Suffix;
        }

        $sMemberStr .= ' ' . $this->getBirthdayString($bDirBirthday, $per_BirthMonth, $per_BirthDay, $per_BirthYear, $per_Flags) . "\n";

        $sCountry = SelectWhichInfo($per_Country, $fam_Country, false);

        if ($bDirPersonalPhone && strlen($per_HomePhone)) {
            $TempStr = ExpandPhoneNumber($per_HomePhone, $sCountry, $bWierd);
            $sMemberStr .= '   ' . gettext('Phone') . ': ' . $TempStr .= "\n";
        }
        if ($bDirPersonalWork && strlen($per_WorkPhone)) {
            $TempStr = ExpandPhoneNumber($per_WorkPhone, $sCountry, $bWierd);
            $sMemberStr .= '   ' . gettext('Work') . ': ' . $TempStr .= "\n";
        }
        if ($bDirPersonalCell && strlen($per_CellPhone)) {
            $TempStr = ExpandPhoneNumber($per_CellPhone, $sCountry, $bWierd);
            $sMemberStr .= '   ' . gettext('Cell') . ': ' . $TempStr .= "\n";
        }
        if ($bDirPersonalEmail && strlen($per_Email)) {
            $sMemberStr .= '   ' . gettext('Email') . ': ' . $per_Email .= "\n";
        }
        if ($bDirPersonalWorkEmail && strlen($per_WorkEmail)) {
            $sMemberStr .= '   ' . gettext('Work/Other Email') . ': ' . $per_WorkEmail .= "\n";
        }

        return $sMemberStr;
    }

    // Number of lines is only for the $text parameter
    public function addRecord($sName, $text, $numlines, $fid, $pid): void
    {
        $dirimg = '';
        if ($fid !== null) {
            $family = FamilyQuery::create()->findOneById($fid);
            if ($family && !$family->getPhoto()->isInitials() && file_exists($family->getPhoto()->getPhotoURI())) {
                $dirimg = $family->getPhoto()->getPhotoURI();
            }
        }
        if ($pid !== null) {
            $person = PersonQuery::create()->findOneById($pid);
            if ($person && !$person->getPhoto()->isInitials() && file_exists($person->getPhoto()->getPhotoURI())) {
                $dirimg = $person->getPhoto()->getPhotoURI();
            }
        }
        $this->checkLines($numlines, $dirimg);

        $this->printName(iconv('UTF-8', 'ISO-8859-1', $sName));

        $_PosX = ($this->_Column * ($this->_ColWidth + $this->_Gutter)) + $this->_Margin_Left;
        $_PosY = $this->GetY();

        $this->SetXY($_PosX, $_PosY);

        if ($dirimg != '') {
            $h = 20;
            $_PosY += 2;
            $this->Image($dirimg, $_PosX, $_PosY, $h);
            $this->SetXY($_PosX, $_PosY + $h + 2);
        }

        $this->MultiCell($this->_ColWidth, $this->_LS, iconv('UTF-8', 'ISO-8859-1', $text), 0, 'L');
        $this->SetY($this->GetY() + $this->_LS);
    }
}
