<?php
// Legacy redirect shim — migrated to /fundraiser/
// Forward legacy DateStart/DateEnd filter params to the new dateStart/dateEnd query params.
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\RedirectUtils;

$params = [];
if (!empty($_GET['DateStart'])) {
    $params['dateStart'] = $_GET['DateStart'];
}
if (!empty($_GET['DateEnd'])) {
    $params['dateEnd'] = $_GET['DateEnd'];
}
$queryStr = $params ? '?' . http_build_query($params) : '';

RedirectUtils::redirect('fundraiser/' . $queryStr);
