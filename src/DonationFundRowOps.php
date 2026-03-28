<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\DonationFundService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page
AuthenticationManager::redirectHomeIfNotAdmin();

$fundId = InputUtils::legacyFilterInput($_GET['FundID'], 'int');
$action = InputUtils::legacyFilterInput($_GET['Action']);

$service = new DonationFundService();

if ($action === 'delete') {
    try {
        $service->deleteFund((int) $fundId);
        RedirectUtils::redirect('DonationFundEditor.php?Action=delete');
    } catch (\Exception $e) {
        RedirectUtils::redirect('DonationFundEditor.php?DeleteError=' . urlencode($e->getMessage()));
    }
} elseif ($action === 'up' || $action === 'down') {
    try {
        $service->reorderFund((int) $fundId, $action);
    } catch (\Exception $e) {
        // Silently ignore reorder errors (fund not found, already at boundary)
    }
    RedirectUtils::redirect('DonationFundEditor.php');
}
