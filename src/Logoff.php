<?php

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\UserQuery;
use ChurchCRM\Utils\RedirectUtils;

if (!empty($_SESSION['user'])) {
    $currentUser = UserQuery::create()->findPk($_SESSION['user']->getId());
    if (!empty($currentUser)) {
        $currentUser->setDefaultFY($_SESSION['idefaultFY']);
        $currentUser->setCurrentDeposit($_SESSION['iCurrentDeposit']);

        $currentUser->save();
    }
}

$_COOKIE = [];
$_SESSION = [];
session_destroy();

RedirectUtils::Redirect('Login.php');
exit;
