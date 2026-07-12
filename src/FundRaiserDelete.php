<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have finance permissions
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

$iFundRaiserID = (int) InputUtils::legacyFilterInput($_GET['FundRaiserID'], 'int');
$linkBack = RedirectUtils::getLinkBackFromRequest('FindFundRaiser.php');

if ($iFundRaiserID > 0) {
    $fundraiser = FundRaiserQuery::create()->findPk($iFundRaiserID);
    if ($fundraiser) {
        $fundraiser->delete();
    }
}

RedirectUtils::redirect($linkBack);
