<?php

require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/VancoConfig.php';

use ChurchCRM\Utils\InputUtils;

// set into the Vanco interface by AutoPaymentEditor.php
$iVancoAutID = InputUtils::LegacyFilterInputArr($_POST, 'customerid', 'int');

// this is what we are really after- this handle can be used to initiate authorized transactions
$iVancoPaymentMethodRef = InputUtils::LegacyFilterInputArr($_POST, 'paymentmethodref', 'int');

$sVancoPaymentCreditCard = '';
$iEnableCreditCard = 0;
if (InputUtils::LegacyFilterInputArr($_POST, 'accounttype') == 'CC') {
    $sVancoPaymentCreditCard = "$iVancoPaymentMethodRef";
    $iEnableCreditCard = 1;
}

$sVancoPaymentBankDraft = '';
$iEnableBankDraft = 0;
if (InputUtils::LegacyFilterInputArr($_POST, 'accounttype') == 'C') {
    $sVancoPaymentBankDraft = "$iVancoPaymentMethodRef";
    $iEnableBankDraft = 1;
}

// Other information that was just entered into the payment page that we will store for reference
$sVancoName = InputUtils::LegacyFilterInputArr($_POST, 'name');
$aVancoNames = explode(' ', $sVancoName, 2);
$sVancoFirstName = $aVancoNames[0];
$sVancoLastName = $aVancoNames[1];
$sVancoAddr1 = InputUtils::LegacyFilterInputArr($_POST, 'billingaddr1');
$sVancoBillingCity = InputUtils::LegacyFilterInputArr($_POST, 'billingcity');
$sVancoBillingState = InputUtils::LegacyFilterInputArr($_POST, 'billingstate');
$sVancoBillingZip = InputUtils::LegacyFilterInputArr($_POST, 'billingzip');
$sVancoEmail = InputUtils::LegacyFilterInputArr($_POST, 'email');
$sVancoExpMonth = InputUtils::LegacyFilterInputArr($_POST, 'expmonth');
$sVancoExpYear = InputUtils::LegacyFilterInputArr($_POST, 'expyear');

// information reflected back (use for verification)
$sVancoClientID = InputUtils::LegacyFilterInputArr($_POST, 'clientid');

$sSQL = 'UPDATE autopayment_aut SET ';
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
$sSQL .= ', aut_DateLastEdited="'.date('YmdHis').'"';
$sSQL .= ', aut_EditedBy='.$_SESSION['user']->getId();
$sSQL .= " WHERE aut_ID=$iVancoAutID";

$resultArr = [];

$bSuccess = false;
if ($result = mysqli_query($cnInfoCentral, $sSQL)) {
    $bSuccess = true;
}

if (!$bSuccess) {
    $errStr = gettext('Cannot execute query.')."<p>$sSQL<p>".mysqli_error($cnInfoCentral);
    array_push($resultArr, ['Query'=>$sSQL]);
    array_push($resultArr, ['Error'=>$errStr]);
    $var_str = var_export($_POST, true);
    array_push($resultArr, ['POST'=>$var_str]);
}

array_push($resultArr, ['Success'=>$bSuccess]);

header('Content-type: application/json');
echo json_encode($resultArr);
