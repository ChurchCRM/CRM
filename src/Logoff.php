<?php

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\UserQuery;
use ChurchCRM\Utils\RedirectUtils;

if (!empty($_SESSION['iUserID'])) {
    if (!isset($_SESSION['sshowPledges']) || ($_SESSION['sshowPledges'] == '')) {
        $_SESSION['sshowPledges'] = 0;
    }
    if (!isset($_SESSION['sshowPayments']) || ($_SESSION['sshowPayments'] == '')) {
        $_SESSION['sshowPayments'] = 0;
    }
    if (!isset($_SESSION['bSearchFamily']) || ($_SESSION['bSearchFamily'] == '')) {
        $_SESSION['bSearchFamily'] = 0;
    }

    $currentUser = UserQuery::create()->findPk($_SESSION['iUserID']);
    if (!empty($currentUser)) {
        $currentUser->setShowPledges($_SESSION['sshowPledges']);
        $currentUser->setShowPayments($_SESSION['sshowPayments']);
        $currentUser->setShowSince($_SESSION['sshowSince']);
        $currentUser->setDefaultFY($_SESSION['idefaultFY']);
        $currentUser->setCurrentDeposit($_SESSION['iCurrentDeposit']);
    
        $currentUser->setSearchfamily($_SESSION['bSearchFamily']);
        $currentUser->save();
    }
}

$_COOKIE = [];
$_SESSION = [];
session_destroy();

RedirectUtils::Redirect('Login.php');
exit;
