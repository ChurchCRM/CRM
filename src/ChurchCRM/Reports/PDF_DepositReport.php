<?php

namespace ChurchCRM\Reports;

class PDF_DepositReport extends ChurchInfoReport
{
    // Private properties
  public $_Char_Size = 10;        // Character size
  public $_Font = 'Courier';

  // Sets the character size
  // This changes the line height too
  public function Set_Char_Size($pt)
  {
      if ($pt > 3) {
          $this->_Char_Size = $pt;
          $this->SetFont($this->_Font, '', $this->_Char_Size);
      }
  }

    public function PrintRightJustified($x, $y, $str)
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
      $this->Set_Char_Size(10);
      $this->SetAutoPageBreak(false);
  }
}
