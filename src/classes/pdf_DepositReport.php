<?php

class PDF_DepositReport extends ChurchInfoReport
{

  // Private properties
  var $_Char_Size = 10;        // Character size
  var $_Font = "Courier";

  // Sets the character size
  // This changes the line height too
  function Set_Char_Size($pt)
  {
    if ($pt > 3) {
      $this->_Char_Size = $pt;
      $this->SetFont($this->_Font, '', $this->_Char_Size);
    }
  }

  function PrintRightJustified($x, $y, $str)
  {
    $iLen = strlen($str);
    $nMoveBy = 10 - 2 * $iLen;
    $this->SetXY($x + $nMoveBy, $y);
    $this->Write(8, $str);
  }

  // Constructor
  function PDF_DepositReport()
  {
    parent::FPDF("P", "mm", $this->paperFormat);

    $this->_Font = "Courier";
    $this->SetMargins(0, 0);
    $this->Open();
    $this->Set_Char_Size(10);
    $this->SetAutoPageBreak(false);
  }


}

?>
