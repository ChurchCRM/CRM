<?php
require "Include/Config.php";
require "Include/Functions.php";
include "Include/vancowebservices.php";
include "Include/VancoConfig.php";

$customerid = FilterInput ($_GET["autid"], "int");
$iAutID = $customerid;

$sSQL = "SELECT * FROM autopayment_aut WHERE aut_ID=" . $iAutID;
$rsAutRec = mysql_query($sSQL, $cnInfoCentral);
$aRow = mysql_fetch_array($rsAutRec);
extract($aRow); // Get this autopayment record into local variables

$accountType = "";
$accountNumber = "";
if ($aut_EnableBankDraft) {
	$accountType = "C";
	$accountNumber = $aut_Account;
} elseif ($aut_EnableCreditCard) {
	$accountType = "CC";
	$accountNumber = $aut_CreditCard;
}
$FullName = $aut_FirstName . " " . $aut_LastName;

class VancoToolsXML
{
	private $userid, $password, $clientid, $enckey, $test;
	
	function __construct($setUserid, $setPassword, $setClientid, $setEncKey, $setTest) {
		$this->userid = $setUserid;
		$this->password = $setPassword;
		$this->clientid = $setClientid;
		$this->enckey = $setEncKey;
		$this->test = $setTest;

//		echo "Inside VancoToolsXML __construct $this->userid password $this->password clientid $this->clientid enckey $this->enckey test $this->test <br>";
	}

	function generateRequestID()
	{
		/*
		Function:    generateRequestID()
		Description: Used to generate a unique request ID to be used in a Web Services request
		Parameters:  None
		Returns:     String value to be used as a request ID. Value will be date/time with a random 4 digit number appended
		*/
//		date_default_timezone_set('America/Chicago');
		$currenttime = date("YmdHis");
		$randomnumber = rand(0, 9999);
		return $currenttime.$randomnumber;
	}
	
	function PostXML($xmlstr)
	{
//		echo "Inside VancoToolsXML PostXML userid $this->userid password $this->password clientid $this->clientid enckey $this->enckey test $this->test <br>";

		$ReqHeaderBase = "";
		if ($this->test)
			$ReqHeaderBase  .= "POST /cgi-bin/wstest2.vps HTTP/1.1\n";
		else
			$ReqHeaderBase  .= "POST /cgi-bin/ws2.vps HTTP/1.1\n"; 
//			$ReqHeaderBase  .= "POST /cgi-bin/wsnvp.vps HTTP/1.1\n"; 
		$ReqHeaderBase .= "Host: " . $_SERVER['HTTP_HOST'] . "\n"; 
		$ReqHeaderBase .= "User-Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n"; 
		$ReqHeaderBase .= "Content-Type: application/x-www-form-urlencoded\n"; 
		
		$ReqHeader = $ReqHeaderBase . "Content-length: " . strlen($xmlstr) . "\nConnection: close\n\n"; 
		$Req = $ReqHeader . $xmlstr . "\n\n";
		
//		echo "Sending request: '" . $Req;
		
		//--- Open Connection --- 
		$vancoURL = "";
		if ($this->test)
			$vancoURL = "ssl://www.vancodev.com";
		else
			$vancoURL = "ssl://www.vancoservices.com";
		
//		echo "Opening connection to '$vancoURL'<br>";
		
		$socket = fsockopen($vancoURL, 443, $errno, $errstr, 15); 

		if (!$socket) {
		        echo "Failed to open socket connection to Vanco<br>"; 
		        echo "errno $errno<br>"; 
		        echo "errstr $errstr<br>"; 
		        $Result['errno']=$errno; 
		        $Result['errstr']=$errstr; 
		        return $Result; 
		} else { 

	    	// --- Send XML --- 
    		fwrite($socket, $Req);
    
		    $rets = "";
		
		    // --- Retrieve XML --- 
		    while (!feof($socket)) { 
		        $rets .= fgets($socket, 4096); 
		    }
		    fclose($socket); 
    
		    $rets = substr($rets, strpos($rets, '?'.'>') + 2); // Skip over the header and the xml tag
    
//		    printf ("Got string '%s'", $rets);
    
		    $xml=simplexml_load_string($rets);
//    		print_r($xml);
    		return ($xml);
		}
	}
}

$VancoObj = new VancoToolsXML ($VancoUserid, $VancoPassword, $VancoClientid, $VancoEnc_key, $VancoTest);

$datestr = date ("Y-m-d H:i:s");

