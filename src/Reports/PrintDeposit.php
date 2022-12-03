<?php
/*******************************************************************************
*
*  filename    : Reports/PrintDeposit.php
*  last change : 2013-02-21
*  description : Creates a PDF of the current deposit slip
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

global $iChecksPerDepositForm;

require "../Include/Config.php";
require "../Include/Functions.php";

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

//Security
if (!AuthenticationManager::GetCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::Redirect("Menu.php");
    exit;
}

$iBankSlip = 0;
if (array_key_exists("BankSlip", $_GET)) {
    $iBankSlip = InputUtils::LegacyFilterInput($_GET["BankSlip"], 'int');
}
if (!$iBankSlip && array_key_exists("report_type", $_POST)) {
    $iBankSlip = InputUtils::LegacyFilterInput($_POST["report_type"], 'int');
}

$output = "pdf";
if (array_key_exists("output", $_POST)) {
    $output = InputUtils::LegacyFilterInput($_POST["output"]);
}


$iDepositSlipID = 0;
if (array_key_exists("deposit", $_POST)) {
    $iDepositSlipID = InputUtils::LegacyFilterInput($_POST["deposit"], "int");
}

if (!$iDepositSlipID && array_key_exists('iCurrentDeposit', $_SESSION)) {
    $iDepositSlipID = $_SESSION['iCurrentDeposit'];
}

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
// If no DepositSlipId, redirect to the menu
if ((!AuthenticationManager::GetCurrentUser()->isAdmin() && $bCSVAdminOnly && $output != "pdf") || !$iDepositSlipID) {
    RedirectUtils::Redirect("Menu.php");
    exit;
}

if ($output == "pdf") {
    header('Location: '.SystemURLs::getRootPath()."/api/deposits/".$iDepositSlipID."/pdf");
} elseif ($output == "csv") {
    header('Location: '.SystemURLs::getRootPath()."/api/deposits/".$iDepositSlipID."/csv");
}
