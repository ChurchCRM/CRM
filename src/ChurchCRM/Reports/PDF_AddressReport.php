<?php

namespace ChurchCRM\Reports;

class PdfAddressReport extends ChurchInfoReport
{
    // Private properties
    private int $_Margin_Left = 12;         // Left Margin
    private int $_Margin_Top = 12;         // Top margin
    private int $_Char_Size = 12;        // Character size
    private int $_CurLine = 2;
    private int $_Column = 0;
    private string $_Font = 'Times';
    private $sFamily;
    private $sLastName;

    private function numLinesInFpdfCell(int $w, $txt): int
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

    // Sets the character size
    // This changes the line height too
    public function setCharSize($pt): void
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
        $this->SetMargins(0, 0);

        $this->setCharSize(12);
        $this->addPage();
        $this->SetAutoPageBreak(false);

        $this->setCharSize(20);
        $this->Write(10, 'ChurchCRM USPS Address Verification Report');
        $this->setCharSize(12);
    }

    public function checkLines($numlines): void
    {
        $CurY = $this->GetY();  // Temporarily store off the position

        // Need to determine if we will extend beyoned 20mm from the bottom of
        // the page.
        $this->SetY(-20);
        if ($this->_Margin_Top + (($this->_CurLine + $numlines) * 5) > $this->GetY()) {
            // Next Page
            $this->_CurLine = 5;
            $this->addPage();
        }
        $this->SetY($CurY); // Put the position back
    }

    // Number of lines is only for the $text parameter
    public function addRecord($fam_Str, string $sLuStr, string $sErrStr): void
    {
        $sLuStr .= "\n" . $sErrStr;

        $numlines1 = $this->numLinesInFpdfCell(90, $fam_Str);
        $numlines2 = $this->numLinesInFpdfCell(90, $sLuStr);

        if ($numlines1 > $numlines2) {
            $numlines = $numlines1;
        } else {
            $numlines = $numlines2;
        }

        $this->checkLines($numlines);

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
