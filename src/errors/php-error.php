<?php
require_once __DIR__ . '/template.php';

// Get required PHP version from composer.json
$requiredPhpVersion = '8.4';
if (file_exists(__DIR__ . '/../../composer.json')) {
    $composer = json_decode(@file_get_contents(__DIR__ . '/../../composer.json'), true);
    if (is_array($composer) && !empty($composer['config']['platform']['php'])) {
        $requiredPhpVersion = $composer['config']['platform']['php'];
    }
}
$currentVersion = phpversion();

// Build custom sections for this error
$customSections = "### PHP Version Mismatch\n\n";
$customSections .= "- **Current:** `" . htmlspecialchars($currentVersion) . "`\n";
$customSections .= "- **Required:** `PHP " . htmlspecialchars($requiredPhpVersion) . "`\n\n";
$customSections .= "Please contact your hosting provider and request an upgrade.";

$issueBody = buildGitHubIssueBody('PHP Version Not Supported', $customSections);

$content = '<p class="text-muted">ChurchCRM requires a current version of PHP with active security support.</p>'
    . '<div class="alert alert-info bg-light border-info mt-3">'
    . '<strong>Your PHP Version:</strong> <code>' . htmlspecialchars($currentVersion) . '</code><br>'
    . '<strong>Required:</strong> <code>PHP ' . htmlspecialchars($requiredPhpVersion) . ' or later</code>'
    . '</div>'
    . '<p class="text-muted small mt-3">Contact your hosting provider to upgrade PHP.</p>';

renderErrorPage('PHP Version Not Supported', '⚠️', 'primary-purple', $content, $issueBody);