$LoginXML= 
"<VancoWS>" .
	"<Auth>" .
		"<RequestType>Login</RequestType>".
        "<RequestID>".$VancoObj->generateRequestID()."</RequestID>" .
        "<RequestTime>$datestr</RequestTime>" .
        "<Version>2</Version>".
    "</Auth>".
    "<Request>".
        "<RequestVars>".
            "<UserID>$VancoUserid</UserID>".
            "<Password>$VancoPassword</Password>".
        "</RequestVars>".
    "</Request>".
"</VancoWS>"; 

$LoginRespXML = $VancoObj->PostXML ($LoginXML);
$sessionid = $LoginRespXML->Response->SessionID;

//printf ("Got session id %s", $sessionid);
		
$addCustomerXML = 
	"<VancoWS>".
		"<Auth>".
			"<RequestType>EFTAddEditCustomer</RequestType>".
			"<RequestID>".$VancoObj->generateRequestID()."</RequestID>".
			"<RequestTime>$datestr</RequestTime>".
			"<SessionID>$sessionid</SessionID>".
			"<Version>2</Version>".
		"</Auth>".
		"<Request>".
			"<RequestVars>".
				"<ClientID>$VancoClientid</ClientID>".
				"<CustomerID>$customerid</CustomerID>".
				"<CustomerName>$FullName</CustomerName>".
	      		"<CustomerAddress1>$aut_Address1</CustomerAddress1>".
	     		"<CustomerAddress2></CustomerAddress2>".
	      		"<CustomerCity>$aut_City</CustomerCity>".
	      		"<CustomerState>$aut_State</CustomerState>".
	      		"<CustomerZip>$aut_Zip</CustomerZip>".
	      		"<CustomerPhone>$aut_Phone</CustomerPhone>".
			"</RequestVars>".
		"</Request>".
	"</VancoWS>";

$addCustomerXmlResp = $VancoObj->PostXML ($addCustomerXML);

$addCCXML =
	"<VancoWS>".
		"<Auth>".
			"<RequestType>EFTAddEditPaymentMethod</RequestType>".
			"<RequestID>".$VancoObj->generateRequestID()."</RequestID>".
			"<RequestTime>$datestr</RequestTime>".
			"<SessionID>$sessionid</SessionID>".
			"<Version>2</Version>".
		"</Auth>".
		"<Request>".
			"<RequestVars>".
				"<ClientID>$VancoClientid</ClientID>".
				"<CustomerID>$customerid</CustomerID>".
				"<AccountType>$accountType</AccountType>".
				"<AccountNumber>$accountNumber</AccountNumber>".
				"<RoutingNumber>$aut_Route</RoutingNumber>".
				"<CardBillingName>$FullName</CardBillingName>".
				"<CardExpMonth>$aut_ExpMonth</CardExpMonth>".
				"<CardExpYear>$aut_ExpYear</CardExpYear>".
				"<SameCCBillingAddrAsCust>NO</SameCCBillingAddrAsCust>".
				"<CardBillingAddr1>$aut_Address1</CardBillingAddr1>".
				"<CardBillingAddr2>$aut_Address2</CardBillingAddr2>".
				"<CardBillingCity>$aut_City</CardBillingCity>".
				"<CardBillingState>$aut_State</CardBillingState>".
				"<CardBillingZip>$aut_Zip</CardBillingZip>".
			"</RequestVars>".
		"</Request>".
	"</VancoWS>";

$addPaymentMethodXmlResp = $VancoObj->PostXML ($addCCXML);

//print_r($addPaymentMethodXmlResp);

$resArr = array ();

$resArr[] = array('AutID'=>$iAutID);
$resArr[] = array('PaymentType'=>"$accountType");

if (gettype($addPaymentMethodXmlResp->Response->Errors->Error) == "object") {
	foreach ($addPaymentMethodXmlResp->Response->Errors->Error as $onerr) {
		$resArr[] = array("Error"=>$onerr->ErrorCode.": ".$onerr->ErrorDescription);
//		print "Got an error code ".$onerr->ErrorCode." description " . $onerr->ErrorDescription . "<br>";
	}
	$resArr[] = array('Success'=>False);
} else {
	$gotPaymentMethod = $addPaymentMethodXmlResp->Response->PaymentMethodRef;
	
	$resArr[] = array('PaymentMethod'=>$gotPaymentMethod);
	if ($aut_EnableBankDraft) {
		$sSQL = "UPDATE autopayment_aut SET aut_AccountVanco=$gotPaymentMethod WHERE aut_ID=" . $iAutID;
	} elseif ($aut_EnableCreditCard) {
		$sSQL = "UPDATE autopayment_aut SET aut_CreditCardVanco=$gotPaymentMethod WHERE aut_ID=" . $iAutID;
	}
	mysql_query($sSQL, $cnInfoCentral);
	$resArr[] = array('Success'=>True);
}


header('Content-type: application/json');
echo json_encode($resArr);
?>
