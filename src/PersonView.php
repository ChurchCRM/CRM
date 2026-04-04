<?php

/**
 * PersonView.php — Compatibility redirect.
 *
 * This page has been migrated to the Slim 4 MVC module at /people/view/{personID}.
 * This file is kept as a backward-compatible redirect so that existing links
 * (in emails, bookmarks, third-party integrations, etc.) continue to work.
 */

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'] ?? '', 'int');

if ($iPersonID > 0) {
    // Forward RemoveVO param if present (volunteer opportunity removal)
    $queryString = '';
    if (!empty($_GET['RemoveVO'])) {
        $iRemoveVO = (int) $_GET['RemoveVO'];
        if ($iRemoveVO > 0) {
            $queryString = '?RemoveVO=' . $iRemoveVO;
        }
    }
    RedirectUtils::redirect('people/view/' . $iPersonID . $queryString);
} else {
    RedirectUtils::redirect('people/view/not-found');
}
