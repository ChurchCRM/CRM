<?php
// This is a hard blocking error - application cannot continue without valid configuration
http_response_code(503);

require_once __DIR__ . '/template.php';

$errorMessage = $_GET['error'] ?? 'Configuration error';

// Build custom sections for this error
$customSections = "### Configuration Error Details\n\n";
$customSections .= "```\n" . htmlspecialchars($errorMessage) . "\n```";

$issueBody = buildGitHubIssueBody('Configuration Error', $customSections);

$content = '<p class="text-warning fw-bold mb-2">⚙️ Configuration Error</p>'
    . '<p class="text-muted">ChurchCRM encountered an error during startup while reading your local configuration files.</p>'
    . '<div class="alert alert-warning border-2 mt-3">'
    . '<strong>Error Details:</strong><br>'
    . '<code class="small d-block mt-2 text-break" style="word-break: break-word;">' . nl2br(htmlspecialchars($errorMessage)) . '</code>'
    . '</div>'
    . '<p class="text-muted small mt-3"><strong>To fix this:</strong> Review your local <code>Include/Config.php</code> file and ensure it is valid. Check the error message above for details about what configuration issue occurred. If you do not have a Config.php file, you may need to run the <strong>setup wizard</strong>.</p>';

renderErrorPage('Configuration Error', '⚙️', 'danger', $content, $issueBody);
