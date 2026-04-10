<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Map role codes to human-readable descriptions
$roleDescriptions = [
    'Admin' => gettext('Administrator privileges'),
    'Finance' => gettext('Finance access'),
    'ManageGroups' => gettext('Group management access'),
    'EditRecords' => gettext('Edit records permission'),
    'DeleteRecords' => gettext('Delete records permission'),
    'AddRecords' => gettext('Add records permission'),
    'MenuOptions' => gettext('Menu options access'),
    'Notes' => gettext('Notes access'),
    'CreateDirectory' => gettext('Create directory permission'),
    'AddEvent' => gettext('Add event permission'),
    'Authentication' => gettext('User authentication'),
];

$roleDescription = isset($roleDescriptions[$missingRole])
    ? $roleDescriptions[$missingRole]
    : gettext('Required permission');

// Prepare variables for shared error partial
$code = 403;
$title = gettext('Permission Required');
$message = gettext('The page you tried to visit requires special permissions that your account does not currently have.');
$returnUrl = SystemURLs::getRootPath() . '/v2/dashboard';
$returnText = gettext('Go to Dashboard');

// Add role callout as extra HTML when a role is present
$extraHtml = '';
if (!empty($missingRole)) {
    $escaped = InputUtils::escapeHTML($roleDescription);
    $extraHtml = "<div class=\"callout callout-warning text-start mt-3\">" .
                 "<h5><i class=\"ti ti-key me-2\"></i> " . gettext('Required Permission') . "</h5>" .
                 "<p class=\"mb-0\"><strong>$escaped</strong></p>" .
                 "</div>";
}

require __DIR__ . '/error-page.php';

require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
