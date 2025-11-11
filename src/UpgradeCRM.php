<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;

// Redirect to new Slim v2 route
if (!AuthenticationManager::validateUserSessionIsActive(false) || !AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::redirect('index.php');
}

RedirectUtils::redirect('v2/admin/upgrade');
