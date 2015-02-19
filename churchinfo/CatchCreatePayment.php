<?php 
require "Include/Config.php";
require "Include/Functions.php";
require "Include/VancoConfig.php";

// set into the Vanco interface by AutoPaymentEditor.php
$iVancoAutID = FilterInputArr($_POST,"customerid",'int'); 

// this is what we are really after- this handle can be used to initiate authorized transactions
$iVancoPaymentMethodRef = FilterInputArr($_POST, "paymentmethodref", 'int');

$sVancoPaymentCreditCard = "";
$iEnableCreditCard = 0;
if (FilterInputArr ($_POST, 'accounttype') == "CC") {
	$sVancoPaymentCreditCard = "$iVancoPaymentMethodRef";
	$iEnableCreditCard = 1;
}

$sVancoPaymentBankDraft = "";
$iEnableBankDraft = 0;
if (FilterInputArr ($_POST, 'accounttype') == "C") {
	$sVancoPaymentBankDraft = "$iVancoPaymentMethodRef";
	$iEnableBankDraft = 1;
}

// Other information that was just entered into the payment page that we will store for reference
$sVancoName = FilterInputArr ($_POST, "name");
$aVancoNames = explode (" ", $sVancoName, 2);
$sVancoFirstName = $aVancoNames[0];
$sVancoLastName = $aVancoNames[1];
$sVancoAddr1 = FilterInputArr ($_POST, "billingaddr1");
$sVancoBillingCity = FilterInputArr ($_POST, "billingcity");
$sVancoBillingState = FilterInputArr ($_POST, "billingstate");
$sVancoBillingZip = FilterInputArr ($_POST, "billingzip");
$sVancoEmail = FilterInputArr ($_POST, "email");
$sVancoExpMonth = FilterInputArr ($_POST, "expmonth");
$sVancoExpYear = FilterInputArr ($_POST, "expyear");

// information reflected back (use for verification)
$sVancoClientID = FilterInputArr($_POST,"clientid");

$sSQL = "UPDATE autopayment_aut SET ";
$sSQL .= "aut_FirstName=\"$sVancoFirstName\"";
$sSQL .= ", aut_LastName=\"$sVancoLastName\"";
$sSQL .= ", aut_Address1=\"$sVancoAddr1\"";
$sSQL .= ", aut_City=\"$sVancoBillingCity\"";
$sSQL .= ", aut_State=\"$sVancoBillingState\"";
$sSQL .= ", aut_Zip=\"$sVancoBillingZip\"";
$sSQL .= ", aut_Email=\"$sVancoEmail\"";
$sSQL .= ", aut_EnableCreditCard=\"$iEnableCreditCard\"";
$sSQL .= ", aut_CreditCardVanco=\"$sVancoPaymentCreditCard\"";
$sSQL .= ", aut_EnableBankDraft=\"$iEnableBankDraft\"";
$sSQL .= ", aut_AccountVanco=\"$sVancoPaymentBankDraft\"";
$sSQL .= ", aut_ExpMonth=\"$sVancoExpMonth\"";
$sSQL .= ", aut_ExpYear=\"$sVancoExpYear\"";
$sSQL .= ", aut_DateLastEdited=\"" . date ("YmdHis"). "\"";
$sSQL .= ", aut_EditedBy=" . $_SESSION['iUserID'];
$sSQL .= " WHERE aut_ID=$iVancoAutID";

$resultArr = array ();

$bSuccess = false;
if ($result = mysql_query($sSQL, $cnInfoCentral))
    $bSuccess = true;

if (! $bSuccess) {
	$errStr = gettext("Cannot execute query.") . "<p>$sSQL<p>" . mysql_error();
	array_push ($resultArr, array('Query'=>$sSQL));
	array_push ($resultArr, array ('Error'=>$errStr));
	$var_str = var_export($_POST, true);
	array_push ($resultArr, array ('POST'=>$var_str));
}

array_push ($resultArr, array ('Success'=>$bSuccess));

header('Content-type: application/json');
echo json_encode($resultArr);
?>
