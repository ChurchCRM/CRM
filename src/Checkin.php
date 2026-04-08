<?php

/**
 * Legacy redirect — Checkin.php is now at /event/checkin (MVC).
 * This file preserves backward-compat for bookmarks and external links.
 */

require_once __DIR__ . '/Include/Config.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;

$eventId = (int) ($_GET['EventID'] ?? $_GET['eventId'] ?? $_POST['EventID'] ?? 0);
$query = [];

if ($eventId > 0) {
    $target = SystemURLs::getRootPath() . '/event/checkin/' . $eventId;
    if (!empty($_GET['AddedCount'])) {
        $query['AddedCount'] = (int) $_GET['AddedCount'];
    }
} else {
    $target = SystemURLs::getRootPath() . '/event/checkin';
    if (!empty($_GET['EventTypeID'])) {
        $query['EventTypeID'] = (int) $_GET['EventTypeID'];
    }
}

if (!empty($query)) {
    $target .= '?' . http_build_query($query);
}

RedirectUtils::redirect($target);
