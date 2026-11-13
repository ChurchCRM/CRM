<?php
/**
 * Version mismatch page on the shared standalone error layout.
 *
 * Shown when:
 *  1. The DB version is newer than the installed software (downgrade detected)
 *  2. An automatic database upgrade failed (error from Bootstrapper)
 */
use ChurchCRM\Utils\InputUtils;

require_once dirname(__DIR__, 2) . '/errors/template.php';

$versionRows = '<table class="table table-sm table-borderless mb-0"><tbody>'
    . '<tr><th scope="row">' . InputUtils::escapeHTML(gettext('Current DB Version')) . '</th><td><code>' . InputUtils::escapeHTML($dbVersion) . '</code></td></tr>'
    . '<tr><th scope="row">' . InputUtils::escapeHTML(!empty($errorMessage) ? gettext('Current Software Version') : gettext('Installed Software Version')) . '</th><td><code>' . InputUtils::escapeHTML($softwareVersion) . '</code></td></tr>'
    . '</tbody></table>';

if (!empty($errorMessage)) {
    $title = gettext('Database Upgrade Failed');
    $lead = gettext('An automatic database upgrade was attempted but failed. Please review the error below and contact your system administrator.');
    $content = '<p class="text-secondary">' . InputUtils::escapeHTML($lead) . '</p>'
        . $versionRows
        . '<div class="alert alert-danger mt-3 mb-0" role="alert">' . InputUtils::escapeHTML($errorMessage) . '</div>';
    if (!empty($databaseNotice)) {
        $content .= '<div class="alert alert-warning mt-3 mb-0" role="alert">' . InputUtils::escapeHTML($databaseNotice) . '</div>';
    }
    $variant = 'danger';
    $icon = '❌';
    $issueSections = "### Database Upgrade Failed\n\n```\n" . str_replace(["\r", "\n", '`'], [' ', ' ', "'"], (string) $errorMessage) . "\n```";
} else {
    $title = gettext('Software Update Required');
    $lead = gettext('Your database version is newer than the installed software. This usually means the software was rolled back to an older version. Please upgrade ChurchCRM to match your database.');
    $content = '<p class="text-secondary">' . InputUtils::escapeHTML($lead) . '</p>'
        . $versionRows
        . '<div class="alert alert-warning mt-3 mb-0" role="alert">' . InputUtils::escapeHTML(gettext('Please upgrade ChurchCRM to version')) . ' <strong>' . InputUtils::escapeHTML($dbVersion) . '</strong> ' . InputUtils::escapeHTML(gettext('or later to continue.')) . '</div>';
    $variant = 'warning';
    $icon = '⚠️';
    $issueSections = "### Software Update Required\n\n- **Database version:** `" . $dbVersion . "`\n- **Software version:** `" . $softwareVersion . "`";
}

renderErrorPage($sRootPath, $title, $icon, $variant, $content, buildGitHubIssueBody($title, $issueSections));
