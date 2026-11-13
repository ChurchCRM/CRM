<?php
// This is a hard blocking error - application cannot continue without valid configuration
http_response_code(503);

require_once __DIR__ . '/template.php';
standaloneReportInstallFailure('config_invalid');

$errorRootPath = isset($errorRootPath) ? $errorRootPath : standaloneRootPath();

$errorMessage = isset($errorMessage) ? $errorMessage : (isset($_GET['error']) ? (string) $_GET['error'] : 'Configuration error');

// Build custom sections for this error
$customSections = "### Configuration Error Details\n\n";
$customSections .= "```\n" . str_replace(["\r", "\n", '`'], [' ', ' ', "'"], $errorMessage) . "\n```";

$issueBody = buildGitHubIssueBody('Configuration Error', $customSections);

$content = '<p class="text-secondary">ChurchCRM encountered an error during startup while reading your local configuration files.</p>'
    . '<div class="alert alert-warning border-2 mt-3">'
    . '<strong>Error Details:</strong><br>'
    . '<code class="small d-block mt-2 text-break">' . nl2br(htmlspecialchars($errorMessage)) . '</code>'
    . '</div>'
    . '<p class="text-secondary small mt-3"><strong>To fix this:</strong> Review your local <code>Include/Config.php</code> file and ensure it is valid. Check the error message above for details about what configuration issue occurred. If you do not have a Config.php file, you may need to run the <strong>setup wizard</strong>.</p>';

renderErrorPage($errorRootPath, 'Configuration Error', '⚙️', 'danger', $content, $issueBody);
