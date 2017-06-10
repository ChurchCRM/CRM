<?php

namespace ChurchCRM\Reports;

class PDF_GroupDirectory extends ChurchInfoReport
{
    // Private properties
    public $_Margin_Left = 0;         // Left Margin
    public $_Margin_Top = 0;         // Top margin
    public $_Char_Size = 12;        // Character size
    public $_CurLine = 0;
    public $_Column = 0;
    public $_Font = 'Times';
    public $sFamily;
    public $sLastName;

    public function Header()
    {
        if ($this->PageNo() == 1) {
            global $sGroupName;
            global $sRoleName;
            //Select Arial bold 15
            $this->SetFont($this->_Font, 'B', 15);
            //Line break
            $this->Ln(7);
            //Move to the right
            $this->Cell(10);
            //Framed title
            $sTitle = $sGroupName.' - '.gettext('Group Directory');
            if (strlen($sRoleName)) {
                $sTitle .= ' ('.$sRoleName.')';
            }
            $this->Cell(197, 10, $sTitle, 1, 0, 'C');
        }
    }

    public function Footer()
    {
        //Go to 1.5 cm from bottom
        $this->SetY(-15);
        //Select Arial italic 8
        $this->SetFont($this->_Font, 'I', 8);
        //Print centered page number
        $this->Cell(0, 10, 'Page '.($this->PageNo()), 0, 0, 'C');
    }

    // Sets the character size
    // This changes the line height too
    public function Set_Char_Size($pt)
    {
        if ($pt > 3) {
            $this->_Char_Size = $pt;
            $this->SetFont($this->_Font, '', $this->_Char_Size);
        }
    }

    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);

        $this->_Column = 0;
        $this->_CurLine = 2;
        $this->_Font = 'Times';
        $this->SetMargins(0, 0);
        $this->Set_Char_Size(12);
        $this->AddPage();
        $this->SetAutoPageBreak(false);

        $this->_Margin_Left = 12;
        $this->_Margin_Top = 12;
    }

    public function Check_Lines($numlines)
    {
        $CurY = $this->GetY();  // Temporarily store off the position

        // Need to determine if we will extend beyoned 15mm from the bottom of
        // the page.
        $this->SetY(-15);
        if ($this->_Margin_Top + (($this->_CurLine + $numlines) * 5) > $this->GetY()) {
            // Next Column or Page
            if ($this->_Column == 1) {
                $this->_Column = 0;
                $this->_CurLine = 2;
                $this->AddPage();
            } else {
                $this->_Column = 1;
                $this->_CurLine = 2;
            }
        }
        $this->SetY($CurY); // Put the position back
    }

    // This function prints out the heading when a letter
    // changes.
/*	function Add_Header($sLetter)
    {
        $this->Check_Lines(2);
        $this->SetTextColor(255);
        $this->SetFont($this->_Font,'B',12);
        $_PosX = $this->_Margin_Left+($this->_Column*108);
        $_PosY = $this->_Margin_Top+($this->_CurLine*5);
        $this->SetXY($_PosX, $_PosY);
        $this->Cell(80, 5, $sLetter, 1, 1, "C", 1) ;
        $this->SetTextColor(0);
        $this->SetFont($this->_Font,'',$this->_Char_Size);
        $this->_CurLine+=2;
    }
*/

    // This prints the name in BOLD
    public function Print_Name($sName)
    {
        $this->SetFont($this->_Font, 'B', 12);
        $_PosX = $this->_Margin_Left + ($this->_Column * 108);
        $_PosY = $this->_Margin_Top + ($this->_CurLine * 5);
        $this->SetXY($_PosX, $_PosY);
        $this->Write(5, $sName);
        $this->SetFont($this->_Font, '', $this->_Char_Size);
        $this->_CurLine++;
    }

    // Number of lines is only for the $text parameter
    public function Add_Record($sName, $text, $numlines)
    {
        $this->Check_Lines($numlines);

        $this->Print_Name($sName);

        $_PosX = $this->_Margin_Left + ($this->_Column * 108);
        $_PosY = $this->_Margin_Top + ($this->_CurLine * 5);
        $this->SetXY($_PosX, $_PosY);
        $this->MultiCell(108, 5, $text);
        $this->_CurLine += $numlines;
    }
}
