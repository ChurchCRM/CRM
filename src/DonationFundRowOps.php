<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\DonationFundService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page
AuthenticationManager::redirectHomeIfNotAdmin();

$fundId = InputUtils::legacyFilterInput($_POST['FundID'] ?? $_GET['FundID'] ?? '', 'int');
$action = InputUtils::legacyFilterInput($_POST['Action'] ?? $_GET['Action'] ?? '');

// All state-changing operations require POST + a valid CSRF token (CWE-352 / CWE-650,
// see GHSA-68xh-3jh8-3wvq). SameSite=Lax cookies are sent on cross-site top-level GET
// navigations, so a GET-based delete endpoint is exploitable without any user interaction
// beyond visiting a malicious page.
if (in_array($action, ['delete', 'up', 'down'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die(gettext('Method Not Allowed'));
    }
    if (!CSRFUtils::verifyRequest($_POST, 'donationFundAction')) {
        http_response_code(403);
        die(gettext('Invalid CSRF token'));
    }
}

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
        RedirectUtils::redirect('DonationFundEditor.php');
    } catch (\Exception $e) {
        RedirectUtils::redirect('DonationFundEditor.php?ReorderError=' . urlencode($e->getMessage()));
    }
}
