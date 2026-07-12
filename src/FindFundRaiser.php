<?php
// Legacy redirect shim — migrated to /fundraiser/
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\RedirectUtils;

RedirectUtils::redirect('fundraiser/');
