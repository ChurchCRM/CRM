<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Delete records permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');

// Only allow POST requests with valid CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    RedirectUtils::redirect('FindFundRaiser.php');
}

if (!CSRFUtils::verifyRequest($_POST, 'deleteFundRaiser')) {
    RedirectUtils::redirect('FindFundRaiser.php');
}

$iFundRaiserID = (int) InputUtils::legacyFilterInput($_POST['FundRaiserID'], 'int');
$linkBack = InputUtils::legacyFilterInput($_POST['linkBack'] ?? 'FindFundRaiser.php');

if ($iFundRaiserID > 0) {
    $fundraiser = FundRaiserQuery::create()->findPk($iFundRaiserID);
    if ($fundraiser) {
        $fundraiser->delete();
    }
}

RedirectUtils::redirect($linkBack);
