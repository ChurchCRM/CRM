<?php
/*******************************************************************************
*
*  filename    : Reports/VotingMembers.php
*  last change : 2004-12-07
*  description : Creates a PDF with names of voting members for a particular fiscal year
*
*  InfoCentral is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

//Get the Fiscal Year ID out of the querystring
$iFYID = FilterInput($_GET["FYID"],'int');
$iRequireDonationYears = FilterInput($_GET["RequireDonationYears"],'int');

class PDF_VotingMembers extends ChurchInfoReport {

	// Constructor
	function PDF_VotingMembers() {
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->SetFont("Times",'',10);
		$this->SetMargins(20,20);
		$this->Open();
		$this->SetAutoPageBreak(false);
		$this->AddPage();
	}
}

$pdf = new PDF_VotingMembers();

$topY = 10;
$curY = $topY;

$pdf->WriteAt ($pdf->leftX, $curY, (gettext ("Voting members ") . MakeFYString ($iFYID)));
$curY += 10;

$votingMemberCount = 0;

// Get all the families
$sSQL = "SELECT * FROM family_fam WHERE 1 ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

// Loop through families
while ($aFam = mysql_fetch_array($rsFamilies)) {
	extract ($aFam);

	// Get payments only
	$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = " . $fam_ID . " AND plg_PledgeOrPayment = 'Payment' AND
			 (" . $iFYID . "-plg_FYID<" . $iRequireDonationYears . ")";

	$rsPledges = RunQuery($sSQL);

	if (($iRequireDonationYears==0) || mysql_num_rows ($rsPledges) > 0) {

		$pdf->WriteAt ($pdf->leftX, $curY, $fam_Name);

		//Get the family members for this family
		$sSQL = "SELECT per_FirstName, per_LastName, cls.lst_OptionName AS sClassName
				FROM person_per
				LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
				WHERE per_fam_ID = " . $fam_ID . " AND cls.lst_OptionName='" . gettext ("Member") . "'";
		$rsFamilyMembers = RunQuery($sSQL);

		if (mysql_num_rows ($rsFamilyMembers) == 0)
			$curY += 5;

		while ($aMember = mysql_fetch_array($rsFamilyMembers)) {
			extract ($aMember);
			$pdf->WriteAt ($pdf->leftX + 30, $curY, ($per_FirstName . " " . $per_LastName));
			$curY += 5;
			if ($curY > 250) {
				$pdf->AddPage();
				$curY = $topY;
			}
			$votingMemberCount += 1;
		}
	}
}

$curY += 5;
$pdf->WriteAt ($pdf->leftX, $curY, "Number of Voting Members: " . $votingMemberCount);

if ($iPDFOutputType == 1)
	$pdf->Output("VotingMembers" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
