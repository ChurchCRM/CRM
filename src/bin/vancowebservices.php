<?php
// vancoWebServices.php   
//                                                           
// Original Author: Vanco Services, LLC                               
// Original Date: 2014-07-14                                                                   
//                                                           
// Copyright   Vanco Services, LLC                           
//             12600 Whitewater Drive,                       
//             Suite 200,                                    
//             Minnetonka, MN 55343                          
//             800-774-9355 (Phone)                          
//             952-983-8665 (Fax)                            
//                                                           
// 

class VancoTools
{
  private $userid, $password, $clientid, $enc_key, $test;

  function __construct($setUserid, $setPassword, $setClientid, $setEnckey, $setTest)
  {
    $this->userid = $setUserid;
    $this->password = $setPassword;
    $this->clientid = $setClientid;
    $this->enc_key = $setEnckey;
    $this->test = $setTest;
  }

  function vancoLoginRequest()
  {
    /*
        Function:    vancoLoginRequest()
        Description: Post a login request to obtain a session ID
        Parameters:  None
        Returns:     SessionID to be used in consecutive posts, or errors if login fails
        */
    $requestid = $this->generateRequestID();
    $postdata = "nvpvar=requesttype=login&requestid=$requestid&userid=" . $this->userid . "&password=" . $this->password;
    $response = $this->post($postdata);
    $data = explode("&", $response);
    $sessionid = "";
    foreach ($data as $item) {
      if (substr($item, 0, 9) === "sessionid") {
        $sessionid = substr("$item", 10);
      }
    }
    return $sessionid;
  }


  function vancoEFTTransparentRedirectNVPGenerator($urltoredirect, $customerid, $customerref, $isdebitcardonly)
  {
    /*
        Function:    vancoEFTTransparentRedirectNVPGenerator($urltoredirect,$customerid,$customerref,$isdebitcardonly)
        Description: Create an encrypted string for nvpvar used in transparent redirect requests
        Parameters:  $urltoredirect: String to be used as the redirect URL for transparent redirect
                 $customerid: Optional customer ID. See transparent redirect specifications for details on this field
                 $customerref: Optional customer reference number. See Transparent redirect specifications for details on this field
                 $isdebitcardonly: Indicator to not allow credit cards to be used, defaults to No.
        Returns:     Encrypted string to be used as nvpvar variable in a transparent redirect request
        */
    $requestid = $this->generateRequestID();
    $nvpstring = "requesttype=efttransparentredirect&requestid=$requestid&clientid=" . $this->clientid . "&urltoredirect=$urltoredirect&isdebitcardonly=$isdebitcardonly";
    if ($customerid != "") {
      $nvpstring .= '&customerid=' . $customerid;
    }
    if ($customerref != "") {
      $nvpstring .= '&customerref=' . $customerref;
    }
    $encryptednvp = $this->encryptNVPString($nvpstring);
    return $encryptednvp;
  }


