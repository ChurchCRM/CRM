<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page
AuthenticationManager::redirectHomeIfNotAdmin();

$fundId = InputUtils::legacyFilterInput($_GET['FundID'], 'int');
$action = InputUtils::legacyFilterInput($_GET['Action']);

if ($action === 'delete') {
    // Delete the fund
    $fund = DonationFundQuery::create()->findOneById($fundId);
    if ($fund !== null) {
        $deletedOrder = $fund->getOrder();
        $fund->delete();
        
        // Renumber the remaining funds
        $funds = DonationFundQuery::create()
            ->orderByOrder()
            ->find();
        
        $currentOrder = 1;
        foreach ($funds as $remainingFund) {
            $remainingFund->setOrder($currentOrder++);
            $remainingFund->save();
        }
    }
    
    RedirectUtils::redirect('DonationFundEditor.php?Action=delete');
} elseif ($action === 'up' || $action === 'down') {
    // Get the current fund
    $fund = DonationFundQuery::create()->findOneById($fundId);
    
    if ($fund !== null) {
        $currentOrder = $fund->getOrder();
        
        if ($action === 'up' && $currentOrder > 1) {
            // Find the fund with the previous order
            $previousFund = DonationFundQuery::create()
                ->filterByOrder($currentOrder - 1)
                ->findOne();
            
            if ($previousFund !== null) {
                // Swap orders
                $fund->setOrder($currentOrder - 1);
                $previousFund->setOrder($currentOrder);
                $fund->save();
                $previousFund->save();
            }
        } elseif ($action === 'down') {
            // Find the fund with the next order
            $nextFund = DonationFundQuery::create()
                ->filterByOrder($currentOrder + 1)
                ->findOne();
            
            if ($nextFund !== null) {
                // Swap orders
                $fund->setOrder($currentOrder + 1);
                $nextFund->setOrder($currentOrder);
                $fund->save();
                $nextFund->save();
            }
        }
    }
    
    RedirectUtils::redirect('DonationFundEditor.php');
}
