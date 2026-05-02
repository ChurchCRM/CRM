<?php
require_once __DIR__ . '/template.php';

$errorMessage = $_GET['error'] ?? 'Configuration error';

// Build custom sections for this error
$customSections = "### Configuration Error Details\n\n";
$customSections .= "```\n" . htmlspecialchars($errorMessage) . "\n```";

$issueBody = buildGitHubIssueBody('Configuration Error', $customSections);

$content = '<p class="text-muted">ChurchCRM encountered an error reading or validating your configuration.</p>'
    . '<div class="alert alert-warning bg-light border-warning mt-3">'
    . '<strong>Error Details:</strong><br>'
    . '<code class="small d-block mt-2 text-break">' . nl2br(htmlspecialchars($errorMessage)) . '</code>'
    . '</div>'
    . '<p class="text-muted small mt-3">Review your <code>Include/Config.php</code> file and try again.</p>';

renderErrorPage('Configuration Error', '⚙️', 'danger', $content, $issueBody);