  function vancoEFTAddCompleteTransactionRequest($sessionid, $paymentmethodref, $startdate, $frequencycode, $customerid = "", $customerref = "", $name = "", $address1 = "", $address2 = "", $city = "",
                                                 $state = "", $czip = "", $phone = "", $isdebitcardonly = "", $enddate = "", $transactiontypecode = "", $funddict = "", $amount = "")
  {
    /*
        Function:    vancoEFTAddCompleteTransactionRequest($sessionid,$paymentmethodref,$startdate,$frequencycode,$customerid,$customerref,$name,$address1,$address2,$city,$state,\
                 $czip,$phone,$isdebitcardonly,$enddate,$transactiontypecode,$funddict,$amount)
        Description: Post a eftaddcompletetransaction request to add a transaction
        Parameters:  $sessionid: Unique session ID obtained from vancoLoginRequest function
                 $paymentmethodref: Reference number for the payment method to be used for this transaction
                 $startdate: First date this transaction will process. 0000-00-00 will result in processing this transaction ASAP
                 $frequencycode: Code for the frequency in which this transaction should process. See transparent redirect specifications for details on this field
                 $customerid: Optional customer ID. See transparent redirect specifications for details on this field
                 $customerref: Optional customer reference number. See Transparent redirect specifications for details on this field
                 $name: Optional name of the customer. Not needed if there are no changes to the existing name on the account
                 $address1: Optional address line 1 of the customer. Not needed if there are no changes to the existing address on the account
                 $address2: Optional address line 2 of the customer. Not needed if there are no changes to the existing address on the account
                 $city: Optional city of the customer. Not needed if there are no changes to the existing address on the account
                 $state: Optional state of the customer. Not needed if there are no changes to the existing address on the account
                 $czip: Optional zip code of the customer. Not needed if there are no changes to the existing address on the account
                 $phone: Optional phone number of the customer. Not needed if there are no changes to the existing phone number, or if you are not collecting this data
                 $isdebitcardonly: Indicator to not allow credit cards to be used, defaults to No.
                 $enddate: Optional last date this transaction will process. If left blank and transaction is not one-time, transaction will process until canceled
                 $transactiontypecode: Type of authorization obtained for this transaction. See Transparent redirect specifications for details on this field
                 $funddict: Optional array of the fund IDs and fund amounts. i.e. array('fundid_0' => '0001', 'fundamount_0' => '50.00','fundid_1' => '0002', 'fundamount_1' => '25.50')
                 $amount: If funds are not being used, this will be the amount of the transaction
        Returns:     Response variables for a successful transaction, or errors if login fails. See transparent redirect specifications for details on the return fields
        */
    $requestid = $this->generateRequestID();

    $nvpstring = "requesttype=eftaddcompletetransaction&requestid=$requestid&clientid=" . $this->clientid;
    // Adding any inputed values to the nvpvar
    if ($paymentmethodref != "") {
      $nvpstring .= '&paymentmethodref=' . $paymentmethodref;
    }
    if ($startdate != "") {
      $nvpstring .= '&startdate=' . $startdate;
    }
    if ($frequencycode != "") {
      $nvpstring .= '&frequencycode=' . $frequencycode;
    }
    if ($customerid != "") {
      $nvpstring .= '&customerid=' . $customerid;
    }
    if ($customerref != "") {
      $nvpstring .= '&customerref=' . $customerref;
    }
    if ($name != "") {
      $nvpstring .= '&customername=' . $name;
    }
    if ($address1 != "") {
      $nvpstring .= '&customeraddress1=' . $address1;
    }
    if ($address2 != "") {
      $nvpstring .= '&customeraddress2=' . $address2;
    }
    if ($city != "") {
      $nvpstring .= '&customercity=' . $city;
    }
    if ($state != "") {
      $nvpstring .= '&customerstate=' . $state;
    }
    if ($czip != "") {
      $nvpstring .= '&customerzip=' . $czip;
    }
    if ($phone != "") {
      $nvpstring .= '&customerphone=' . $phone;
    }
    if ($isdebitcardonly != "") {
      $nvpstring .= '&isdebitcardonly=' . $isdebitcardonly;
    }
    if ($enddate != "") {
      $nvpstring .= '&enddate=' . $enddate;
    }
    if ($transactiontypecode != "") {
      $nvpstring .= '&transactiontypecode=' . $transactiontypecode;
    }
    //define amount fields
    if ($funddict != "") {
      foreach ($funddict as $key => $value) {
        $nvpstring .= '&' . $key . '=' . $value;
      }
    } else {
      $nvpstring .= '&amount=' . $amount;
    }
    $responsedata = $this->encryptNVPString($nvpstring);
    $postdata = "sessionid=$sessionid&nvpvar=$responsedata";
    return $this->post($postdata);
  }


  function post($postdata)
  {
    /*
        Function:    post($postdata)
        Description: Post a Web Services request to vancoservices or vancodev
        Parameters:  $postdata: String to use in CGI variables in HTTPS post
        Returns:     Response to Web Services post
        */
    if ($this->test == True) {
      $ip = "https://www.vancodev.com";
      $filepath = "/cgi-bin/wsnvptest.vps";
    } else {
      $ip = "https://www.vancoservices.com";
      $filepath = "/cgi-bin/wsnvp.vps";
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$ip$filepath");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $res = curl_exec($ch);

    $results = explode('nvpvar=', $res);

    foreach ($results as $item) {
      $result = $item;
    }
    $result = str_replace('"></html>', "", $result);

    return $this->decryptResponseString($result);
  }


  function encryptNVPString($nvpstring)
  {
    /*
        Function:    encryptNVPString($nvpstring)
        Description: Encrypt a string to be used in the nvpvar variable
        Parameters:  $nvpstring: Unencrypted string that needs to be encrypted
        Returns:     Encrypted string to use in nvpvar variable
        */
    $deflated = gzdeflate($nvpstring);
    $padded = $this->pad_msg($deflated);
    $encrypted = $this->encrypt($padded, $this->enc_key);
    $encoded = $this->urlsafe_b64encode($encrypted);
    return $encoded;
  }

  function urlsafe_b64encode($string)
  {
    return rtrim(strtr(base64_encode($string), '+/', '-_'));
  }

  function encrypt($data, $key)
  {
    return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB);
  }

