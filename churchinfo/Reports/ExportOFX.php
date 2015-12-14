<?php
/*******************************************************************************
*
*  filename    : Reports/PrintDeposit.php
*  last change : 2013-02-21
*  description : Creates a PDF of the current deposit slip
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

global $iChecksPerDepositForm;

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

// Security
//if (!$_SESSION['bFinance'] && !$_SESSION['bAdmin']) {
//	Redirect("Menu.php");
//	exit;
//}
$fundTotal = array ();

$iDepositSlipID = 0;
if (array_key_exists ("deposit", $_POST))
	$iDepositSlipID = FilterInput($_POST["deposit"],"int");
	
if (!$iDepositSlipID && array_key_exists ('iCurrentDeposit', $_SESSION))
	$iDepositSlipID = $_SESSION['iCurrentDeposit'];

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
// If no DepositSlipId, redirect to the menu
if ((!$_SESSION['bAdmin'] && $bCSVAdminOnly && $output != "pdf") || !$iDepositSlipID)
{
	Redirect("Menu.php");
	exit;
}

// SQL Statement

// Get the list of funds
	$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
	$rsFunds = RunQuery($sSQL);


//Get the payments for this deposit slip
$sSQL = "SELECT plg_plgID, plg_FYID, plg_date, plg_amount, plg_method, plg_CheckNo, 
	         plg_comment, a.fam_Name AS FamilyName, b.fun_Name AS fundName
			 FROM pledge_plg
			 LEFT JOIN family_fam a ON plg_FamID = a.fam_ID
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_PledgeOrPayment = 'Payment' AND plg_depID = " . $iDepositSlipID . " ORDER BY pledge_plg.plg_method DESC, pledge_plg.plg_date";
$rsPledges = RunQuery($sSQL);

// Exit if no rows returned
$iCountRows = mysql_num_rows($rsPledges);
if ($iCountRows < 1)
{
	header("Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Individual%20Deposit%20Report"); 
}

	while ($aRow = mysql_fetch_array($rsPledges))
	{
		extract($aRow);
		if (!$fundName)
		{
			$fundTotal['UNDESIGNATED'] += $plg_amount;
		}
		else 
		{
			if (array_key_exists ($fundName, $fundTotal))
			{
				$fundTotal[$fundName] += $plg_amount;
			}
			else
			{
				$fundTotal[$fundName] = $plg_amount;
			}
		}
		
	}
	$orgName = "ChurchCRM Deposit Data";
	$buffer = "OFXHEADER:100".PHP_EOL.
				"DATA:OFXSGML".PHP_EOL.
				"VERSION:102".PHP_EOL.
				"SECURITY:NONE".PHP_EOL.
				"ENCODING:USASCII".PHP_EOL.
				"CHARSET:1252".PHP_EOL.
				"COMPRESSION:NONE".PHP_EOL.
				"OLDFILEUID:NONE".PHP_EOL.
				"NEWFILEUID:NONE".PHP_EOL.PHP_EOL;
	$buffer .= "<OFX>";
	$buffer .= "<SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>20151213191205.762[-5:EST]<LANGUAGE>ENG<FI><ORG>".$orgName."<FID>12345</FI></SONRS></SIGNONMSGSRSV1>";
	$buffer .= "<BANKMSGSRSV1>".
      "<STMTTRNRS>".
        "<TRNUID>23382938".
        "<STATUS>".
          "<CODE>0".
         "<SEVERITY>INFO".
        "</STATUS>";
        

	if (mysql_num_rows ($rsFunds) > 0) 
	{
		mysql_data_seek($rsFunds,0);
		while ($row = mysql_fetch_array($rsFunds))
		{
		$fun_name = $row["fun_Name"];
			if (array_key_exists ($fun_name, $fundTotal) && $fundTotal[$fun_name] > 0) 
			{
				$buffer.="<STMTRS>".
					  "<CURDEF>USD".
					  "<BANKACCTFROM>".
						"<BANKID>".$orgName.
						"<ACCTID>".$fun_name.
						"<ACCTTYPE>SAVINGS".
					 "</BANKACCTFROM>";
				$buffer.=
				"<STMTTRN>".
					"<TRNTYPE>CREDIT".
					"<DTPOSTED>20070315".
					"<DTUSER>20070315".
					"<TRNAMT>".$fundTotal[$fun_name].
					"<FITID>". 
					"<NAME>TRANSFER".
					"<MEMO>".$fun_name.
				"</STMTTRN></STMTRS>";
			}
		}
		if (array_key_exists ('UNDESIGNATED', $fundTotal) && $fundTotal['UNDESIGNATED']) 
		{
			#$pdf->SetXY ($curX, $curY);
	   		#$pdf->Write (8, gettext("UNDESIGNATED"));
			#$amountStr = sprintf ("%.2f", $fundTotal['UNDESIGNATED']);
	   		#$pdf->PrintRightJustified ($curX + $summaryMethodX, $curY, $amountStr);
			#$curY += $summaryIntervalY;
			
		}	
	}

	$buffer .= "
      </STMTTRNRS></BANKTRANLIST></OFX>";
	// Export file
	header("Content-Disposition: attachment; filename=ChurchInfo-" . date("Ymd-Gis") . ".ofx");
	echo $buffer;
?>