<?php

namespace ChurchCRM\Reports;

class PdfDepositReport extends ChurchInfoReport
{
    // Private properties
    public $_Char_Size = 10;        // Character size
    public $_Font = 'Courier';

    // Sets the character size
    // This changes the line height too
    public function setCharSize($pt): void
    {
        if ($pt > 3) {
            $this->_Char_Size = $pt;
            $this->SetFont($this->_Font, '', $this->_Char_Size);
        }
    }

    public function printRightJustified($x, $y, $str): void
    {
        $iLen = strlen($str);
        $nMoveBy = 10 - 2 * $iLen;
        $this->SetXY($x + $nMoveBy, $y);
        $this->Write(8, $str);
    }

    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);

      //
        $this->SetFont('courier');
        $this->SetMargins(0, 0);
        $this->setCharSize(10);
        $this->SetAutoPageBreak(false);
    }
}