  function pad_msg($data)
  {
    return $data . str_repeat(' ', strlen($data) % 16);
  }


  function decryptResponseString($response)
  {
    /*
        Function:    decryptResponseString($response)
        Description: Decrypt an encrypted string from a Web Services response
        Parameters:  $response: String returned from the Web Services response
        Returns:     Decrypted string of the response variables
        */
    $encrypted = $this->urlsafe_b64decode($response);
    $decrypted = $this->decrypt($encrypted, $this->enc_key);
    $inflated = gzinflate($decrypted);
    return $inflated;
  }

  function urlsafe_b64decode($data)
  {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
  }

  function decrypt($data, $key)
  {
    return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB);
  }


  //Function to generate a unique requestid for the request.
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
    return $currenttime . $randomnumber;
  }

  function errorString($errNo)
  {
    switch ($errNo) {
      case 10:
        return "Invalid UserID/password combination";
      case 11:
        return "Session expired";
      case 25:
        return "All default address fields are required";
      case 32:
        return "Name is required";
      case 33:
        return "Unknown bank/bankpk";
      case 34:
        return "Valid PaymentType is required";
      case 35:
        return "Valid Routing Number Is Required";
      case 63:
        return "Invalid StartDate";
      case 65:
        return "Specified fund reference is not valid.";
      case 66:
        return "Invalid End Date";
      case 67:
        return "Transaction must have at least one transaction fund.";
      case 68:
        return "User is Inactive";
      case 69:
        return "Expiration Date Invalid";
      case 70:
        return "Account Type must be “C”, “S' for ACH and must be blank for Credit Card";
      case 71:
        return "Class Code must be PPD, CCD, TEL, WEB, RCK or blank.";
      case 72:
        return "Missing Client Data: Client ID";
      case 73:
        return "Missing Customer Data: Customer ID or Name or Last Name & First Name";
      case 74:
        return "PaymentMethod is required.";
      case 76:
        return "Transaction Type is required";
      case 77:
        return "Missing Credit Card Data: Card # or Expiration Date";
      case 78:
        return "Missing ACH Data: Routing # or Account #";
      case 79:
        return "Missing Transaction Data: Amount or Start Date";
      case 80:
        return "Account Number has invalid characters in it";
      case 81:
        return "Account Number has too many characters in it";
      case 82:
        return "Customer name required";
      case 83:
        return "Customer ID has not been set";
      case 86:
        return "NextSettlement does not fall in today's processing dates";
      case 87:
        return "Invalid FrequencyPK";
      case 88:
        return "Processed yesterday";
      case 89:
        return "Duplicate Transaction (matches another with PaymentMethod and NextSettlement)";
      case 91:
        return "Dollar amount for transaction is over the allowed limit";
      case 92:
        return "Invalid client reference occurred. - Transaction WILL NOT process";
      case 94:
        return "Customer ID already exists for this client";
      case 95:
        return "Payment Method is missing Account Number";
      case 101:
        return "Dollar Amount for transaction cannot be negative";
      case 102:
        return "Updated transaction's dollar amount violates amount limit";
      case 105:
        return "PaymentMethod Date not valid yet.";
      case 125:
        return "Email Address is required.";
      case 127:
        return "User Is Not Proofed";
      case 134:
        return "User does not have access to specified client.";
      case 157:
        return "Client ID is required";
      case 158:
        return "Specified Client is invalid";
      case 159:
        return "Customer ID required";
      case 160:
        return "Customer ID is already in use";
      case 161:
        return "Customer name required";
      case 162:
        return "Invalid Date Format";
      case 163:
        return "Transaction Type is required";
      case 164:
        return "Transaction Type is invalid";
      case 165:
        return "Fund required";
      case 166:
        return "Customer Required";
      case 167:
        return "Payment Method Not Found";
      case 168:
        return "Amount Required";
      case 169:
        return "Amount Exceeds Limit. Set up manually.";
      case 170:
        return "Start Date Required";
      case 171:
        return "Invalid Start Date";
      case 172:
        return "End Date earlier than Start Date";
      case 173:
        return "Cannot Prenote a Credit Card";
      case 174:
        return "Cannot Prenote processed account";
      case 175:
        return "Transaction pending for Prenote account";
      case 176:
        return "Invalid Account Type";
      case 177:
        return "Account Number Required";
      case 178:
        return "Invalid Routing Number";
      case 179:
        return "Client doesn't accept Credit Card Transactions";
      case 180:
        return "Client is in test mode for Credit Cards";
      case 181:
        return "Client is cancelled for Credit Cards";
      case 182:
        return "Name on Credit Card is Required";
      case 183:
        return "Invalid Expiration Date";
      case 184:
        return "Complete Billing Address is Required";
      case 195:
        return "Transaction Cannot Be Deleted";
      case 196:
        return "Recurring Telephone Entry Transaction NOT Allowed";
      case 198:
        return "Invalid State";
      case 199:
        return "Start Date Is Later Than Expiration date";
      case 201:
        return "Frequency Required";
      case 202:
        return "Account Cannot Be Deleted, Active Transaction Exists";
      case 203:
        return "Client Does Not Accept ACH Transactions";
      case 204:
        return "Duplicate Transaction";
      case 210:
        return "Recurring Credits NOT Allowed";
      case 211:
        return "ONHold/Cancelled Customer";
      case 217:
        return "End Date Cannot Be Earlier Than The Last Settlement Date";
      case 218:
        return "Fund ID Cannot Be W, P, T, or C";
      case 223:
        return "Customer ID not on file";
      case 224:
        return "Credit Card Credits NOT Allowed - Must Be Refunded";
      case 231:
        return "Customer Not Found For Client";
      case 232:
        return "Invalid Account Number";
      case 233:
        return "Invalid Country Code";
      case 234:
        return "Transactions Are Not Allow From This Country";
      case 242:
        return "Valid State Required";
      case 251:
        return "Transactionref Required";
      case 284:
        return "User Has Been Deleted";
      case 286:
        return "Client not set up for International Credit Card Processing";
      case 296:
        return "Client Is Cancelled";
      case 328:
        return "Credit Pending - Cancel Date cannot be earlier than Today";
      case 329:
        return "Credit Pending - Account cannot be placed on hold until Tomorrow";
      case 341:
        return "Cancel Date Cannot be Greater Than Today";
      case 344:
        return "Phone Number Must be 10 Digits Long";
      case 365:
        return "Invalid Email Address";
      case 378:
        return "Invalid Loginkey";
      case 379:
        return "Requesttype Unavailable";
      case 380:
        return "Invalid Sessionid";
      case 381:
        return "Invalid Clientid for Session";
      case 383:
        return "Internal Handler Error. Contact Vanco Services.";
      case 384:
        return "Invalid Requestid";
      case 385:
        return "Duplicate Requestid";
      case 390:
        return "Requesttype Not Authorized For User";
      case 391:
        return "Requesttype Not Authorized For Client";
      case 392:
        return "Invalid Value Format";
      case 393:
        return "Blocked IP";
      case 395:
        return "Transactions cannot be processed on Weekends";
      case 404:
        return "Invalid Date";
      case 410:
        return "Credits Cannot Be WEB or TEL";
      case 420:
        return "Transaction Not Found";
      case 431:
        return "Client Does Not Accept International Credit Cards";
      case 432:
        return "Can not process credit card";
      case 434:
        return "Credit Card Processor Error";
      case 445:
        return "Cancel Date Cannot Be Prior to the Last Settlement Date";
      case 446:
        return "End Date Cannot Be In The Past";
      case 447:
        return "Masked Account";
      case 469:
        return "Card Number Not Allowed";
      case 474:
        return "MasterCard Not Accepted";
      case 475:
        return "Visa Not Accepted";
      case 476:
        return "American Express Not Accepted";
      case 477:
        return "Discover Not Accepted";
      case 478:
        return "Invalid Account Number";
      case 489:
        return "Customer ID Exceeds 15 Characters";
      case 490:
        return "Too Many Results, Please Narrow Search";
      case 495:
        return "Field Contains Invalid Characters";
      case 496:
        return "Field contains Too Many Characters";
      case 497:
        return "Invalid Zip Code";
      case 498:
        return "Invalid City";
      case 499:
        return "Invalid Canadian Postal Code";
      case 500:
        return "Invalid Canadian Province";
      case 506:
        return "User Not Found";
      case 511:
        return "Amount Exceeds Limit";
      case 512:
        return "Client Not Set Up For Credit Card Processing";
      case 515:
        return "Transaction Already Refunded";
      case 516:
        return "Can Not Refund a Refund";
      case 517:
        return "Invalid Customer";
      case 518:
        return "Invalid Payment Method";
      case 519:
        return "Client Only Accepts Debit Cards";
      case 520:
        return "Transaction Max for Account Number Reached";
      case 521:
        return "Thirty Day Max for Client Reached";
      case 523:
        return "Invalid Login Request";
      case 527:
        return "Change in account/routing# or type";
      case 535:
        return "SSN Required";
      case 549:
        return "CVV2 Number is Required";
      case 550:
        return "Invalid Client ID";
      case 556:
        return "Invalid Banking Information";
      case 569:
        return "Please Contact This Organization for Assistance with Processing This Transaction";
      case 570:
        return "City Required";
      case 571:
        return "Zip Code Required";
      case 572:
        return "Canadian Provence Required";
      case 573:
        return "Canadian Postal Code Required";
      case 574:
        return "Country Code Required";
      case 578:
        return "Unable to Read Card Information. Please Click “Click to Swipe” Button and Try Again.";
      case 610:
        return "Invalid Banking Information. Previous Notification of Change Received for this Account";
      case 629:
        return "Invalid CVV2";
      case 641:
        return "Fund ID Not Found";
      case 642:
        return "Request Amount Exceeds Total Transaction Amount";
      case 643:
        return "Phone Extension Required";
      case 645:
        return "Invalid Zip Code";
      case 652:
        return "Invalid SSN";
      case 653:
        return "SSN Required";
      case 657:
        return "Billing State Required";
      case 659:
        return "Phone Number Required";
      case 663:
        return "Version Not Supported";
      case 665:
        return "Invalid Billing Address";
      case 666:
        return "Customer Not On Hold";
      case 667:
        return "Account number for fund is invalid";
      case 678:
        return "Password Expired";
      case 687:
        return "Fund Name is currently in use. Please choose another name. If you would like to use this Fund Name, go to the other fund and change the Fund Name to something different.";
      case 688:
        return "Fund ID is currently in use. Please choose another number. If you would like to use this Fund ID, go to the other fund and change the Fund ID to something different.";
      case 705:
        return "Please Limit Your Date Range To 30 Days";
      case 706:
        return "Last Digits of Account Number Required";
      case 721:
        return "MS Transaction Amount Cannot Be Greater Than $50,000.";
      case 725:
        return "User ID is for Web Services Only";
      case 730:
        return "Start Date Required";
      case 734:
        return "Date Range Cannot Be Greater Than One Year";
      case 764:
        return "Start Date Cannot Occur In The Past";
      case 800:
        return "The CustomerID Does Not Match The Given CustomerRef";
      case 801:
        return "Default Payment Method Not Found";
      case 838:
        return "Transaction Cannot Be Processed. Please contact your organization.";
      case 842:
        return "Invalid Pin";
      case 844:
        return "Phone Number Must be 10 Digits Long";
      case 850:
        return "Invalid Authentication Signature";
      case 857:
        return "Fund Name Can Not Be Greater Than 30 Characters";
      case 858:
        return "Fund ID Can Not Be Greater Than 20 Characters";
      case 859:
        return "Customer Is Unproofed";
      case 862:
        return "Invalid Start Date";
      case 956:
        return "Amount Must Be Greater Than $0.00";
      case 960:
        return "Date of Birth Required";
      case 963:
        return "Missing Field";
      case 973:
        return "No match found for these credentials.";
      case 974:
        return "Recurring Return Fee Not Allowed";
      case 992:
        return "No Transaction Returned Within the Past 45 Days";
      case 993:
        return "Return Fee Must Be Collected Within 45 Days";
      case 994:
        return "Return Fee Is Greater Than the Return Fee Allowed";
      case 1005:
        return "Phone Extension Must Be All Digits";
      case 1008:
        return "We are sorry. This organization does not accept online credit card transactions. Please try again using a debit card.";
      case 1047:
        return "Invalid nvpvar variables";
      case 1054:
        return "Invalid. Debit Card Only";
      case 1067:
        return "Invalid Original Request ID";
      case 1070:
        return "Transaction Cannot Be Voided";
      case 1073:
        return "Transaction Processed More Than 25 Minutes Ago";
      case 1127:
        return "Declined - Tran Not Permitted";
      case 1128:
        return "Unable To Process, Please Try Again";
    }
  }
}

?>
