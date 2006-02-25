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

	function GetAddress1 ()				{ return $this->USaddress1; }
	function GetAddress2 ()				{ return $this->USaddress2; }
	function GetCity ()					{ return $this->UScity; }
	function GetState ()				{ return $this->USstate; }
	function GetZip ()					{ return $this->USzip; }
	function GetReturnCode ()			{ return $this->ReturnCode;}
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

		$fp = fopen('/var/www/html/message.txt', 'w+');
		fwrite($fp, $response . "\n" . $url);
		fclose($fp);

		// Initialize return values to NULL
		$this->SearchesLeft	= "";
		$this->ReturnCode	= "";

		if (!$response) {
			$this->ReturnCode    = "9";
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
		$this->USaddress1	= "";
		$this->USaddress2	= "";
		$this->UScity		= "";
		$this->USstate		= "";
		$this->USzip		= "";

		if (!$response) {
			$this->USaddress1  = "Connection failure.\n";
			$this->USaddress1 .= $base . "\n";
			$this->USaddress1 .= "Incorrect server name and/or path or server unavailable.";
		} else {
			if(strlen(XMLparseIST($response,"ErrorCodes"))){
				$this->USaddress1  = XMLparseIST($response,"ErrorCodes") . " ";
				$this->USaddress1	.= XMLparseIST($response,"ErrorDesc");
			} elseif (XMLparseIST($response,"ReturnCodes") > 1) {	
				$this->USaddress1  = "Multiple matches.  Unable to determine proper match.";
			} elseif (XMLparseIST($response,"ReturnCodes") < 1) {
				$this->USaddress1  = "No match found.";
			} else {
				$this->USaddress1	= XMLparseIST($response,"DeliveryLine1");
				$this->USaddress2	= XMLparseIST($response,"DeliveryLine2");
				$this->UScity		= XMLparseIST($response,"City");
				$this->USstate		= XMLparseIST($response,"State");
				$this->USzip		= XMLparseIST($response,"ZipAddon");
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

	echo "At this time ChurchInfo uses XML web services provided by Intelligent ";
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

	echo "getAccountInfo ReturnCode = " . $myISTReturnCode . "<br>";
	echo "Searches Left = " . $myISTSearchesLeft . "<br><br>";

	echo "To conserve funds the following rules are followed to determine if ";
	echo "an address lookup should be performed.<br>";
	echo "1) The family record was added to ChurchInfo after the last lookup<br>";
	echo "2) The family record was edited after the last lookup<br>";
	echo "3) It's been more than one year since the family record has been verified<br>";
	echo "4) The address must be a US address (Country = United States)<br><br><br>";

	// Housekeeping ... Delete families from the table istlookup_lu that
	// do not exist in the table family_fam.  This happens whenever
	// a family is deleted from family_fam.


	$sSQL  = "SELECT fam_ID FROM family_fam ";
	$rsResult = RunQuery($sSQL);
	$sALLIDs = "";
	while ($aRow = mysql_fetch_array($rsResult)) {
		extract($aRow);
		if(strlen($sAllIDs))
			$sAllIDs .= ",";
		$sAllIDs .= $fam_ID;
	}
//	echo "All IDs = " . $sAllIDs . "<br>";

	$sSQL  = "SELECT lu_fam_ID FROM istlookup_lu ";
	$sSQL .= "WHERE lu_fam_ID NOT IN (" . $sAllIDs . ")";
	$rsResult = RunQuery($sSQL);
	$sOrphanedIDs = "";
	while ($aRow = mysql_fetch_array($rsResult)) {
		extract($aRow);
		if(strlen($sOrphanedIDs))
			$sOrphanedIDs .= ",";
		$sOrphanedIDs .= $lu_fam_ID;
	}
	echo "Orphaned IDs = " . $sOrphanedIDs . "<br>";

	if (strlen($sOrphanedIDs)){
		$sSQL  = "DELETE FROM istlookup_lu ";
		$sSQL .= "WHERE lu_fam_ID IN (" . $sOrphanedIDs . ")";
		RunQuery($sSQL);
	}

	// More Housekeeping ... Delete families from the table istlookup_lu that
	// have had their family_fam records edited since the last lookup

	$sSQL  = "SELECT fam_ID FROM family_fam INNER JOIN istlookup_lu ";
	$sSQL .= "ON family_fam.fam_ID = istlookup_lu.lu_fam_ID "; 
	$sSQL .= "WHERE fam_DateLastEdited > lu_LookupDateTime ";
	$sSQL .= "AND fam_DateLastEdited IS NOT NULL";
	$rsResult = RunQuery($sSQL);
	$sUpDatedIDs = "";
	while ($aRow = mysql_fetch_array($rsResult)) {
		extract($aRow);
		if(strlen($sUpDatedIDs))
			$sUpDatedIDs .= ",";
		$sUpDatedIDs .= $fam_ID;
	}
	echo "UpDated IDs = " . $sUpDatedIDs . "<br>";

	if (strlen($sUpDatedIDs)) {
		$sSQL  = "DELETE FROM istlookup_lu ";
		$sSQL .= "WHERE lu_fam_ID IN (" . $sUpDatedIDs . ")";
		RunQuery($sSQL);
	}

	// More Housekeeping ... Delete families from the table istlookup_lu that
	// have not had a lookup performed in more than one year.  Zip codes and street
	// names occasionally change so an annual verification is a good idea.

	$oneYearAgo = date("Y-m-d H:i:s",strtotime("-12 months"));
//	$oneYearAgo = date("Y-m-d H:i:s");
//	echo "oneYearAgo = " . $oneYearAgo . "<br>";

	$sSQL  = "SELECT lu_fam_ID FROM istlookup_lu "; 
	$sSQL .= "WHERE '" . $oneYearAgo . "' > lu_LookupDateTime";
	$rsResult = RunQuery($sSQL);
	$sOutDatedIDs = "";
	while ($aRow = mysql_fetch_array($rsResult)) {
		extract($aRow);
		if(strlen($sOutDatedIDs))
			$sOutDatedIDs .= ",";
		$sOutDatedIDs .= $fam_ID;
	}
	echo "OutDated IDs = " . $sOutDatedIDs . "<br>";

	if (strlen($sOutDatedIDs)) {
		$sSQL  = "DELETE FROM istlookup_lu ";
		$sSQL .= "WHERE lu_fam_ID IN (" . $sOutDatedIDs . ")";
		RunQuery($sSQL);
	}


	// All housekeeping is finished !!!


	// Get count of non-US addresses
	$sSQL  = "SELECT count(fam_ID) AS nonustotal FROM family_fam ";
	$sSQL .= "WHERE fam_Country NOT IN ('United States')";
	$rsResult = RunQuery($sSQL);
	extract(mysql_fetch_array($rsResult));
	echo "Total non-US family addresses = " . $nonustotal . "<br>";

	// Get count of US addresses
	$sSQL  = "SELECT count(fam_ID) AS ustotal FROM family_fam ";
	$sSQL .= "WHERE fam_Country IN ('United States')";
	$rsResult = RunQuery($sSQL);
	extract(mysql_fetch_array($rsResult));
	echo "Total US family addresses = " . $ustotal . "<br><br>"; 

	// Get count of US addresses that do not require a fresh lookup
	$sSQL  = "SELECT count(lu_fam_ID) AS usokay FROM istlookup_lu";
	$rsResult = RunQuery($sSQL);
	extract(mysql_fetch_array($rsResult));
	echo "Total US addresses not requiring lookup = " . $usokay . "<br>"; 

	// Get count of US addresses ready for lookup
	$sSQL  = "SELECT count(fam_ID) AS newcount FROM family_fam ";
	$sSQL .= "WHERE fam_Country IN ('United States') AND fam_ID NOT IN (";
	$sSQL .= "SELECT lu_fam_ID from istlookup_lu)";
	$rs = RunQuery($sSQL);
	extract(mysql_fetch_array($rs));
	echo "Count of US addresses ready for lookup = " . $newcount . "<br><br>";

	if ($myISTSearchesLeft < $newcount) {
		echo "Your account only has " . $myISTSearchesLeft . " searches remaining. ";
		echo "This is not sufficient to lookup all addresses so the job will ";
		echo "finish incomplete.";

	}


	?>
	<form method="POST" action="Reports/AddressReport.php">

	<p align="center"><BR>
	<input type="submit" class="icButton" name="Submit" 
	<?php echo 'value="' . gettext("Create Report") . '"'; ?>>
	<input type="button" class="icButton" name="Cancel" 
	<?php echo 'value="' . gettext("Cancel") . '"'; ?>
	onclick="javascript:document.location='Menu.php';">
	</p>
	</form>
	<?php
}
require "Include/Footer.php";
?>
