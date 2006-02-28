<?php
/*******************************************************************************
 *
 *  filename    : ISTAddressVerification.php
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright Contributors
 *  description : USPS address verification
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// This file verifies family address information using an on-line XML
// service provided by Intelligent Search Technology, Ltd.  Fees required.
// See https://www.name-searching.com/CaddressASP

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

function XMLparseIST($xmlstr,$xmlfield) {
	// Function to parse XML data from Intelligent Search Technolgy, Ltd.

	if(!(strpos($xmlstr,"<$xmlfield>") === FALSE) || 
		 strpos($xmlstr,"</$xmlfield>" === FALSE)){

		$startpos = strpos($xmlstr,"<$xmlfield>")+strlen("<$xmlfield>");
		$endpos = strpos($xmlstr,"</$xmlfield>");

		if ($endpos < $startpos)
			return "";

		return substr($xmlstr, $startpos, $endpos-$startpos);
	}
	
	return "";
}


class ISTAddressLookup {

	// This code is written to work with XML lookups provide by
	// Intelligent Search Technology, Ltd.
	// https://www.name-searching.com/CaddressASP/LoginForm.aspx

	function GetAddress1 ()				{ return $this->DeliveryLine1; }
	function GetAddress2 ()				{ return $this->DeliveryLine2; }
	function GetCity ()					{ return $this->City; }
	function GetState ()				{ return $this->State; }
	function GetZip ()					{ return $this->ZipAddon; }
	function GetZip5 ()					{ return $this->Zip; }
	function GetZip4 ()					{ return $this->Addon; }
	function GetLOTNumber ()			{ return $this->LOTNumber; }
	function GetDPCCheckdigit ()		{ return $this->DPCCheckdigit; }
	function GetRecordType ()			{ return $this->RecordType; }
	function GetLastLine ()				{ return $this->LastLine; }
	function GetCarrierRoute ()			{ return $this->CarrierRoute; }
	function GetReturnCode ()			{ return $this->ReturnCode; }
	function GetReturnCodes ()			{ return $this->ReturnCodes; }
	function GetErrorCodes ()			{ return $this->ErrorCodes; }
	function GetSearchesLeft ()			{ return $this->SearchesLeft; }

	function SetAddress ($address1, $address2, $city, $state) {
		$this->address1 = trim($address1);
		$this->address2 = trim($address2);
		$this->city     = trim($city);
		$this->state    = trim($state);
	}

	function getAccountInfo ($sISTusername, $sISTpassword) {
		// Returns account related information.  Currently, it is used to retrieve
		// remaining number of transactions.  Return codes are:
		// 0 - information retrieved successfully 
		// 1 - invalid account
		// 2 - account is disabled
		// 3 - account does not have access to CorrectAddress(R) XML web services
		// 4 - unspecified error

		$base	 = "https://www.name-searching.com/CaddressASP/";
		$base	.= "CorrectAddressWebService.asmx/getAccountInfo";


		$query_string     = "";
		$XmlArray         =  NULL;

		//NOTE: Fields with * sign are required.
		//intializing the parameters
 
		$username			= $sISTusername;				//  * (Type your username)
		$password			= $sISTpassword;				//  * (Type your password)

		$params = array(
						'username'         =>  $username,
						'password'         =>  $password
		);

		foreach ($params as $key => $value) { 
			$query_string .= "$key=" . urlencode($value) . "&";
		}

		$url = "$base?$query_string";

		$response = file_get_contents($url);

//		$fp = fopen('/var/www/html/message.txt', 'w+');
//		fwrite($fp, $response . "\n" . $url);
//		fclose($fp);

		// Initialize return values to NULL
		$this->SearchesLeft	= "";
		$this->ReturnCode	= "";

		if (!$response) {
			$this->ReturnCode   = "9";
			$this->SearchesLeft  = "Connection failure: " . $base;
			$this->SearchesLeft .= " Incorrect server name and/or path or server unavailable.";
		} else {
			$this->ReturnCode = XMLparseIST($response,"ReturnCode");

			switch ($this->ReturnCode) {
				case "0":
					$this->SearchesLeft = XMLparseIST($response,"SearchesLeft");
					break;
				case "1":
					$this->SearchesLeft = "Invalid Account";
					break;
				case "2":
					$this->SearchesLeft = "Account is disabled";
					break;
				case "3":
					$this->SearchesLeft  = "Account does not have access to CorrectAddress(R)";
					$this->SearchesLeft .= " XML web services";
					break;
				default:
					$this->SearchesLeft = "Error";
					break;
			}

		}




	}

	function wsCorrectA ($sISTusername, $sISTpassword) {

		// Lookup and Correct US address

		$base	 = "https://www.name-searching.com/CaddressASP/";
		$base	.= "CorrectAddressWebService.asmx/wsCorrectA";
//		$base	.= "CorrectAddressWebService.asmx/wsTigerCA";


		$query_string     = "";
		$XmlArray         =  NULL;

		//NOTE: Fields with * sign are required.
		//intializing the parameters
 
		$username			= $sISTusername;				//  * (Type your username)
		$password			= $sISTpassword;				//  * (Type your password)
		$firmname			= '';							//optional
		$urbanization		= '';							//optional
		$delivery_line_1	= $this->address1;				//  * (Type the street address1)
		$delivery_line_2	= $this->address2;				//optional
		$city_state_zip		= $this->city . " " . $this->state;	//  *
		$ca_codes			= '128            135         139';	//  * 
		$ca_filler			= '' ;							//   *
		$batchname			= '';							// optional


		$params = array(
						'username'         =>  $username,
						'password'         =>  $password,
						'firmname'         =>  $firmname,
						'urbanization'     =>  $urbanization,
						'delivery_line_1'  =>  $delivery_line_1,
						'delivery_line_2'  =>  $delivery_line_2,
						'city_state_zip'   =>  $city_state_zip,
						'ca_codes'         =>  $ca_codes,
						'ca_filler'        =>  $ca_filler,
						'batchname'        =>  $batchname                              
		);

		foreach ($params as $key => $value) { 
			$query_string .= "$key=" . urlencode($value) . "&";
		}

		$url = "$base?$query_string";

		$response = file_get_contents($url);

		$fp = fopen('/var/www/html/message.txt', 'w+');
		fwrite($fp, $response . "\n" . $url);
		fclose($fp);

		// Initialize return values to NULL
		$this->DeliveryLine1	= "";
		$this->DeliveryLine2	= "";
		$this->City				= "";
		$this->State			= "";
		$this->ZipAddon			= "";
		$this->Zip				= "";
		$this->Addon			= "";
		$this->LOTNumber		= "";
		$this->DPCCheckdigit	= "";
		$this->RecordType		= "";
		$this->LastLine			= "";
		$this->CarrierRoute		= "";
		$this->ReturnCodes		= "";
		$this->ErrorCodes		= "";
		$this->ErrorDesc		= "";
		$this->SearchesLeft		= "";

		if (!$response) {
			$this->DeliveryLine1  = "Connection failure.\n";
			$this->DeliveryLine1 .= $base . "\n";
			$this->DeliveryLine1 .= "Incorrect server name and/or path or server unavailable.";
			$this->ErrorCodes     = "xx";
			$this->ErrorDesc	  = "Incorrect server name and/or path or server unavailable.";
			$this->SearchesLeft   = "0";
		} else {
			$this->DeliveryLine1	= XMLparseIST($response,"DeliveryLine1");
			$this->DeliveryLine2	= XMLparseIST($response,"DeliveryLine2");
			$this->City				= XMLparseIST($response,"City");
			$this->State			= XMLparseIST($response,"State");
			$this->ZipAddon			= XMLparseIST($response,"ZipAddon");
			$this->Zip				= XMLparseIST($response,"Zip");
			$this->Addon			= XMLparseIST($response,"Addon");
			$this->LOTNumber		= XMLparseIST($response,"LOTNumber");
			$this->DPCCheckdigit	= XMLparseIST($response,"DPCCheckdigit");
			$this->RecordType		= XMLparseIST($response,"RecordType");
			$this->LastLine			= XMLparseIST($response,"LastLine");
			$this->CarrierRoute		= XMLparseIST($response,"CarrierRoute");
			$this->ReturnCodes		= XMLparseIST($response,"ReturnCodes");
			$this->ErrorCodes		= XMLparseIST($response,"ErrorCodes");
			$this->ErrorDesc		= XMLparseIST($response,"ErrorDesc");
			$this->SearchesLeft		= XMLparseIST($response,"SearchesLeft");

			if(strlen(XMLparseIST($response,"ErrorCodes"))){
				$this->DeliveryLine1 .= " " . XMLparseIST($response,"ErrorCodes");
				$this->DeliveryLine1 .= " " . XMLparseIST($response,"ErrorDesc");
			} 
			if (XMLparseIST($response,"ReturnCodes") > 1) {	
				$this->DeliveryLine1 .= " Multiple matches.  Unable to determine proper match.";
			}
			if (XMLparseIST($response,"ReturnCodes") < 1) {
				$this->DeliveryLine1 .= " No match found.";
			}
		}
	}
}

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext("US Address Verification");
require "Include/Header.php";


if(strlen($sISTusername) && strlen($sISTpassword)) {

	$myISTAddressLookup = new ISTAddressLookup;

	$myISTAddressLookup->getAccountInfo ($sISTusername, $sISTpassword);

	$myISTReturnCode = $myISTAddressLookup->GetReturnCode ();
	$myISTSearchesLeft = $myISTAddressLookup->GetSearchesLeft ();

} else {

	$myISTReturnCode = "9";
	$myISTSearchesLeft = "Missing sISTusername or sISTpassword";

}

if ($myISTReturnCode != "0") {

	echo "<br>";
	echo "getAccountInfo ReturnCode = " . $myISTReturnCode . "<br>";
	echo $myISTSearchesLeft . "<br><br>";
	echo "Please verify that your Intelligent Search Technology, Ltd. username and password ";
	echo "are correct.<br><br>";
	echo "Admin -> Edit General Settings -> sISTusername<br>";
	echo "Admin -> Edit General Settings -> sISTpassword<br><br>";
	echo "Follow the URL below to log in and manage your Intelligent Search Technology account ";
	echo "settings.  If you do not already have an account you may establish an account at this ";
	echo "URL.<br>";

	echo "<a href=\"https://www.name-searching.com/CaddressASP\">" . gettext("https://www.name-searching.com/CaddressASP") . "</a><br><br><br>";


	echo "If you are sure that your account username and password are correct and that your ";
	echo "account is in good standing it is possible that the server is currently unavailable ";
	echo "but may be back online if you try again later.<br><br><br>";

	echo "ChurchInfo uses XML web services provided by Intelligent ";
	echo "Search Technology, Ltd.  For information about CorrectAddress(R) Online Address ";
	echo "Verification Service visit the following URL.<br>";

	echo "<a href=\"http://www.intelligentsearch.com/address_verification/verify_address.html\">" . gettext("http://www.intelligentsearch.com/address_verification/verify_address.html") . "</a>";


} elseif ($myISTSearchesLeft == "0"){

	echo "<br>";
	echo "Searches Left = " . $myISTSearchesLeft . "<br><br>";
	echo "Follow the URL below to log in and manage your Intelligent Search Technology account ";
	echo "settings.<br>";

	echo "<a href=\"https://www.name-searching.com/CaddressASP\">" . gettext("https://www.name-searching.com/CaddressASP") . "</a><br><br><br>";


} else {
	// IST account is valid and working.  Time to get to work.

	echo "<h3>";
	echo "To conserve funds the following rules are used to determine if ";
	echo "an address lookup should be performed.<br>";
	echo "1) The family record was added to ChurchInfo after the last lookup<br>";
	echo "2) The family record was edited after the last lookup<br>";
	echo "3) It's been more than one year since the family record has been verified<br>";
	echo "4) The address must be a US address (Country = United States)<br><br>";
	echo "</h3>";

	// Housekeeping ... Delete families from the table istlookup_lu that
	// do not exist in the table family_fam.  This happens whenever
	// a family is deleted from family_fam.  (Or, more rarely, if a family
	// moves to another country)

	$sSQL  = "SELECT lu_fam_ID FROM istlookup_lu ";
	$rsIST = RunQuery($sSQL);
	$iOrphanCount = 0;
	while ($aRow = mysql_fetch_array($rsIST)) {
		extract ($aRow);
		// verify that this ID exists in family_fam with 
		// fam_Country = 'United States'
		$sSQL  = "SELECT count(fam_ID) as idexists FROM family_fam ";
		$sSQL .= "WHERE fam_ID='" . $lu_fam_ID . "' ";
		$sSQL .= "AND fam_Country='United States'";
		$rsExists = RunQuery($sSQL);
		extract(mysql_fetch_array($rsExists));
		if ($idexists == '0'){
			$sSQL  = "DELETE FROM istlookup_lu WHERE lu_fam_ID='" . $lu_fam_ID . "'";
			RunQuery($sSQL);
			$iOrphanCount++;
		}
	}
	echo "<h4>";
	if ($iOrphanCount)
		echo $iOrphanCount . " Orphaned IDs deleted.<br>";


	// More Housekeeping ... Delete families from the table istlookup_lu that
	// have had their family_fam records edited since the last lookup

	$sSQL  = "SELECT fam_ID FROM family_fam INNER JOIN istlookup_lu ";
	$sSQL .= "ON family_fam.fam_ID = istlookup_lu.lu_fam_ID "; 
	$sSQL .= "WHERE fam_DateLastEdited > lu_LookupDateTime ";
	$sSQL .= "AND fam_DateLastEdited IS NOT NULL";
	$rsUpdated = RunQuery($sSQL);
	$iUpdatedCount = 0;
	while ($aRow = mysql_fetch_array($rsUpdated)) {
		extract($aRow);
		$sSQL  = "DELETE FROM istlookup_lu WHERE lu_fam_ID='" . $fam_ID . "'";
		RunQuery($sSQL);
		$iUpdatedCount++;
	}
	if ($iUpdatedCount)
		echo $iUpdatedCount . " Updated IDs deleted.<br>";

	// More Housekeeping ... Delete families from the table istlookup_lu that
	// have not had a lookup performed in more than one year.  Zip codes and street
	// names occasionally change so an annual verification is a good idea.

	$oneYearAgo = date("Y-m-d H:i:s",strtotime("-12 months"));
//	$oneYearAgo = date("Y-m-d H:i:s");
//	echo "oneYearAgo = " . $oneYearAgo . "<br>";

	$sSQL  = "SELECT lu_fam_ID FROM istlookup_lu "; 
	$sSQL .= "WHERE '" . $oneYearAgo . "' > lu_LookupDateTime";
	$rsResult = RunQuery($sSQL);
	$iOutdatedCount = 0;
	while ($aRow = mysql_fetch_array($rsResult)) {
		extract($aRow);
		$sSQL  = "DELETE FROM istlookup_lu WHERE lu_fam_ID='" . $lu_fam_ID . "'";
		RunQuery($sSQL);
		$iOutdatedCount++;
	}
	if ($iOutdatedCount)
		echo $iOutdatedCount . " Outdated IDs deleted.<br>";

	// All housekeeping is finished !!!

	// Get count of non-US addresses
	$sSQL  = "SELECT count(fam_ID) AS nonustotal FROM family_fam ";
	$sSQL .= "WHERE fam_Country NOT IN ('United States')";
	$rsResult = RunQuery($sSQL);
	extract(mysql_fetch_array($rsResult));
	$iNonUSCount = intval($nonustotal);
	if ($iNonUSCount) {
		echo $iNonUSCount . " Non US addresses in database will not be verified.<br>";
	}

	// Get count of US addresses
	$sSQL  = "SELECT count(fam_ID) AS ustotal FROM family_fam ";
	$sSQL .= "WHERE fam_Country IN ('United States')";
	$rsResult = RunQuery($sSQL);
	extract(mysql_fetch_array($rsResult));
	$iUSCount = intval($ustotal);
	if ($iUSCount) {
		echo $iUSCount . " Total US addresses in database.<br>";
	}

	// Get count of US addresses that do not require a fresh lookup
	$sSQL  = "SELECT count(lu_fam_ID) AS usokay FROM istlookup_lu";
	$rsResult = RunQuery($sSQL);
	extract(mysql_fetch_array($rsResult));
	$iUSOkay = intval($usokay);
	if ($iUSOkay) {
		echo $iUSOkay . " US addresses have already had lookups performed.<br>";
	}

	// Get count of US addresses ready for lookup
	$sSQL  = "SELECT count(fam_ID) AS newcount FROM family_fam ";
	$sSQL .= "WHERE fam_Country IN ('United States') AND fam_ID NOT IN (";
	$sSQL .= "SELECT lu_fam_ID from istlookup_lu)";
	$rs = RunQuery($sSQL);
	extract(mysql_fetch_array($rs));
	$iEligible = intval($newcount);
	if ($iEligible)
		echo $iEligible . " US addresses are eligible for lookup.<br>";
	else
		echo "There are no US addresses eligible for lookup.<br>";
	echo "</h4>";

	if ($_POST['DoLookup']) {

		$sSQL  = "SELECT fam_ID, fam_Address1, fam_Address2, fam_City, fam_State FROM family_fam ";
		$sSQL .= "WHERE fam_Country='United States' ORDER BY fam_ID";
		$rsResult = RunQuery($sSQL);

		while ($aRow = mysql_fetch_array($rsResult)) {
			extract($aRow);
			$sSQL  = "SELECT count(lu_fam_ID) AS idexists FROM istlookup_lu ";
			$sSQL .= "WHERE lu_fam_ID='" . $fam_ID . "'";
			$rsResult2 = RunQuery($sSQL);
			extract(mysql_fetch_array($rsResult2));
			if ($idexists == "0") {
				//echo "<br>Family ID " . $fam_ID . " needs lookup<br>";

				$myISTAddressLookup = new ISTAddressLookup;
				$myISTAddressLookup->SetAddress ($fam_Address1, $fam_Address2, 
												$fam_City, $fam_State);

				$ret = $myISTAddressLookup->wsCorrectA ($sISTusername,$sISTpassword);

				$lu_fam_ID = MySQLquote(addslashes($fam_ID));
				$lu_LookupDateTime = MySQLquote(addslashes(date("Y-m-d H:i:s")));
				$lu_DeliveryLine1 = MySQLquote(addslashes($myISTAddressLookup->GetAddress1()));
				$lu_DeliveryLine2 = MySQLquote(addslashes($myISTAddressLookup->GetAddress2()));
				$lu_City = MySQLquote(addslashes($myISTAddressLookup->GetCity()));
				$lu_State = MySQLquote(addslashes($myISTAddressLookup->GetState()));
				$lu_ZipAddon = MySQLquote(addslashes($myISTAddressLookup->GetZip()));
				$lu_Zip = MySQLquote(addslashes($myISTAddressLookup->GetZip5()));
				$lu_Addon = MySQLquote(addslashes($myISTAddressLookup->GetZip4()));
				$lu_LOTNumber = MySQLquote(addslashes($myISTAddressLookup->GetLOTNumber()));
				$lu_DPCCheckdigit = MySQLquote(addslashes($myISTAddressLookup->GetDPCCheckdigit()));
				$lu_RecordType = MySQLquote(addslashes($myISTAddressLookup->GetRecordType()));
				$lu_LastLine = MySQLquote(addslashes($myISTAddressLookup->GetLastLine()));
				$lu_CarrierRoute = MySQLquote(addslashes($myISTAddressLookup->GetCarrierRoute()));
				$lu_ReturnCodes = MySQLquote(addslashes($myISTAddressLookup->GetReturnCodes()));
				$lu_ErrorCodes = MySQLquote(addslashes($myISTAddressLookup->GetErrorCodes()));

				//echo "<br>" . $lu_ErrorCodes;

				$iSearchesLeft = $myISTAddressLookup->GetSearchesLeft();
				if (!is_numeric($iSearchesLeft))
					$iSearchesLeft = 0;
				else
					$iSearchesLeft = intval($iSearchesLeft);

				if ($lu_ErrorCodes != "'xx'") {
				// Error code xx is one of the following
				// 1) Connection failure 2) Invalid username or password 3) No searches left 
				//
				// Insert data into istlookup_lu table
				//
				$sSQL  = "INSERT INTO istlookup_lu (";
				$sSQL .= "  lu_fam_ID,  lu_LookupDateTime,  lu_DeliveryLine1, ";
				$sSQL .= "  lu_DeliveryLine2,  lu_City,  lu_State,  lu_ZipAddon, ";
				$sSQL .= "  lu_Zip,  lu_Addon,  lu_LOTNumber,  lu_DPCCheckdigit,  lu_RecordType, ";
				$sSQL .= "  lu_LastLine,  lu_CarrierRoute,  lu_ReturnCodes,  lu_ErrorCodes) ";
				$sSQL .= "VALUES( ";
				$sSQL .= " $lu_fam_ID, $lu_LookupDateTime, $lu_DeliveryLine1, ";
				$sSQL .= " $lu_DeliveryLine2, $lu_City, $lu_State, $lu_ZipAddon, ";
				$sSQL .= " $lu_Zip, $lu_Addon, $lu_LOTNumber, $lu_DPCCheckdigit, $lu_RecordType, ";
				$sSQL .= " $lu_LastLine, $lu_CarrierRoute, $lu_ReturnCodes, $lu_ErrorCodes) ";

				//echo $sSQL;

				RunQuery($sSQL);
				}

				$bRedirect = TRUE;

				if ($iSearchesLeft < 30) {
					$bRedirect = FALSE;
					if ($lu_ErrorCodes != "'xx'") {
					echo "<h3>There are " . $iSearchesLeft . " searches remaining ";
					echo "in your account.  Searches will be performed one at a time until ";
					echo "your account balance is zero.  To enable bulk lookups you will ";
					echo "need to add funds to your Intelligent Search Technolgy account ";
					echo "at the following link.<br>";
					echo "<a href=\"https://www.name-searching.com/CaddressASP\">";
					echo gettext("https://www.name-searching.com/CaddressASP") . "</a><br></h3>";
					} else {
	echo "<h4>Lookup failed.  There is a problem with the connection or with your account.</h4>";
	echo "Please verify that your Intelligent Search Technology, Ltd. username and password ";
	echo "are correct.<br><br>";
	echo "Admin -> Edit General Settings -> sISTusername<br>";
	echo "Admin -> Edit General Settings -> sISTpassword<br><br>";
	echo "Follow the URL below to log in and manage your Intelligent Search Technology account ";
	echo "settings.  If you do not already have an account you may establish an account at this ";
	echo "URL.<br>";

	echo "<a href=\"https://www.name-searching.com/CaddressASP\">" . gettext("https://www.name-searching.com/CaddressASP") . "</a><br><br>";

	echo "If you are sure that your account username and password are correct and that your ";
	echo "account is in good standing it is possible that the server is currently unavailable ";
	echo "but may be back online if you try again later.<br><br>";
					}

					if ($iSearchesLeft) {
					?>
					<form method="POST" action="USISTAddressVerification.php">
					<input type=submit class=icButton name=DoLookup value="Perform Next Lookup">
					</form><br><br>
					<?php
					}
					break;
				}
			}
		}


		if ($bRedirect)		
			Redirect("USISTAddressVerification.php");
	}

	?>
	<table><tr>
	<?php

	if (!$_POST['DoLookup'] && $iEligible){
	?>
	<td><form method="POST" action="USISTAddressVerification.php">
	<input type=submit class=icButton name=DoLookup value="Perform Lookups">
	</form></td>
	<?php } ?>

	<?php if ($iUSOkay) { ?>
	<td><form method="POST" action="Reports/USISTAddressReport.php">
	<input type=submit class=icButton name=MismatchReport value="View Mismatch Report">
	</form></td>
	<?php } ?>

	<?php if ($iNonUSCount) { ?>
	<td><form method="POST" action="Reports/USISTAddressReport.php">
	<input type=submit class=icButton name=NonUSReport value="View Non-US Address Report">
	</form></td>
	<?php } ?>

	</tr></table>

	<?php
}
require "Include/Footer.php";
?>
