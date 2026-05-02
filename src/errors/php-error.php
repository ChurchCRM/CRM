<?php
// This is a hard blocking error - application cannot continue
http_response_code(503);

require_once __DIR__ . '/template.php';

// Get required PHP version from composer.json (at root)
$requiredPhpVersion = '8.4';
if (file_exists(__DIR__ . '/../../composer.json')) {
    $composer = json_decode(@file_get_contents(__DIR__ . '/../../composer.json'), true);
    if (is_array($composer) && !empty($composer['config']['platform']['php'])) {
        $requiredPhpVersion = $composer['config']['platform']['php'];
    }
}
$currentVersion = phpversion();

// Build custom sections for this error
$customSections = "### PHP Version Mismatch (HARD ERROR)\n\n";
$customSections .= "- **Current:** `" . htmlspecialchars($currentVersion) . "`\n";
$customSections .= "- **Required:** `PHP " . htmlspecialchars($requiredPhpVersion) . "`\n\n";
$customSections .= "**Severity:** Hard blocking error - Application cannot run.\n\n";
$customSections .= "Contact your hosting provider and request an immediate upgrade.";

$issueBody = buildGitHubIssueBody('PHP Version Not Supported (Hard Error)', $customSections);

$content = '<p class="text-danger fw-bold mb-3">🚫 APPLICATION BLOCKED</p>'
    . '<p class="text-danger fw-bold">PHP Version Not Supported</p>'
    . '<p class="text-muted mt-2">Your web server is running an unsupported PHP version. ChurchCRM <strong>cannot run</strong> on the installed PHP version. <strong>This is a hard blocking error.</strong></p>'
    . '<div class="alert alert-danger border-2 mt-3">'
    . '<strong>Current PHP Version:</strong> <code>' . htmlspecialchars($currentVersion) . '</code><br>'
    . '<strong>Minimum Required:</strong> <code>PHP ' . htmlspecialchars($requiredPhpVersion) . ' or later</code>'
    . '</div>'
    . '<p class="text-danger fw-bold mt-3">⚠️ Action Required (URGENT)</p>'
    . '<p class="text-muted small"><strong>Contact your hosting provider immediately</strong> and request an upgrade to PHP ' . htmlspecialchars($requiredPhpVersion) . ' or later. ChurchCRM will not function until this requirement is met. If you manage your own server, upgrade PHP directly.</p>';

renderErrorPage('PHP Version Not Supported', '❌', 'danger', $content, $issueBody);
