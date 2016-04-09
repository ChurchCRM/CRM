<?php
/*******************************************************************************
*
*  filename    : Reports/USISTAddressReport.php
*  website     : http://www.churchcrm.io
*  copyright   : Copyright Contributors
*  description : Creates address verification report
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';
require '../Include/ReportConfig.php';

// If user does not have permission redirect to the menu.
if (!$bUSAddressVerification) {
	Redirect('Menu.php');
	exit;
}

class PDF_AddressReport extends ChurchInfoReport {

	// Private properties
	private $_Margin_Left = 0;         // Left Margin
	private $_Margin_Top  = 0;         // Top margin 
	private $_Char_Size   = 12;        // Character size
	private $_CurLine     = 0;
	private $_Column      = 0;
	private $_Font        = 'Times';
	private $sFamily;
	private $sLastName;

	function num_lines_in_fpdf_cell($w,$txt)
	{
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

	// Sets the character size
	// This changes the line height too
	function Set_Char_Size($pt) {
		if ($pt > 3) {
			$this->_Char_Size = $pt;
			$this->SetFont($this->_Font,'',$this->_Char_Size);
		}
	}

	// Constructor
	function PDF_AddressReport() {
		global $paperFormat;
		parent::FPDF('P', 'mm', $this->paperFormat);

		$this->_Column      = 0;
		$this->_CurLine     = 2;
		$this->_Font        = 'Times';
		$this->SetMargins(0,0);
		$this->Open();
		$this->Set_Char_Size(12);
		$this->AddPage();
		$this->SetAutoPageBreak(false);

		$this->_Margin_Left = 12;
		$this->_Margin_Top  = 12;

		$this->Set_Char_Size(20);
		$this->Write (10, 'ChurchInfo USPS Address Verification Report');
		$this->Set_Char_Size(12);
	}

	function Check_Lines($numlines)	{
		$CurY = $this->GetY();  // Temporarily store off the position

		// Need to determine if we will extend beyoned 20mm from the bottom of
		// the page.
		$this->SetY(-20);
		if ($this->_Margin_Top+(($this->_CurLine+$numlines)*5) > $this->GetY())
		{
			// Next Page
			$this->_CurLine = 5;
			$this->AddPage();
		}
		$this->SetY($CurY); // Put the position back
	}

	// Number of lines is only for the $text parameter
	function Add_Record($fam_Str, $sLuStr, $sErrStr) {

		$sLuStr .= "\n" . $sErrStr;

		$numlines1 = $this->num_lines_in_fpdf_cell(90,$fam_Str);
		$numlines2 = $this->num_lines_in_fpdf_cell(90,$sLuStr);

		if ($numlines1 > $numlines2)
			$numlines = $numlines1;
		else
			$numlines = $numlines2;

		$this->Check_Lines($numlines);

		$_PosX = $this->_Margin_Left;
		$_PosY = $this->_Margin_Top+($this->_CurLine*5);
		$this->SetXY($_PosX, $_PosY);
		$this->MultiCell(90, 5, $fam_Str, 0, 'L');

		$_PosX += 100;
		$this->SetXY($_PosX, $_PosY);
		$this->MultiCell(90, 5, $sLuStr, 0, 'L');

		$this->_CurLine += $numlines + 1;
	}
}

// Read in report settings from database
$rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
}

if ($_POST['MismatchReport']) {
	$iNum = 1;
	$sWhere = "WHERE fam_Country IN ('United States') ";
	$sMissing = 'Ready for Lookup.  Lookup not done.';
}
elseif ($_POST['NonUSReport']) {
	$iNum = 2;
	$sWhere = "WHERE fam_Country NOT IN ('United States') ";
	$sMissing = 'Unable to perform lookup for non-US address';
} else {
	Redirect('USISTAddressVerification.php');
}

// Instantiate the class and build the report.
$pdf = new PDF_AddressReport();

$sSQL  = 'SELECT * FROM family_fam ';
$sSQL .= $sWhere;
$sSQL .= 'ORDER BY fam_Name';

$rsFamilies = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsFamilies)) {

	extract($aRow);

	$sSQL  = 'SELECT count(lu_fam_ID) AS idexists FROM istlookup_lu ';
	$sSQL .= "WHERE lu_fam_ID IN ($fam_ID)";
	
	$rsLookup = RunQuery($sSQL);
	extract(mysql_fetch_array($rsLookup));
	if ($idexists == '0') {
		$lu_DeliveryLine1 = $sMissing;
		$lu_DeliveryLine2 = '';
		$lu_LastLine = '';
		$lu_ErrorCodes = '';
		$lu_ErrorDesc = '';
	} else {

		$sSQL  = 'SELECT * FROM istlookup_lu ';
		$sSQL .= "WHERE lu_fam_ID IN ($fam_ID)";
		$rsLookup = RunQuery($sSQL);
		extract(mysql_fetch_array($rsLookup));

	}

	// This check alows cities like Coeur d'Alene ID to be accepted also as Coeur d Alene ID
	$lu_LastLine = str_replace("'"," ",$lu_LastLine);
	$fam_City = str_replace("'"," ",$fam_City);		

	// This may not be the best way to handle multiple line addresses
	if (strtoupper($fam_Address2) == strtoupper($lu_DeliveryLine1)) {
		$lu_DeliveryLine1 = $fam_Address1;
		$lu_DeliveryLine2 = $fam_Address2;
	}

	$fam_Str  = '';
	if(strlen($fam_Address1))
		$fam_Str .= $fam_Address1 . "\n";
	if(strlen($fam_Address2))
		$fam_Str .= $fam_Address2 . "\n";
	$fam_Str .= "$fam_City $fam_State $fam_Zip";


	$lu_Str = '';
	$lu_ErrStr = '';
	if(strlen($lu_DeliveryLine1))
		$lu_Str .= $lu_DeliveryLine1 . "\n";
	if(strlen($lu_DeliveryLine2))
		$lu_Str .= $lu_DeliveryLine2 . "\n";
	$lu_Str .= $lu_LastLine;

	$lu_Str = strtoupper($lu_Str);

	if (strtoupper($fam_Str) == $lu_Str) { // Filter nuisance error messages
		if ($lu_ErrorCodes == '10' ||
		    $lu_ErrorCodes == '06' ||
			$lu_ErrorCodes == '14'   ){
			$lu_ErrorCodes = '';
		}
	}

    $bErrorDesc = FALSE;
	if(strlen($lu_ErrorCodes)){
		if($lu_ErrorCodes != 'x1x2'){ // Filter error messages associated with subscribing to
									  // CorrectAddress instead of CorrectAddress with Addons
			$lu_ErrStr = "$lu_ErrorCodes $lu_ErrorDesc";
            $bErrorDesc = TRUE;
		}
	}
    
    $pos1 = strrpos($lu_ErrorDesc, 'no match found');
    $pos2 = strrpos($lu_ErrorDesc, 'not enough information provided');

    if (($pos1 === FALSE) && ($pos2 === FALSE))
        $bErrorDesc = FALSE;

	if ((strtoupper($fam_Str) != $lu_Str) || $bErrorString) {
		// Print both addresses if they don't match exactly

		$fam_Str = $fam_Name . "\n" . $fam_Str;
		$lu_Str = "Intelligent Search Technology, Ltd. Response\n" . $lu_Str;
		$pdf->Add_Record($fam_Str, $lu_Str, $lu_ErrStr);
	}
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate

if ($iPDFOutputType == 1)
	$pdf->Output('Addresses-' . date('Ymd-Gis') . '.pdf', 'D');
else
	$pdf->Output();	

?>
