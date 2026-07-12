<?php
// Legacy redirect shim — FundRaiserDelete was a GET mutation; now handled by POST /fundraiser/{id}/delete.
// Redirect to the fundraiser list; the delete action requires using the new UI.
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\RedirectUtils;

RedirectUtils::redirect('fundraiser/');
