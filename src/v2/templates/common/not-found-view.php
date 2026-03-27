<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Not Found") .":" . gettext($memberType);
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Provide values expected by the shared error partial
$lowerType = strtolower($memberType ?? '');
if ($lowerType === 'person' || $lowerType === 'people') {
    $returnUrl = SystemURLs::getRootPath() . '/v2/people';
    $returnText = gettext('Return to People');
    // Include the missing id prominently so it's not hidden
    $title = sprintf('%s: %s %s', gettext('Person not found'), strtoupper($memberType ?? 'PERSON'), htmlspecialchars($id ?? ''));
    $message = sprintf('%s %s %s', gettext('We could not find the person you were looking for.'), strtoupper($memberType ?? 'PERSON'), htmlspecialchars($id ?? ''));
    $code = 404;
} elseif ($lowerType === 'family' || $lowerType === 'families') {
    $returnUrl = SystemURLs::getRootPath() . '/v2/families';
    $returnText = gettext('Return to Families');
    $title = gettext('Family not found');
    $message = gettext('We could not find the family you were looking for.');
    $code = 404;
} else {
    $returnUrl = SystemURLs::getRootPath() . '/v2/dashboard';
    $returnText = gettext('Return to Dashboard');
    $title = gettext('Not Found');
    $message = gettext('We could not find the record you were looking for.');
    $code = 404;
}

require __DIR__ . '/error-page.php';

require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
