<?php

namespace ChurchCRM\Reports;

class PDF_AddressReport extends ChurchInfoReport
{
    // Private properties
    private $_Margin_Left = 0;         // Left Margin
    private $_Margin_Top = 0;         // Top margin
    private $_Char_Size = 12;        // Character size
    private $_CurLine = 0;
    private $_Column = 0;
    private $_Font = 'Times';
    private $sFamily;
    private $sLastName;

    public function num_lines_in_fpdf_cell($w, $txt)
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n") {
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
        global $paperFormat;
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

        $this->Set_Char_Size(20);
        $this->Write(10, 'ChurchCRM USPS Address Verification Report');
        $this->Set_Char_Size(12);
    }

    public function Check_Lines($numlines)
    {
        $CurY = $this->GetY();  // Temporarily store off the position

        // Need to determine if we will extend beyoned 20mm from the bottom of
        // the page.
        $this->SetY(-20);
        if ($this->_Margin_Top + (($this->_CurLine + $numlines) * 5) > $this->GetY()) {
            // Next Page
            $this->_CurLine = 5;
            $this->AddPage();
        }
        $this->SetY($CurY); // Put the position back
    }

    // Number of lines is only for the $text parameter
    public function Add_Record($fam_Str, $sLuStr, $sErrStr)
    {
        $sLuStr .= "\n".$sErrStr;

        $numlines1 = $this->num_lines_in_fpdf_cell(90, $fam_Str);
        $numlines2 = $this->num_lines_in_fpdf_cell(90, $sLuStr);

        if ($numlines1 > $numlines2) {
            $numlines = $numlines1;
        } else {
            $numlines = $numlines2;
        }

        $this->Check_Lines($numlines);

        $_PosX = $this->_Margin_Left;
        $_PosY = $this->_Margin_Top + ($this->_CurLine * 5);
        $this->SetXY($_PosX, $_PosY);
        $this->MultiCell(90, 5, $fam_Str, 0, 'L');

        $_PosX += 100;
        $this->SetXY($_PosX, $_PosY);
        $this->MultiCell(90, 5, $sLuStr, 0, 'L');

        $this->_CurLine += $numlines + 1;
    }
}
